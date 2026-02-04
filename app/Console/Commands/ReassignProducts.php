<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Category\Models\Category;
use Webkul\Product\Models\Product;

class ReassignProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:reassign-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reassign all products to the School Bag category';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Find the School Bag category
        $schoolBagCategory = Category::whereHas('translations', function($query) {
            $query->where('slug', 'school-bag');
        })->first();

        if (!$schoolBagCategory) {
            $this->error('School Bag category not found!');
            return;
        }

        $this->info("Found School Bag category: {$schoolBagCategory->translations->first()->name}");

        // Get all products
        $products = Product::all();
        $assignedCount = 0;

        foreach ($products as $product) {
            // Check if product is already assigned to School Bag
            if (!$product->categories->contains($schoolBagCategory->id)) {
                $product->categories()->attach($schoolBagCategory->id);
                $assignedCount++;
                $productName = $product->product_flats->first() ? $product->product_flats->first()->name : $product->id;
                $this->line("Assigned product '{$productName}' to School Bag");
            }
        }

        $this->info("Successfully assigned {$assignedCount} products to School Bag category");
        $this->info("Total products in database: " . $products->count());
    }
}
