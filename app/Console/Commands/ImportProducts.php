<?php

namespace App\Console\Commands;

use App\Services\Importing\ProductImporter;
use Illuminate\Console\Command;

class ImportProducts extends Command
{
    protected $signature = 'import:products';

    protected $description = 'Import products';

    public function __construct(private ProductImporter $productImporter)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $path = storage_path('app/import/products.csv');

        if (! file_exists($path)) {
            $this->error('CSV not found');

            return self::FAILURE;
        }

        try {

            $parentIds = $this->productImporter->import($path);

            $this->info('Imported parent products: '.count($parentIds));

            $this->info('Reindexing product inventory, prices, and flat data...');

            $this->call('indexer:index', [
                '--type' => ['inventory', 'price', 'flat'],
                '--mode' => ['full'],
            ]);

            $this->info('Import completed');

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
    }
}
