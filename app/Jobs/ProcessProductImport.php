<?php

namespace App\Jobs;

use App\Services\Importing\ProductImporter;
use App\Services\Importing\Support\ImportLogWriter;
use App\Services\Importing\Support\ImportProgressTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class ProcessProductImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(
        private string $batchId,
        private string $csvPath,
        private ?string $imageBasePath = null,
        private ?string $sourceFolderPath = null
    ) {}

    public function handle(ProductImporter $importer): void
    {
        $tracker = ImportProgressTracker::find($this->batchId);

        if (! $tracker) {
            return;
        }

        $logWriter = new ImportLogWriter($this->batchId);

        try {
            $logWriter->info("Import started for batch {$this->batchId}");
            $logWriter->info("CSV: {$this->csvPath}");

            if ($this->imageBasePath) {
                $importer->setImageBasePath($this->imageBasePath);
                $logWriter->info("Image base: {$this->imageBasePath}");
            }

            $importer->setTracker($tracker)->setLogWriter($logWriter);

            $parentIds = $importer->import($this->csvPath);

            // Run indexer as a separate process (some commands aren't available inside queue workers)
            $logWriter->info('Running indexer...');

            try {
                $php = PHP_BINARY;
                $artisan = base_path('artisan');

                $indexResult = Process::path(base_path())
                    ->timeout(300)
                    ->run("{$php} {$artisan} indexer:index --type=inventory --type=price --type=flat --mode=full");

                if ($indexResult->successful()) {
                    $logWriter->info('Indexer completed successfully.');
                } else {
                    $logWriter->info('Indexer warning: '.$indexResult->errorOutput());
                }

                Process::path(base_path())->timeout(60)->run("{$php} {$artisan} cache:clear");
                Process::path(base_path())->timeout(60)->run("{$php} {$artisan} responsecache:clear");

                $logWriter->info('Cache cleared.');
            } catch (\Throwable $e) {
                $logWriter->info('Indexer/cache warning: '.$e->getMessage());
            }

            $batch = $tracker->getBatch();

            $logWriter->summary([
                'created'  => $batch->created_count,
                'updated'  => $batch->updated_count,
                'skipped'  => $batch->skipped_count,
                'images'   => $batch->image_count,
                'errors'   => $batch->error_count,
                'duration' => $batch->started_at->diff(now())->format('%H:%I:%S'),
            ]);

            // Move source folder to processed
            if ($this->sourceFolderPath && is_dir($this->sourceFolderPath)) {
                $timestamp = now()->format('Y-m-d_His');
                $processedDir = storage_path("app/imports/processed/{$timestamp}_{$this->batchId}");
                File::ensureDirectoryExists(dirname($processedDir));
                File::moveDirectory($this->sourceFolderPath, $processedDir);
                $logWriter->info("Moved source folder to: {$processedDir}");
            }

            $tracker->complete();
        } catch (\Throwable $e) {
            $logWriter->error('FATAL', $e->getMessage());
            $tracker->fail($e->getMessage());

            // Move source folder to failed
            if ($this->sourceFolderPath && is_dir($this->sourceFolderPath)) {
                try {
                    $timestamp = now()->format('Y-m-d_His');
                    $failedDir = storage_path("app/imports/failed/{$timestamp}_{$this->batchId}");
                    File::ensureDirectoryExists(dirname($failedDir));
                    File::moveDirectory($this->sourceFolderPath, $failedDir);
                    $logWriter->info("Moved source folder to failed: {$failedDir}");
                } catch (\Throwable $moveError) {
                    $logWriter->info("Could not move to failed: {$moveError->getMessage()}");
                }
            }

            throw $e;
        }
    }
}
