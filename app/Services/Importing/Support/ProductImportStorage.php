<?php

namespace App\Services\Importing\Support;

use App\Services\Importing\AttributeManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
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
            if (count($data) !== count($header)) {
                continue;
            }

            $row = array_combine($header, array_map('trim', $data));

            // Normalize path separators in path-related columns
            foreach (['image_1', 'image_path', 'parent_sku', 'sku'] as $col) {
                if (isset($row[$col])) {
                    $row[$col] = str_replace('\\', '/', $row[$col]);
                }
            }

            $rows[] = $row;
        }

        fclose($handle);

        return $rows;
    }

    public function saveCoreAttributes(int $productId, array $row, string $sku, bool $isParent): void
    {
        $nameEn = $row['name_en'] ?? '';
        $nameAr = $row['name_ar'] ?? '';
        $descEnLong = $row['description_en_long'] ?? '';
        $descArLong = $row['description_ar_long'] ?? '';
        $descEnShort = $row['description_en_short'] ?? '';
        $descArShort = $row['description_ar_short'] ?? '';

        $this->saveAttributeValue($productId, 'sku', $sku);
        $this->saveAttributeValue($productId, 'name', $nameEn, 'en');
        $this->saveAttributeValue($productId, 'description', $descEnLong, 'en');
        $this->saveAttributeValue($productId, 'short_description', $descEnShort, 'en');
        $this->saveAttributeValue($productId, 'url_key', Str::slug($sku), 'en');
        $this->saveAttributeValue($productId, 'name', $nameAr, 'ar');
        $this->saveAttributeValue($productId, 'description', $descArLong, 'ar');
        $this->saveAttributeValue($productId, 'short_description', $descArShort, 'ar');
        $this->saveAttributeValue($productId, 'url_key', Str::slug($sku.'-ar'), 'ar');
        $this->saveAttributeValue($productId, 'description', $descEnLong);
        $this->saveAttributeValue($productId, 'short_description', $descEnShort);
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

    public function attachImagesFromFolder(int $productId, string $sku, ?string $parentSku = null, ?string $color = null, ?string $imageBasePath = null): int
    {
        $basePath = PathHelper::normalize($imageBasePath ?? storage_path('app/import/images'));

        // Normalize color/sku for cross-platform path matching
        $normalizedColor = $color ? PathHelper::sanitizeFilename($color) : null;
        $normalizedSku = PathHelper::sanitizeFilename($sku);
        $normalizedParentSku = $parentSku ? PathHelper::sanitizeFilename($parentSku) : null;

        $folder = null;

        // Try structure: {ParentSKU}/{Color}/
        if ($normalizedParentSku && $normalizedColor) {
            $candidate = PathHelper::join($basePath, $normalizedParentSku, $normalizedColor);

            if (is_dir($candidate)) {
                $folder = $candidate;
            }
        }

        // Try structure: {SKU}/{Color}/
        if (! $folder && $normalizedColor) {
            $candidate = PathHelper::join($basePath, $normalizedSku, $normalizedColor);

            if (is_dir($candidate)) {
                $folder = $candidate;
            }
        }

        // Fall back to: {SKU}/ (flat images directly in folder)
        if (! $folder) {
            $candidate = PathHelper::join($basePath, $normalizedSku);

            if (is_dir($candidate) && $this->hasImageFiles($candidate)) {
                $folder = $candidate;
            }
        }

        if (! $folder) {
            return 0;
        }

        $count = 0;
        $position = DB::table('product_images')->where('product_id', $productId)->max('position') ?? -1;
        $position++;

        $iterator = new \DirectoryIterator($folder);

        foreach ($iterator as $fileInfo) {
            if ($fileInfo->isDot() || ! $fileInfo->isFile()) {
                continue;
            }

            $filename = $fileInfo->getFilename();

            // Case-insensitive image extension check
            if (! PathHelper::isImageFile($filename)) {
                continue;
            }

            $fullPath = PathHelper::normalize($fileInfo->getPathname());

            // Sanitize filename for storage (preserve Unicode/Arabic)
            $safeFilename = PathHelper::sanitizeFilename($filename);
            $storagePath = "product/{$normalizedSku}/{$safeFilename}";

            try {
                Storage::disk('public')->put($storagePath, file_get_contents($fullPath));

                DB::table('product_images')->updateOrInsert(
                    ['product_id' => $productId, 'path' => $storagePath],
                    [
                        'product_id' => $productId,
                        'type'       => 'image',
                        'path'       => $storagePath,
                        'position'   => $position++,
                    ]
                );

                $count++;
            } catch (\Throwable $e) {
                // Log error but don't stop the batch
                \Illuminate\Support\Facades\Log::warning("Image import failed for {$sku}/{$filename}: {$e->getMessage()}");
            }
        }

        return $count;
    }

    public function copyFirstVariantImageToParent(int $parentId, int $variantId): void
    {
        $firstImage = DB::table('product_images')->where('product_id', $variantId)->first();

        if (! $firstImage) {
            return;
        }

        DB::table('product_images')->updateOrInsert(
            [
                'product_id' => $parentId,
                'path'       => $firstImage->path,
            ],
            [
                'type'     => 'image',
                'position' => 0,
            ]
        );
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

    private function hasImageFiles(string $directory): bool
    {
        $iterator = new \DirectoryIterator($directory);

        foreach ($iterator as $item) {
            if ($item->isDot()) {
                continue;
            }

            if ($item->isFile() && PathHelper::isImageFile($item->getFilename())) {
                return true;
            }
        }

        return false;
    }
}
