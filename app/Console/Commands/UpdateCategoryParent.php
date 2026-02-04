<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Category\Models\Category;

class UpdateCategoryParent extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update-category-parent';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update All Bags category parent_id to 1';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $category = Category::whereHas('translations', function($query) {
            $query->where('slug', 'all-bags');
        })->first();

        if ($category) {
            $category->parent_id = 1;
            $category->save();
            $this->info('Updated All Bags category parent_id to 1');
        } else {
            $this->error('All Bags category not found');
        }
    }
}
