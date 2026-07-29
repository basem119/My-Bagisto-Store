<?php

namespace App\Services\Importing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;
use Webkul\Attribute\Models\Attribute as ProductAttribute;

class OptionManager
{
    private LoggerInterface $logger;

    public function __construct(private AttributeManager $attributeManager)
    {
        $this->logger = Log::build([
            'driver' => 'single',
            'path'   => storage_path('logs/attributes.log'),
        ]);
    }

    /**
     * Resolve a raw CSV value to attribute option ids.
     */
    public function resolveValue(ProductAttribute $attribute, mixed $rawValue): int|string|null
    {
        $labels = $this->splitValues((string) $rawValue, $this->getDelimiter($attribute->code));

        if ($labels === []) {
            return null;
        }

        $ids = [];

        foreach ($labels as $label) {
            $ids[] = $this->findOrCreateOptionId($attribute, $label);
        }

        $ids = array_values(array_unique(array_filter($ids)));

        if ($ids === []) {
            return null;
        }

        return $attribute->type === 'select'
            ? $ids[0]
            : implode(',', $ids);
    }

    /**
     * @return array<int, string>
     */
    private function splitValues(string $rawValue, string $delimiter): array
    {
        if (trim($rawValue) === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode($delimiter, $rawValue))));
    }

    private function findOrCreateOptionId(ProductAttribute $attribute, string $label): ?int
    {
        $existing = DB::table('attribute_options')
            ->where('attribute_id', $attribute->id)
            ->whereRaw('LOWER(admin_name) = ?', [mb_strtolower($label)])
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $sortOrder = (int) DB::table('attribute_options')
            ->where('attribute_id', $attribute->id)
            ->max('sort_order');

        $optionId = DB::table('attribute_options')->insertGetId([
            'attribute_id' => $attribute->id,
            'admin_name'   => $label,
            'sort_order'   => $sortOrder + 1,
        ]);

        foreach ($this->getLocales() as $locale) {
            DB::table('attribute_option_translations')->updateOrInsert(
                [
                    'attribute_option_id' => $optionId,
                    'locale'              => $locale,
                ],
                [
                    'label' => $label,
                ]
            );
        }

        $this->logger->info("Created option: {$label}");

        return (int) $optionId;
    }

    private function getDelimiter(string $attributeCode): string
    {
        $config = $this->attributeManager->getAttributeConfig($attributeCode);

        return (string) ($config['option_delimiter'] ?? ',');
    }

    /**
     * @return array<int, string>
     */
    private function getLocales(): array
    {
        $fallback = ['en', 'ar'];

        $configuredLocales = array_keys((array) config('product_attributes.locales', []));

        $locales = array_values(array_unique(array_merge($fallback, $configuredLocales)));

        return array_filter($locales, fn ($value) => is_string($value) && trim($value) !== '');
    }
}
