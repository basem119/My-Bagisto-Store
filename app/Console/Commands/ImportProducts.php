<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

use Webkul\Product\Repositories\ProductRepository;
use Webkul\Category\Repositories\CategoryRepository;
use Webkul\Attribute\Repositories\AttributeRepository;

class ImportProducts extends Command
{
    protected $signature = 'import:products';

    protected $description = 'Import products';

    public function __construct(
        protected ProductRepository $productRepository,
        protected CategoryRepository $categoryRepository,
        protected AttributeRepository $attributeRepository
    ) {
        parent::__construct();
    }

    public function handle()
    {
        $path = storage_path('app/import/products.csv');

        if (!file_exists($path)) {
            $this->error('CSV not found');
            return;
        }

        DB::beginTransaction();

        try {

            $rows = $this->readCsv($path);

            $grouped = collect($rows)->groupBy('parent_sku');

            $parentIds = [];

            foreach ($grouped as $parentSku => $items) {

                $this->info("Processing: {$parentSku}");

                $parent = $this->createConfigurableProduct(
                    $parentSku,
                    $items->first()
                );

                $parentIds[] = $parent->id;

                $firstVariant = null;

                foreach ($items as $item) {
                    $variant = $this->createSimpleProduct(
                        $parent,
                        $item
                    );
                    
                    if (!$firstVariant) {
                        $firstVariant = $variant;
                    }
                }
                if ($firstVariant) {

                    $firstImage = DB::table('product_images')
                        ->where('product_id', $firstVariant->id)
                        ->first();

                    if ($firstImage) {

                        DB::table('product_images')->insert([
                            'type' => 'image',
                            'path' => $firstImage->path,
                            'product_id' => $parent->id,
                            'position' => 0
                        ]);
                    }
                }
                DB::table('product_super_attributes')->updateOrInsert([
                    'product_id' => $parent->id,
                    'attribute_id' => $this->getAttributeId('color')
                ]);
            }

            foreach ($parentIds as $parentId) {
                $this->syncRelatedProductsByCategory($parentId);
            }

            DB::commit();

            $this->info('Reindexing product inventory, prices, and flat data...');

            $this->call('indexer:index', [
                '--type' => ['inventory', 'price', 'flat'],
                '--mode' => ['full'],
            ]);

            $this->info('Import completed');

        } catch (\Exception $e) {

            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            $this->error($e->getMessage());
        }
    }

    private function readCsv($path)
    {
        $rows = [];

        $handle = fopen($path, 'r');

        $header = fgetcsv($handle);

        $header = array_map(function ($h) {

            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h);

            return trim(strtolower($h));

        }, $header);

        while (($data = fgetcsv($handle)) !== false) {

            $data = array_map('trim', $data);

            $rows[] = array_combine($header, $data);
        }

        fclose($handle);

        return $rows;
    }

    private function createConfigurableProduct($sku, $data)
    {
        $product = $this->productRepository->create([
            'sku' => $sku,
            'type' => 'configurable',
            'attribute_family_id' => 1,
        ]);

        // EN
        $this->saveAttribute($product->id, 'sku', $sku);
        $this->saveAttribute($product->id, 'name', $data['name_en'], 'en');
        $this->saveAttribute($product->id, 'description', $data['description_en'], 'en');
        $this->saveAttribute($product->id, 'short_description', $data['description_en'], 'en');
        $this->saveAttribute(
            $product->id,
            'url_key',
            Str::slug($data['name_en']),
            'en'
        );

        // AR
        $this->saveAttribute($product->id, 'name', $data['name_ar'], 'ar');
        $this->saveAttribute($product->id, 'description', $data['description_ar'], 'ar');
        $this->saveAttribute($product->id, 'short_description', $data['description_ar'], 'ar');
        $this->saveAttribute(
            $product->id,
            'url_key',
            Str::slug($sku . '-ar'),
            'ar'
        );

        // GLOBAL
        $this->saveAttribute($product->id, 'status', 1);
        $this->saveAttribute($product->id, 'visible_individually', 1);

        $this->attachCategory(
            (object)['id' => $product->id],
            $data['category']
        );

        return $product;
    }
    private function createSimpleProduct($parent, $data)
    {
        $product = $this->productRepository->create([
            'sku' => $data['sku'],
            'type' => 'simple',
            'attribute_family_id' => 1,
            'parent_id' => $parent->id,
        ]);

        $productId = $product->id;
        // EN
        $this->saveAttribute($productId, 'sku', $data['sku']);
        $this->saveAttribute($productId, 'name', $data['name_en'], 'en');
        $this->saveAttribute($productId, 'description', $data['description_en'], 'en');
        $this->saveAttribute($productId, 'short_description', $data['description_en'], 'en');
        $this->saveAttribute(
            $productId,
            'url_key',
            Str::slug($data['name_en'] . '-' . $data['color']),
            'en'
        );

        // AR
        $this->saveAttribute($productId, 'name', $data['name_ar'], 'ar');
        $this->saveAttribute($productId, 'description', $data['description_ar'], 'ar');
        $this->saveAttribute($productId, 'short_description', $data['description_ar'], 'ar');
        $this->saveAttribute(
            $productId,
            'url_key',
            Str::slug($data['sku'] . '-ar'),
            'ar'
        );

        // GLOBAL
        $this->saveAttribute($productId, 'status', 1);
        $this->saveAttribute($productId, 'weight', 40);
        $this->saveAttribute($productId, 'visible_individually', 0);

        $this->saveAttribute(
            $productId,
            'price',
            $this->cleanNumber($data['price_before_discount'])
        );

        $this->saveAttribute(
            $productId,
            'special_price',
            $this->cleanNumber($data['price'])
        );

        // COLOR
        $colorId = $this->getAttributeOptionId('color', $data['color']);

        if ($colorId) {
            $this->saveAttribute($productId, 'color', $colorId);
        }
        $this->attachCategory(
            (object)['id' => $productId],
            $data['category']
        );
        $this->attachInventory((object)['id' => $productId], $data['qty']);

        $this->attachImagesFromFolder((object)['id' => $productId], $data['sku']);
        return (object)['id' => $productId];
    }

    private function attachCategory($product, $categoryName)
    {
        $category = DB::table('category_translations')
            ->where('name', $categoryName)
            ->first();

        if (!$category) {

            $this->warn("Category missing: {$categoryName}");

            return;
        }

        DB::table('product_categories')->updateOrInsert([
            'product_id' => $product->id,
            'category_id' => $category->category_id
        ]);
    }

    private function syncRelatedProductsByCategory(int $productId): void
    {
        $categoryIds = DB::table('product_categories')
            ->where('product_id', $productId)
            ->pluck('category_id');

        DB::table('product_relations')
            ->where('parent_id', $productId)
            ->delete();

        if ($categoryIds->isEmpty()) {
            return;
        }

        $relatedProductIds = DB::table('product_categories')
            ->join('products', 'products.id', '=', 'product_categories.product_id')
            ->whereIn('product_categories.category_id', $categoryIds)
            ->where('products.id', '!=', $productId)
            ->whereNull('products.parent_id')
            ->where('products.type', 'configurable')
            ->distinct()
            ->pluck('products.id');

        foreach ($relatedProductIds as $relatedProductId) {
            DB::table('product_relations')->insert([
                'parent_id' => $productId,
                'child_id'  => $relatedProductId,
            ]);
        }
    }

    // private function createColorOption($color)
    // {
    //     $attribute = DB::table('attributes')
    //         ->where('code', 'color')
    //         ->first();

    //     $existing = DB::table('attribute_options')
    //         ->where('attribute_id', $attribute->id)
    //         ->where('admin_name', $color)
    //         ->first();

    //     if ($existing) {
    //         return $existing->id;
    //     }

    //     $id = DB::table('attribute_options')->insertGetId([
    //         'attribute_id' => $attribute->id,
    //         'admin_name' => $color,
    //         'sort_order' => 0
    //     ]);

    //     DB::table('attribute_option_translations')->insert([
    //         [
    //             'locale' => 'en',
    //             'attribute_option_id' => $id,
    //             'label' => $color
    //         ],
    //         [
    //             'locale' => 'ar',
    //             'attribute_option_id' => $id,
    //             'label' => $color
    //         ]
    //     ]);

    //     return $id;
    // }

    private function attachInventory($product, $qty)
    {
        DB::table('product_inventories')->updateOrInsert(
            [
                'product_id' => $product->id,
                'inventory_source_id' => 1
            ],
            [
                'qty' => $qty,
                'vendor_id' => 0
            ]
        );
    }

    private function attachImagesFromFolder($product, $sku)
    {
        $folder = storage_path("app/import/images/{$sku}");

        if (!is_dir($folder)) {
            return;
        }

        $files = scandir($folder);

        $position = 0;

        foreach ($files as $file) {

            if (in_array($file, ['.', '..'])) {
                continue;
            }

            $fullPath = $folder . '/' . $file;

            if (!is_file($fullPath)) {
                continue;
            }

            $newPath = "product/{$sku}/{$file}";

            Storage::disk('public')->put(
                $newPath,
                file_get_contents($fullPath)
            );

            DB::table('product_images')->insert([
                  'product_id' => $product->id,
                    'type' => 'image',
                    'path' => $newPath,
                    'position' => $position++
                ]);
        }
    }

    private function getAttributeId($code)
    {
        return DB::table('attributes')
            ->where('code', $code)
            ->value('id');
    }
    private function saveAttribute($productId, $attributeCode, $value, $locale = null)
    {
        if ($value === null || $value === '') {
            return;
        }

        $attribute = DB::table('attributes')
            ->where('code', $attributeCode)
            ->first();

        if (!$attribute) {
            return;
        }

        $data = [
            'product_id'   => $productId,
            'attribute_id' => $attribute->id,
            'locale'       => $locale,
            'channel'      => 'default',
            'unique_id'    => uniqid(),
        ];

        switch ($attribute->type) {

            case 'text':
            case 'textarea':
                $data['text_value'] = $value;
                break;

            case 'select':
                $data['integer_value'] = (int) $value;
                break;

            case 'price':
            case 'decimal':
                $data['float_value'] = (float) $value;
                break;

            case 'boolean':
                $data['boolean_value'] = (bool) $value;
                break;

            case 'integer':
                $data['integer_value'] = (int) $value;
                break;

            default:
                $data['text_value'] = $value;
        }

        DB::table('product_attribute_values')->updateOrInsert(
            [
                'product_id' => $productId,
                'attribute_id' => $attribute->id,
                'locale' => $locale,
                'channel' => 'default',
            ],
            $data
        );
    }
    private function getAttributeOptionId($attributeCode, $optionLabel)
    {
        $attribute = DB::table('attributes')
            ->where('code', $attributeCode)
            ->first();

        if (!$attribute) {
            return null;
        }

        $option = DB::table('attribute_options')
            ->where('attribute_id', $attribute->id)
            ->where('admin_name', $optionLabel)
            ->first();

        // create if not exists
        if (!$option) {

            $optionId = DB::table('attribute_options')->insertGetId([
                'attribute_id' => $attribute->id,
                'admin_name' => $optionLabel,
                'sort_order' => 0,
            ]);

            DB::table('attribute_option_translations')->insert([
                [
                    'attribute_option_id' => $optionId,
                    'locale' => 'en',
                    'label' => $optionLabel,
                ],
                [
                    'attribute_option_id' => $optionId,
                    'locale' => 'ar',
                    'label' => $optionLabel,
                ]
            ]);

            return $optionId;
        }

        return $option->id;
    }
    private function cleanNumber($value)
    {
        if (!$value) {
            return 0;
        }

        return (float) str_replace(',', '', trim($value));
    }
}
