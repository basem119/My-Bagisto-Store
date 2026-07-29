<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessProductImport;
use App\Services\Importing\Support\ImportProgressTracker;
use App\Services\Importing\Support\PathHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use ZipArchive;

class ProductImportController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        try {
            if (! $request->hasFile('file')) {
                return response()->json([
                    'error' => 'No file received. Check that your ZIP is under the PHP upload limit ('.ini_get('upload_max_filesize').').',
                ], 422);
            }

            $file = $request->file('file');

            if (! $file->isValid()) {
                return response()->json([
                    'error' => 'Upload failed: '.$file->getErrorMessage(),
                ], 422);
            }

            $extension = strtolower($file->getClientOriginalExtension());

            // Reject RAR with clear message
            if ($extension === 'rar') {
                return response()->json([
                    'error' => 'RAR archives are not supported. Please compress your import package as a ZIP archive.',
                ], 422);
            }

            if ($extension !== 'zip') {
                return response()->json([
                    'error' => "Invalid file type: .{$extension}. Only .zip files are accepted.",
                ], 422);
            }

            if (! class_exists(ZipArchive::class)) {
                return response()->json(['error' => 'PHP zip extension is not installed.'], 500);
            }

            // Extract to tmp directory first
            $batchId = (string) Str::uuid();
            $tmpPath = storage_path("app/imports/tmp/{$batchId}");
            File::ensureDirectoryExists($tmpPath);

            $zip = new ZipArchive;
            $openResult = $zip->open($file->getPathname());

            if ($openResult !== true) {
                File::deleteDirectory($tmpPath);

                $zipErrors = [
                    ZipArchive::ER_EXISTS => 'File already exists',
                    ZipArchive::ER_INCONS => 'Inconsistent archive',
                    ZipArchive::ER_INVAL  => 'Invalid argument',
                    ZipArchive::ER_MEMORY => 'Memory allocation failure',
                    ZipArchive::ER_NOENT  => 'No such file',
                    ZipArchive::ER_NOZIP  => 'Not a zip archive',
                    ZipArchive::ER_OPEN   => 'Cannot open file',
                    ZipArchive::ER_READ   => 'Read error',
                    ZipArchive::ER_SEEK   => 'Seek error',
                ];

                $errorMsg = $zipErrors[$openResult] ?? "Unknown error (code: {$openResult})";

                return response()->json(['error' => "Failed to open ZIP: {$errorMsg}"], 422);
            }

            $zip->extractTo($tmpPath);
            $zip->close();

            // Validate package structure
            $validationError = $this->validatePackageStructure($tmpPath);

            if ($validationError) {
                File::deleteDirectory($tmpPath);

                return response()->json(['error' => $validationError], 422);
            }

            $csvPath = $this->findCsv($tmpPath);
            $imagesPath = $this->findImagesDir($tmpPath);

            // Move from tmp to active batch directory
            $batchPath = storage_path("app/imports/{$batchId}");
            File::moveDirectory($tmpPath, $batchPath);

            // Update paths to reflect the move
            $csvPath = str_replace(
                PathHelper::normalize($tmpPath),
                PathHelper::normalize($batchPath),
                PathHelper::normalize($csvPath)
            );

            if ($imagesPath) {
                $imagesPath = str_replace(
                    PathHelper::normalize($tmpPath),
                    PathHelper::normalize($batchPath),
                    PathHelper::normalize($imagesPath)
                );
            }

            $tracker = ImportProgressTracker::create('upload');
            $tracker->getBatch()->update(['batch_id' => $batchId]);

            ProcessProductImport::dispatch(
                $batchId,
                $csvPath,
                $imagesPath,
                $batchPath
            );

            return response()->json([
                'batch_id'   => $batchId,
                'message'    => 'Import started.',
                'csv'        => basename($csvPath),
                'has_images' => $imagesPath !== null,
            ]);
        } catch (\Throwable $e) {
            // Clean up tmp directory on failure
            if (isset($tmpPath) && is_dir($tmpPath)) {
                File::deleteDirectory($tmpPath);
            }

            Log::error('Import upload failed: '.$e->getMessage(), ['trace' => $e->getTraceAsString()]);

            return response()->json([
                'error' => 'Import failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function fromFolder(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'folder' => 'required|string|max:255',
            ]);

            // Prevent directory traversal
            $folderName = basename($request->input('folder'));

            if (str_contains($folderName, '..')) {
                return response()->json(['error' => 'Invalid folder name.'], 422);
            }

            $folderPath = storage_path("app/imports/{$folderName}");

            if (! is_dir($folderPath)) {
                return response()->json(['error' => "Folder '{$folderName}' not found in storage/app/imports/."], 422);
            }

            $validationError = $this->validatePackageStructure($folderPath);

            if ($validationError) {
                return response()->json(['error' => $validationError], 422);
            }

            $csvPath = $this->findCsv($folderPath);
            $imagesPath = $this->findImagesDir($folderPath);

            $tracker = ImportProgressTracker::create('folder', $folderPath);
            $batchId = $tracker->getBatchId();

            ProcessProductImport::dispatch(
                $batchId,
                $csvPath,
                $imagesPath,
                $folderPath
            );

            return response()->json([
                'batch_id' => $batchId,
                'message'  => 'Import started.',
            ]);
        } catch (\Throwable $e) {
            Log::error('Import from folder failed: '.$e->getMessage());

            return response()->json([
                'error' => 'Import failed: '.$e->getMessage(),
            ], 500);
        }
    }

    public function progress(string $batchId): JsonResponse
    {
        $tracker = ImportProgressTracker::find($batchId);

        if (! $tracker) {
            return response()->json(['error' => 'Batch not found.'], 404);
        }

        return response()->json($tracker->toArray());
    }

    public function downloadLog(string $batchId): mixed
    {
        $tracker = ImportProgressTracker::find($batchId);

        if (! $tracker) {
            return response()->json(['error' => 'Batch not found.'], 404);
        }

        $batch = $tracker->getBatch();

        if (! $batch->log_file) {
            return response()->json(['error' => 'No log file available.'], 404);
        }

        $logPath = storage_path("logs/{$batch->log_file}");

        if (! file_exists($logPath)) {
            return response()->json(['error' => 'Log file not found.'], 404);
        }

        return response()->download($logPath, $batch->log_file, [
            'Content-Type' => 'text/plain',
        ]);
    }

    public function folders(): JsonResponse
    {
        $importsDir = storage_path('app/imports');

        if (! is_dir($importsDir)) {
            return response()->json(['folders' => []]);
        }

        $folders = collect(File::directories($importsDir))
            ->map(fn ($path) => basename($path))
            ->filter(fn ($name) => ! in_array($name, ['processed', 'failed', 'tmp'], true))
            ->values();

        return response()->json(['folders' => $folders]);
    }

    /**
     * Validate that the extracted package contains required files.
     */
    private function validatePackageStructure(string $basePath): ?string
    {
        $csvPath = $this->findCsv($basePath);

        if (! $csvPath) {
            $contents = collect(File::allFiles($basePath))
                ->map(fn ($f) => $f->getRelativePathname())
                ->take(20)
                ->implode(', ');

            return "Invalid package: products.csv not found. Extracted contents: {$contents}";
        }

        $imagesDir = $this->findImagesDir($basePath);

        if (! $imagesDir) {
            return 'Invalid package: images/ directory not found. The package must contain both products.csv and an images/ folder.';
        }

        return null;
    }

    private function findCsv(string $basePath): ?string
    {
        $normalized = PathHelper::normalize($basePath);

        if (file_exists("{$normalized}/products.csv")) {
            return "{$normalized}/products.csv";
        }

        // Check one level deeper (ZIP might have a wrapper folder)
        if (! is_dir($normalized)) {
            return null;
        }

        $dirs = File::directories($normalized);

        foreach ($dirs as $dir) {
            $dir = PathHelper::normalize($dir);

            if (file_exists("{$dir}/products.csv")) {
                return "{$dir}/products.csv";
            }
        }

        return null;
    }

    private function findImagesDir(string $basePath): ?string
    {
        $normalized = PathHelper::normalize($basePath);

        if (is_dir("{$normalized}/images")) {
            return "{$normalized}/images";
        }

        if (! is_dir($normalized)) {
            return null;
        }

        $dirs = File::directories($normalized);

        foreach ($dirs as $dir) {
            $dir = PathHelper::normalize($dir);

            if (is_dir("{$dir}/images")) {
                return "{$dir}/images";
            }
        }

        return null;
    }
}
