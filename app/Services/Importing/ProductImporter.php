<?php

namespace App\Services\Importing;

use App\Services\Importing\Support\ProductImportStorage;
use Illuminate\Support\Facades\DB;
use Webkul\Attribute\Models\Attribute as ProductAttribute;
use Webkul\Product\Repositories\ProductRepository;

class ProductImporter
{
    public function __construct(
        private ProductRepository $productRepository,
        private AttributeManager $attributeManager,
        private OptionManager $optionManager,
        private ProductImportStorage $storage
    ) {
    }

    public function import(string $path): array
    {
        if (! file_exists($path)) {
            throw new \RuntimeException('CSV not found');
        }

        $this->attributeManager->syncFromConfig();

        $groupedRows = collect($this->storage->readCsv($path))->groupBy('parent_sku');

        $parentIds = [];

        DB::transaction(function () use ($groupedRows, &$parentIds) {
            foreach ($groupedRows as $parentSku => $items) {
                $parent = $this->createConfigurableProduct((string) $parentSku, $items->first());

                $parentId = $this->storage->getProductId($parent);

                if (! $parentId) {
                    continue;
                }

                $parentIds[] = $parentId;

                $this->syncConfigurableAttributes($parentId);

                $firstVariantId = null;

                foreach ($items as $item) {
                    $variant = $this->createSimpleProduct($parentId, $item);

                    if (! $firstVariantId) {
                        $firstVariantId = $variant;
                    }
                }

                if ($firstVariantId) {
                    $this->storage->copyFirstVariantImageToParent($parentId, $firstVariantId);
                }
            }

            foreach ($parentIds as $parentId) {
                $this->storage->syncRelatedProductsByCategory($parentId);
            }
        });

        return $parentIds;
    }

    private function createConfigurableProduct(string $sku, array $row): object
    {
        $product = $this->productRepository->create([
            'sku'               => $sku,
            'type'              => 'configurable',
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
        $this->applyConfiguredAttributes($productId, $row, 'variant');

        $this->storage->attachCategory($productId, $row['category'] ?? null);
        $this->storage->attachInventory($productId, (float) ($row['qty'] ?? 0));
        $this->storage->attachImagesFromFolder($productId, $row['sku']);

        return $productId;
    }

    private function applyConfiguredAttributes(int $productId, array $row, string $target): void
    {
        foreach ($this->attributeManager->getConfig()['attributes'] ?? [] as $code => $attributeConfig) {
            if (! is_array($attributeConfig)) {
                continue;
            }

            $scope = (string) ($attributeConfig['applies_to'] ?? 'variant');

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

            $column = (string) ($attributeConfig['csv_column'] ?? '');

            if ($column === '') {
                continue;
            }

            $this->saveMappedAttributeValue($productId, $attribute, $row, $column);
        }
    }

    /**
     * @param  array<string, string>  $row
     */
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

    private function syncConfigurableAttributes(int $parentId): void
    {
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

}
