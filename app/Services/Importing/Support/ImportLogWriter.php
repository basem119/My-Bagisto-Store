<?php

namespace App\Services\Importing\Support;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

class ImportLogWriter
{
    private LoggerInterface $logger;

    private string $logFile;

    public function __construct(string $batchId)
    {
        $this->logFile = "import_{$batchId}.log";

        $this->logger = Log::build([
            'driver' => 'single',
            'path'   => storage_path("logs/{$this->logFile}"),
        ]);
    }

    public function getLogFile(): string
    {
        return $this->logFile;
    }

    public function getLogPath(): string
    {
        return storage_path("logs/{$this->logFile}");
    }

    public function created(string $sku, string $type = 'configurable'): void
    {
        $this->logger->info("CREATED | {$type} | {$sku}");
    }

    public function updated(string $sku, string $type = 'configurable'): void
    {
        $this->logger->info("UPDATED | {$type} | {$sku}");
    }

    public function skipped(string $sku, string $reason): void
    {
        $this->logger->info("SKIPPED | {$sku} | {$reason}");
    }

    public function imageImported(string $sku, string $file): void
    {
        $this->logger->info("IMAGE   | {$sku} | {$file}");
    }

    public function imageMissing(string $sku, string $folder): void
    {
        $this->logger->warning("IMG_MISS | {$sku} | folder not found: {$folder}");
    }

    public function error(string $sku, string $message): void
    {
        $this->logger->error("ERROR   | {$sku} | {$message}");
    }

    public function info(string $message): void
    {
        $this->logger->info($message);
    }

    public function summary(array $stats): void
    {
        $this->logger->info('=== IMPORT SUMMARY ===');
        $this->logger->info("Created:  {$stats['created']}");
        $this->logger->info("Updated:  {$stats['updated']}");
        $this->logger->info("Skipped:  {$stats['skipped']}");
        $this->logger->info("Images:   {$stats['images']}");
        $this->logger->info("Errors:   {$stats['errors']}");
        $this->logger->info("Duration: {$stats['duration']}");
    }
}
