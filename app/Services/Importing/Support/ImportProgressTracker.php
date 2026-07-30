<?php

namespace App\Services\Importing\Support;

use App\Models\ImportBatch;

class ImportProgressTracker
{
    private ImportBatch $batch;

    public function __construct(ImportBatch $batch)
    {
        $this->batch = $batch;
    }

    public static function create(string $source, ?string $folderPath = null): self
    {
        $batch = ImportBatch::create([
            'batch_id'    => (string) \Illuminate\Support\Str::uuid(),
            'status'      => 'pending',
            'source'      => $source,
            'folder_path' => $folderPath,
        ]);

        return new self($batch);
    }

    public static function find(string $batchId): ?self
    {
        $batch = ImportBatch::where('batch_id', $batchId)->first();

        return $batch ? new self($batch) : null;
    }

    public function getBatchId(): string
    {
        return $this->batch->batch_id;
    }

    public function getBatch(): ImportBatch
    {
        return $this->batch->fresh();
    }

    public function start(int $totalRows, string $logFile): void
    {
        $this->batch->update([
            'status'     => 'processing',
            'total_rows' => $totalRows,
            'log_file'   => $logFile,
            'started_at' => now(),
        ]);
    }

    public function incrementCreated(): void
    {
        $this->batch->increment('created_count');
        $this->batch->increment('processed_rows');
    }

    public function incrementUpdated(): void
    {
        $this->batch->increment('updated_count');
        $this->batch->increment('processed_rows');
    }

    public function incrementSkipped(): void
    {
        $this->batch->increment('skipped_count');
        $this->batch->increment('processed_rows');
    }

    public function incrementImages(int $count = 1): void
    {
        $this->batch->increment('image_count', $count);
    }

    public function incrementErrors(): void
    {
        $this->batch->increment('error_count');
        $this->batch->increment('processed_rows');
    }

    public function complete(): void
    {
        $this->batch->update([
            'status'       => 'completed',
            'completed_at' => now(),
        ]);
    }

    public function fail(string $message): void
    {
        $this->batch->update([
            'status'        => 'failed',
            'error_message' => $message,
            'completed_at'  => now(),
        ]);
    }

    public function toArray(): array
    {
        $batch = $this->getBatch();

        return [
            'batch_id'       => $batch->batch_id,
            'status'         => $batch->status,
            'total_rows'     => $batch->total_rows,
            'processed_rows' => $batch->processed_rows,
            'created_count'  => $batch->created_count,
            'updated_count'  => $batch->updated_count,
            'skipped_count'  => $batch->skipped_count,
            'image_count'    => $batch->image_count,
            'error_count'    => $batch->error_count,
            'error_message'  => $batch->error_message,
            'log_file'       => $batch->log_file,
            'started_at'     => $batch->started_at?->toIso8601String(),
            'completed_at'   => $batch->completed_at?->toIso8601String(),
            'percentage'     => $batch->total_rows > 0
                ? min(100, round(($batch->processed_rows / $batch->total_rows) * 100))
                : 0,
            'duration'       => $batch->started_at && $batch->completed_at
                ? $batch->started_at->diff($batch->completed_at)->format('%H:%I:%S')
                : ($batch->started_at ? $batch->started_at->diff(now())->format('%H:%I:%S') : null),
        ];
    }
}
