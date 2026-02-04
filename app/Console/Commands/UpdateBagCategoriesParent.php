<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Category\Models\Category;

class UpdateBagCategoriesParent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-bag-categories-parent';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update all bag categories to be direct children of root category';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $bagSlugs = ['laptop-bag', 'waterproof-bag', 'backpack', 'cross-bag', 'school-bag'];

        foreach ($bagSlugs as $slug) {
            $category = Category::whereHas('translations', function($query) use ($slug) {
                $query->where('slug', $slug);
            })->first();

            if ($category) {
                $category->parent_id = 1;
                $category->save();
                $this->info("Updated {$slug} parent_id to 1");
            } else {
                $this->error("Category {$slug} not found");
            }
        }

        $this->info('All bag categories updated to be direct children of root category');
    }
}
