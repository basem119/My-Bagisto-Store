<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Webkul\Category\Models\Category;
use Webkul\Category\Repositories\CategoryRepository;
use Webkul\Product\Models\Product;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categoryRepository = app(CategoryRepository::class);

        // Get the default locale code
        $defaultLocale = core()->getDefaultLocaleCodeFromDefaultChannel();

        // Check if All Bags category already exists
        $existingAllBags = Category::whereHas('translations', function($query) {
            $query->where('slug', 'all-bags');
        })->first();

        if (!$existingAllBags) {
            $allBagsData = [
                $defaultLocale => [
                    'name' => 'All Bags',
                    'slug' => 'all-bags',
                    'description' => 'Browse all our bag categories and find the perfect bag for your needs',
                ],
                'status' => 1,
                'position' => 0, // Parent category comes first
                'display_mode' => 'products_and_description',
                'parent_id' => 1, // Under root category
            ];

            $allBagsCategory = $categoryRepository->create($allBagsData);
            $this->command->info("Created parent category: All Bags");
            $newCategoriesCreated = true;
        } else {
            // Update existing category to ensure it has correct parent_id
            $existingAllBags->parent_id = 1;
            $existingAllBags->save();
            $allBagsCategory = $existingAllBags;
            $this->command->info("Updated existing parent category: All Bags");
        }

        // New categories to add (as direct children of root category)
        $newCategories = [
            [
                'name' => 'Laptop Bag',
                'slug' => 'laptop-bag',
                'description' => 'Bags designed specifically for laptops and tech gadgets',
                'status' => 1,
                'position' => 1,
                'parent_id' => 1, // Direct child of root
            ],
            [
                'name' => 'Waterproof Bag',
                'slug' => 'waterproof-bag',
                'description' => 'Water-resistant and waterproof bags for all weather conditions',
                'status' => 1,
                'position' => 2,
                'parent_id' => 1, // Direct child of root
            ],
            [
                'name' => 'Backpack',
                'slug' => 'backpack',
                'description' => 'Comfortable backpacks for daily use and travel',
                'status' => 1,
                'position' => 3,
                'parent_id' => 1, // Direct child of root
            ],
            [
                'name' => 'Cross Bag',
                'slug' => 'cross-bag',
                'description' => 'Stylish crossbody bags and messenger bags',
                'status' => 1,
                'position' => 4,
                'parent_id' => 1, // Direct child of root
            ],
            [
                'name' => 'School Bag',
                'slug' => 'school-bag',
                'description' => 'Durable school bags and backpacks for students',
                'status' => 1,
                'position' => 5,
                'parent_id' => 1, // Direct child of root
            ],
        ];

        // Create new categories and collect their IDs (only if they don't exist)
        $createdCategoryIds = [$allBagsCategory->id]; // Include the parent category
        $newCategoriesCreated = false;
        foreach ($newCategories as $categoryData) {
            // Check if category already exists
            $existingCategory = Category::whereHas('translations', function($query) use ($categoryData) {
                $query->where('slug', $categoryData['slug']);
            })->first();

            if (!$existingCategory) {
                $data = [
                    $defaultLocale => [
                        'name' => $categoryData['name'],
                        'slug' => $categoryData['slug'],
                        'description' => $categoryData['description'],
                    ],
                    'status' => $categoryData['status'],
                    'position' => $categoryData['position'],
                    'display_mode' => 'products_and_description',
                    'parent_id' => $categoryData['parent_id'],
                ];

                $category = $categoryRepository->create($data);
                $createdCategoryIds[] = $category->id;
                $this->command->info("Created category: {$categoryData['name']}");
                $newCategoriesCreated = true;
            } else {
                $createdCategoryIds[] = $existingCategory->id;
                $this->command->info("Category already exists: {$categoryData['name']}");
            }
        }

        // Find the school bag category for reassignment
        $schoolBagCategory = Category::whereHas('translations', function($query) {
            $query->where('slug', 'school-bag');
        })->first();

        if ($schoolBagCategory) {
            // Get all products and assign them to both All Bags and School Bag categories
            $products = Product::all();
            foreach ($products as $product) {
                $productName = $product->product_flats->first() ? $product->product_flats->first()->name : 'Product ID: ' . $product->id;

                // Add All Bags category if not already assigned
                if (!$product->categories->contains($allBagsCategory->id)) {
                    $product->categories()->attach($allBagsCategory->id);
                    $this->command->info("Assigned product '{$productName}' to All Bags");
                }

                // Add School Bag category if not already assigned
                if (!$product->categories->contains($schoolBagCategory->id)) {
                    $product->categories()->attach($schoolBagCategory->id);
                    $this->command->info("Assigned product '{$productName}' to School Bag");
                }
            }
        }

        // Only delete old categories if we created new ones (first run)
        if ($newCategoriesCreated) {
            // Get all existing categories except the root category and the ones we just created
            $existingCategories = Category::where('id', '>', 1)
                ->whereNotIn('id', $createdCategoryIds)
                ->get();

            // Delete old categories (products are already assigned to new categories above)
            foreach ($existingCategories as $oldCategory) {
                $oldCategory->delete();
                $this->command->info("Deleted old category: {$oldCategory->name}");
            }
        }

        $this->command->info('Category management completed!');
    }
}
