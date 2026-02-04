<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Webkul\Category\Models\Category;

class CheckCategories extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-categories';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check current categories in the database';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $categories = Category::with('translations')->where('id', '>', 1)->get();

        $this->info('Current Categories:');
        foreach ($categories as $category) {
            $translation = $category->translations->first();
            $this->line("ID: {$category->id} - Name: {$translation->name} - Slug: {$translation->slug}");
        }

        $this->info("\nTotal categories: " . $categories->count());
    }
}
