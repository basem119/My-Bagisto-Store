<?php

namespace App\Services\Importing\Support;

use App\Services\Importing\AttributeManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Webkul\Attribute\Models\Attribute as ProductAttribute;

class ProductImportStorage
{
    public function __construct(private AttributeManager $attributeManager)
    {
    }

    public function getProductId(mixed $product): int
    {
        return (int) data_get($product, 'id', 0);
    }

    public function readCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        $header = fgetcsv($handle);

        $header = array_map(function ($value) {
            $value = preg_replace('/^\xEF\xBB\xBF/', '', $value);

            return trim(strtolower((string) $value));
        }, $header ?: []);

        while (($data = fgetcsv($handle)) !== false) {
            $rows[] = array_combine($header, array_map('trim', $data));
        }

        fclose($handle);

        return $rows;
    }

    public function saveCoreAttributes(int $productId, array $row, string $sku, bool $isParent): void
    {
        $nameEn = $row['name_en'] ?? '';
        $descriptionEn = $row['description_en'] ?? '';
        $nameAr = $row['name_ar'] ?? '';
        $descriptionAr = $row['description_ar'] ?? '';

        $this->saveAttributeValue($productId, 'sku', $sku);
        $this->saveAttributeValue($productId, 'name', $nameEn, 'en');
        $this->saveAttributeValue($productId, 'description', $descriptionEn, 'en');
        $this->saveAttributeValue($productId, 'short_description', $descriptionEn, 'en');
        $this->saveAttributeValue($productId, 'url_key', Str::slug($sku), 'en');
        $this->saveAttributeValue($productId, 'name', $nameAr, 'ar');
        $this->saveAttributeValue($productId, 'description', $descriptionAr, 'ar');
        $this->saveAttributeValue($productId, 'short_description', $descriptionAr, 'ar');
        $this->saveAttributeValue($productId, 'url_key', Str::slug($sku.'-ar'), 'ar');
        $this->saveAttributeValue($productId, 'description', $descriptionEn);
        $this->saveAttributeValue($productId, 'short_description', $descriptionEn);
        $this->saveAttributeValue($productId, 'status', 1);
        $this->saveAttributeValue($productId, 'visible_individually', $isParent ? 1 : 0);

        if ($isParent) {
            return;
        }

        $this->saveAttributeValue($productId, 'weight', 40);
        $this->saveAttributeValue($productId, 'price', $this->cleanNumber($row['price_before_discount'] ?? '0'));
        $this->saveAttributeValue($productId, 'special_price', $this->cleanNumber($row['price'] ?? '0'));
    }

    public function attachCategory(int $productId, ?string $categoryName): void
    {
        if (! $categoryName) {
            return;
        }

        $category = DB::table('category_translations')->where('name', $categoryName)->first();

        if (! $category) {
            return;
        }

        DB::table('product_categories')->updateOrInsert(
            ['product_id' => $productId, 'category_id' => $category->category_id],
            ['product_id' => $productId, 'category_id' => $category->category_id]
        );
    }

    public function attachInventory(int $productId, float $qty): void
    {
        DB::table('product_inventories')->updateOrInsert(
            ['product_id' => $productId, 'inventory_source_id' => 1],
            ['qty' => $qty, 'vendor_id' => 0]
        );
    }

    public function attachImagesFromFolder(int $productId, string $sku, ?string $parentSku = null, ?string $color = null): void
    {
        $folder = null;

        // Try new structure: {ParentSKU}/{Color}/
        if ($parentSku && $color) {
            $candidate = storage_path("app/import/images/{$parentSku}/{$color}");

            if (is_dir($candidate)) {
                $folder = $candidate;
            }
        }

        // Fall back to old structure: {SKU}/
        if (! $folder) {
            $candidate = storage_path("app/import/images/{$sku}");

            if (is_dir($candidate)) {
                $folder = $candidate;
            }
        }

        if (! $folder) {
            return;
        }

        $files = scandir($folder) ?: [];
        $position = 0;

        foreach ($files as $file) {
            if (in_array($file, ['.', '..'], true)) {
                continue;
            }

            $fullPath = $folder.'/'.$file;

            if (! is_file($fullPath)) {
                continue;
            }

            $newPath = "product/{$sku}/{$file}";

            Storage::disk('public')->put($newPath, file_get_contents($fullPath));

            DB::table('product_images')->insert([
                'product_id' => $productId,
                'type'       => 'image',
                'path'       => $newPath,
                'position'   => $position++,
            ]);
        }
    }

    public function copyFirstVariantImageToParent(int $parentId, int $variantId): void
    {
        $firstImage = DB::table('product_images')->where('product_id', $variantId)->first();

        if (! $firstImage) {
            return;
        }

        DB::table('product_images')->insert([
            'type'       => 'image',
            'path'       => $firstImage->path,
            'product_id' => $parentId,
            'position'   => 0,
        ]);
    }

    public function syncRelatedProductsByCategory(int $productId): void
    {
        $categoryIds = DB::table('product_categories')->where('product_id', $productId)->pluck('category_id');

        DB::table('product_relations')->where('parent_id', $productId)->delete();

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

    public function saveAttributeValue(int $productId, string $attributeCode, mixed $value, ?string $locale = null): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $attribute = $this->attributeManager->getAttribute($attributeCode)
            ?? ProductAttribute::query()->where('code', $attributeCode)->first();

        if (! $attribute) {
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
            case 'select':
            case 'integer':
                $data['integer_value'] = (int) $value;
                break;
            case 'price':
            case 'decimal':
                $data['float_value'] = (float) $value;
                break;
            case 'boolean':
                $data['boolean_value'] = (bool) $value;
                break;
            default:
                $data['text_value'] = (string) $value;
                break;
        }

        DB::table('product_attribute_values')->updateOrInsert(
            [
                'product_id'   => $productId,
                'attribute_id' => $attribute->id,
                'locale'       => $locale,
                'channel'      => 'default',
            ],
            $data
        );
    }

    private function cleanNumber(string $value): float
    {
        if (trim($value) === '') {
            return 0.0;
        }

        return (float) str_replace(',', '', trim($value));
    }
}
