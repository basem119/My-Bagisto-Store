<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Product\Models\Product;

class CheckProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check products and their category assignments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $products = Product::with(['categories.translations', 'product_flats'])->take(10)->get();

        $this->info('Sample Products and their Categories:');
        foreach ($products as $product) {
            $productFlat = $product->product_flats->first();
            $productName = $productFlat ? $productFlat->name : 'Product ID: ' . $product->id;
            $this->line("Product: {$productName}");

            foreach ($product->categories as $category) {
                $categoryName = $category->translations->first()->name ?? 'No name';
                $this->line("  - Category: {$categoryName}");
            }
            $this->line('');
        }

        $totalProducts = Product::count();
        $this->info("Total products in database: {$totalProducts}");
    }
}
