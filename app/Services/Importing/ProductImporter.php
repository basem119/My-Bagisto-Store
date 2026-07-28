<?php

namespace App\Services\Importing;

use App\Services\Importing\Support\ImportLogWriter;
use App\Services\Importing\Support\ImportProgressTracker;
use App\Services\Importing\Support\ProductImportStorage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Webkul\Attribute\Models\Attribute as ProductAttribute;
use Webkul\Product\Models\Product;
use Webkul\Product\Repositories\ProductRepository;

class ProductImporter
{
    private $logger;

    private ?ImportProgressTracker $tracker = null;

    private ?ImportLogWriter $logWriter = null;

    private ?string $imageBasePath = null;

    public function __construct(
        private ProductRepository $productRepository,
        private AttributeManager $attributeManager,
        private OptionManager $optionManager,
        private ProductImportStorage $storage
    ) {
        $this->logger = Log::build([
            'driver' => 'single',
            'path'   => storage_path('logs/import.log'),
        ]);
    }

    public function setTracker(ImportProgressTracker $tracker): self
    {
        $this->tracker = $tracker;

        return $this;
    }

    public function setLogWriter(ImportLogWriter $logWriter): self
    {
        $this->logWriter = $logWriter;

        return $this;
    }

    public function setImageBasePath(string $path): self
    {
        $this->imageBasePath = rtrim($path, '/\\');

        return $this;
    }

    public function import(string $path): array
    {
        if (! file_exists($path)) {
            throw new \RuntimeException('CSV not found');
        }

        $this->attributeManager->syncFromConfig();

        $rows = $this->storage->readCsv($path);
        $groupedRows = collect($rows)->groupBy('parent_sku');

        if ($this->tracker) {
            $this->tracker->start(
                $groupedRows->count(),
                $this->logWriter?->getLogFile() ?? 'import.log'
            );
        }

        $this->log('info', "Starting import: {$groupedRows->count()} parent groups, ".count($rows).' total rows');

        $parentIds = [];

        DB::transaction(function () use ($groupedRows, &$parentIds) {
            foreach ($groupedRows as $parentSku => $items) {
                try {
                    $this->processParentGroup((string) $parentSku, $items, $parentIds);
                } catch (\Throwable $e) {
                    $this->log('error', "Error processing '{$parentSku}': {$e->getMessage()}");
                    $this->logWriter?->error((string) $parentSku, $e->getMessage());
                    $this->tracker?->incrementErrors();
                }
            }

            foreach ($parentIds as $parentId) {
                $this->storage->syncRelatedProductsByCategory($parentId);
            }
        });

        return $parentIds;
    }

    private function processParentGroup(string $parentSku, $items, array &$parentIds): void
    {
        $existing = Product::where('sku', $parentSku)->first();
        $firstRow = $this->itemToArray($items->first());

        if ($existing) {
            $parentId = $existing->id;

            $this->storage->saveCoreAttributes($parentId, $firstRow, $parentSku, true);
            $this->applyConfiguredAttributes($parentId, $firstRow, 'parent');
            $this->storage->attachCategory($parentId, $firstRow['category'] ?? null);

            $this->syncConfigurableAttributes($parentId);

            $this->log('info', "Updated: parent SKU '{$parentSku}' (id={$parentId})");
            $this->logWriter?->updated($parentSku);

            $parentIds[] = $parentId;

            $firstVariantId = null;

            foreach ($items as $item) {
                $row = $this->itemToArray($item);
                $variantSku = trim($row['sku'] ?? '');

                if (! $variantSku) {
                    continue;
                }

                $existingVariant = Product::where('sku', $variantSku)->first();

                if ($existingVariant) {
                    $this->updateSimpleProduct($existingVariant->id, $row);
                    $this->logWriter?->updated($variantSku, 'simple');

                    if (! $firstVariantId) {
                        $firstVariantId = $existingVariant->id;
                    }
                } else {
                    $variantId = $this->createSimpleProduct($parentId, $row);
                    $this->logWriter?->created($variantSku, 'simple');
                    $this->tracker?->incrementCreated();

                    if (! $firstVariantId) {
                        $firstVariantId = $variantId;
                    }
                }
            }

            if ($firstVariantId) {
                $this->storage->copyFirstVariantImageToParent($parentId, $firstVariantId);
            }

            $this->tracker?->incrementUpdated();
        } else {
            $parent = $this->createConfigurableProduct($parentSku, $firstRow);
            $parentId = $this->storage->getProductId($parent);

            if (! $parentId) {
                $this->tracker?->incrementSkipped();

                return;
            }

            $parentIds[] = $parentId;

            $this->syncConfigurableAttributes($parentId);

            $this->log('info', "Created: parent SKU '{$parentSku}' (id={$parentId})");
            $this->logWriter?->created($parentSku);

            $firstVariantId = null;

            foreach ($items as $item) {
                $row = $this->itemToArray($item);
                $variantSku = trim($row['sku'] ?? '');

                if ($variantSku && Product::where('sku', $variantSku)->exists()) {
                    $this->log('info', "Skipped: variant SKU '{$variantSku}' already exists");
                    $this->logWriter?->skipped($variantSku, 'SKU already exists');

                    continue;
                }

                $variant = $this->createSimpleProduct($parentId, $row);
                $this->logWriter?->created($variantSku, 'simple');

                if (! $firstVariantId) {
                    $firstVariantId = $variant;
                }
            }

            if ($firstVariantId) {
                $this->storage->copyFirstVariantImageToParent($parentId, $firstVariantId);
            }

            $this->tracker?->incrementCreated();
        }
    }

    private function itemToArray(mixed $item): array
    {
        return is_array($item) ? $item : (method_exists($item, 'toArray') ? $item->toArray() : (array) $item);
    }

    private function createConfigurableProduct(string $sku, array $row): object
    {
        $product = $this->productRepository->create([
            'sku'                 => $sku,
            'type'                => 'configurable',
            'attribute_family_id' => (int) config('product_attributes.attribute_family_id', 1),
        ]);

        $productId = $this->storage->getProductId($product);

        $this->storage->saveCoreAttributes($productId, $row, $sku, true);
        $this->applyConfiguredAttributes($productId, $row, 'parent');
        $this->storage->attachCategory($productId, $row['category'] ?? null);

        return $product;
    }

    private function createSimpleProduct(int $parentId, array $row): int
    {
        $product = $this->productRepository->create([
            'sku'                 => $row['sku'],
            'type'                => 'simple',
            'attribute_family_id' => (int) config('product_attributes.attribute_family_id', 1),
            'parent_id'           => $parentId,
        ]);

        $productId = $this->storage->getProductId($product);

        $this->storage->saveCoreAttributes($productId, $row, $row['sku'], false);
        $this->applyColorAttribute($productId, $row);
        $this->applyConfiguredAttributes($productId, $row, 'variant');

        $this->storage->attachCategory($productId, $row['category'] ?? null);
        $this->storage->attachInventory($productId, (float) ($row['qty'] ?? 0));

        $imageCount = $this->storage->attachImagesFromFolder(
            $productId,
            $row['sku'],
            trim($row['parent_sku'] ?? ''),
            trim($row['color'] ?? ''),
            $this->imageBasePath
        );

        $this->tracker?->incrementImages($imageCount);

        return $productId;
    }

    private function updateSimpleProduct(int $productId, array $row): void
    {
        $this->storage->saveCoreAttributes($productId, $row, $row['sku'], false);
        $this->applyColorAttribute($productId, $row);
        $this->applyConfiguredAttributes($productId, $row, 'variant');

        $this->storage->attachCategory($productId, $row['category'] ?? null);
        $this->storage->attachInventory($productId, (float) ($row['qty'] ?? 0));
    }

    private function applyConfiguredAttributes(int $productId, array $row, string $target): void
    {
        foreach ($this->attributeManager->getConfig() as $code => $attributeConfig) {
            if (! is_array($attributeConfig)) {
                continue;
            }

            $scope = (string) ($attributeConfig['applies_to'] ?? 'both');

            if ($scope !== $target && $scope !== 'both') {
                continue;
            }

            $attribute = $this->attributeManager->getAttribute((string) $code);

            if (! $attribute) {
                continue;
            }

            if (! empty($attributeConfig['csv_columns']) && is_array($attributeConfig['csv_columns'])) {
                foreach ($attributeConfig['csv_columns'] as $locale => $column) {
                    $this->saveMappedAttributeValue($productId, $attribute, $row, (string) $column, (string) $locale);
                }

                continue;
            }

            $column = (string) ($attributeConfig['csv_column'] ?? $code);

            if ($column === '') {
                continue;
            }

            $this->saveMappedAttributeValue($productId, $attribute, $row, $column);
        }
    }

    private function saveMappedAttributeValue(
        int $productId,
        ProductAttribute $attribute,
        array $row,
        string $column,
        ?string $locale = null
    ): void {
        $rawValue = $row[$column] ?? null;

        if ($rawValue === null || trim((string) $rawValue) === '') {
            return;
        }

        if (in_array($attribute->type, ['select', 'multiselect'], true)) {
            $resolved = $this->optionManager->resolveValue($attribute, $rawValue);

            if ($resolved === null) {
                return;
            }

            $this->storage->saveAttributeValue($productId, $attribute->code, $resolved, $locale);

            return;
        }

        $this->storage->saveAttributeValue($productId, $attribute->code, $rawValue, $locale);
    }

    private function applyColorAttribute(int $productId, array $row): void
    {
        $colorValue = trim($row['color'] ?? '');

        if ($colorValue === '') {
            return;
        }

        $colorAttribute = $this->attributeManager->getAttribute('color');

        if (! $colorAttribute) {
            return;
        }

        $resolved = $this->optionManager->resolveValue($colorAttribute, $colorValue);

        if ($resolved !== null) {
            $this->storage->saveAttributeValue($productId, 'color', $resolved);
        }
    }

    private function syncConfigurableAttributes(int $parentId): void
    {
        $colorAttribute = $this->attributeManager->getAttribute('color');

        if ($colorAttribute) {
            DB::table('product_super_attributes')->updateOrInsert([
                'product_id'   => $parentId,
                'attribute_id' => $colorAttribute->id,
            ]);
        }

        foreach ($this->attributeManager->getConfigurableAttributeConfigs() as $config) {
            $code = (string) $config['attribute_code'];

            $attribute = $this->attributeManager->getAttribute($code);

            if (! $attribute) {
                continue;
            }

            DB::table('product_super_attributes')->updateOrInsert([
                'product_id'   => $parentId,
                'attribute_id' => $attribute->id,
            ]);
        }
    }

    private function log(string $level, string $message): void
    {
        $this->logger->{$level}($message);
        $this->logWriter?->{$level === 'error' ? 'error' : 'info'}($message);
    }
}
