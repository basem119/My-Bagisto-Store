<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessProductImport;
use App\Services\Importing\Support\ImportProgressTracker;
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

            if ($extension !== 'zip') {
                return response()->json([
                    'error' => "Invalid file type: .{$extension}. Only .zip files are accepted.",
                ], 422);
            }

            $batchId = (string) Str::uuid();
            $extractPath = storage_path("app/imports/{$batchId}");

            File::ensureDirectoryExists($extractPath);

            if (! class_exists(ZipArchive::class)) {
                return response()->json(['error' => 'PHP zip extension is not installed.'], 500);
            }

            $zip = new ZipArchive;
            $openResult = $zip->open($file->getPathname());

            if ($openResult !== true) {
                File::deleteDirectory($extractPath);

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

            $zip->extractTo($extractPath);
            $zip->close();

            $csvPath = $this->findCsv($extractPath);

            if (! $csvPath) {
                // List what was extracted for debugging
                $contents = collect(File::allFiles($extractPath))
                    ->map(fn ($f) => $f->getRelativePathname())
                    ->take(20)
                    ->implode(', ');

                File::deleteDirectory($extractPath);

                return response()->json([
                    'error' => "No products.csv found in ZIP. Extracted files: {$contents}",
                ], 422);
            }

            $imagesPath = $this->findImagesDir($extractPath);

            $tracker = ImportProgressTracker::create('upload');
            $tracker->getBatch()->update(['batch_id' => $batchId]);

            ProcessProductImport::dispatch(
                $batchId,
                $csvPath,
                $imagesPath,
                $extractPath
            );

            return response()->json([
                'batch_id'   => $batchId,
                'message'    => 'Import started.',
                'csv'        => basename($csvPath),
                'has_images' => $imagesPath !== null,
            ]);
        } catch (\Throwable $e) {
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

            $folderName = basename($request->input('folder'));
            $folderPath = storage_path("app/imports/{$folderName}");

            if (! is_dir($folderPath)) {
                return response()->json(['error' => "Folder '{$folderName}' not found in storage/app/imports/."], 422);
            }

            $csvPath = $this->findCsv($folderPath);

            if (! $csvPath) {
                return response()->json(['error' => 'No products.csv found in folder.'], 422);
            }

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
            ->filter(fn ($name) => $name !== 'processed')
            ->values();

        return response()->json(['folders' => $folders]);
    }

    private function findCsv(string $basePath): ?string
    {
        if (file_exists("{$basePath}/products.csv")) {
            return "{$basePath}/products.csv";
        }

        $dirs = File::directories($basePath);

        foreach ($dirs as $dir) {
            if (file_exists("{$dir}/products.csv")) {
                return "{$dir}/products.csv";
            }
        }

        return null;
    }

    private function findImagesDir(string $basePath): ?string
    {
        if (is_dir("{$basePath}/images")) {
            return "{$basePath}/images";
        }

        $dirs = File::directories($basePath);

        foreach ($dirs as $dir) {
            if (is_dir("{$dir}/images")) {
                return "{$dir}/images";
            }
        }

        return null;
    }
}
