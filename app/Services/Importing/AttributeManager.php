<?php

namespace App\Services\Importing;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Webkul\Attribute\Models\Attribute as ProductAttribute;

class AttributeManager
{
    private LoggerInterface $logger;
    private array $attributeCache = [];

    public function __construct()
    {
        $this->logger = Log::build([
            'driver' => 'single',
            'path'   => storage_path('logs/attributes.log'),
        ]);
    }

    public function syncFromConfig(): void
    {
        $config = $this->getConfig();
        $familyId = 1;

        $groupNames = collect($config)
            ->filter(fn ($v) => is_array($v))
            ->pluck('group')
            ->filter()
            ->unique()
            ->map(fn ($name) => ['name' => $name])
            ->values()
            ->all();

        $groups = $this->syncGroups($familyId, $groupNames);

        foreach ($config as $key => $attributeConfig) {
            if (! is_array($attributeConfig)) {
                continue;
            }

            $code = is_string($key) ? $key : null;

            if (! $code) {
                continue;
            }

            $groupName = (string) ($attributeConfig['group'] ?? 'General');
            $groupId = $groups[$this->normalizeGroupKey($groupName)] ?? null;
            $attribute = $this->upsertAttribute($code, $attributeConfig);

            if ($groupId) {
                $this->attachToGroup($attribute->id, $groupId, (int) ($attributeConfig['position'] ?? 0));
            }

            if (! empty($attributeConfig['options']) && is_array($attributeConfig['options'])) {
                $this->syncOptions($attribute, $attributeConfig['options']);
            }

            $this->attributeCache[$code] = $attribute->fresh();
        }
    }

    public function getAttribute(string $code): ?ProductAttribute
    {
        if (isset($this->attributeCache[$code])) {
            return $this->attributeCache[$code];
        }

        $attribute = ProductAttribute::query()->where('code', $code)->first();

        if ($attribute) {
            $this->attributeCache[$code] = $attribute;
        }

        return $attribute;
    }

    public function getAttributeConfig(string $code): array
    {
        $config = $this->getConfig();

        return is_array($config[$code] ?? null) ? $config[$code] : [];
    }

    public function getConfigurableAttributeConfigs(): array
    {
        $result = [];

        foreach ($this->getConfig() as $key => $config) {
            if (! is_array($config)) {
                continue;
            }

            $code = is_string($key) ? $key : null;

            if (! $code || ! (bool) ($config['use_to_create_configurable_product'] ?? $config['configurable'] ?? false)) {
                continue;
            }

            $config['attribute_code'] = $code;
            $result[] = $config;
        }

        return $result;
    }

    public function getConfig(): array
    {
        return config('product_attributes', []);
    }

    private function syncGroups(int $familyId, array $groups): array
    {
        $result = [];

        foreach ($groups as $index => $groupConfig) {
            $config = is_array($groupConfig) ? $groupConfig : ['name' => (string) $groupConfig];
            $name = trim((string) ($config['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $code = trim((string) ($config['code'] ?? Str::snake($name)));
            $column = (int) ($config['column'] ?? 1);
            $position = (int) ($config['position'] ?? ($index + 1));
            $key = $this->normalizeGroupKey($name);

            $group = DB::table('attribute_groups')
                ->where('attribute_family_id', $familyId)
                ->where(function ($query) use ($name, $code) {
                    $query->where('name', $name)->orWhere('code', $code);
                })
                ->first();

            if (! $group) {
                $result[$key] = DB::table('attribute_groups')->insertGetId([
                    'attribute_family_id' => $familyId,
                    'name'                => $name,
                    'code'                => $code,
                    'column'              => $column,
                    'position'            => $position,
                    'is_user_defined'     => 0,
                ]);

                $this->log("Created group: {$name}");

                continue;
            }

            DB::table('attribute_groups')->where('id', $group->id)->update([
                'name'     => $name,
                'code'     => $code,
                'column'   => $column,
                'position' => $position,
            ]);

            $result[$key] = (int) $group->id;
        }

        return $result;
    }

    private function upsertAttribute(string $code, array $config): ProductAttribute
    {
        $attribute = ProductAttribute::query()->where('code', $code)->first();
        $payload = $this->buildAttributePayload($code, $config);

        if (! $attribute) {
            $attribute = ProductAttribute::query()->create($payload);
            $this->log("Created attribute: {$code}");
        } else {
            $frontendChanged = (int) $attribute->is_visible_on_front !== (int) $payload['is_visible_on_front'];
            $attribute->fill($payload);

            if ($attribute->isDirty()) {
                $attribute->save();
                $this->log("Updated attribute: {$code}");
            }

            if ($frontendChanged) {
                $this->log("Updated frontend visibility: {$code}");
            }
        }

        $this->syncTranslations($attribute->id, $payload['admin_name'], $config['translations'] ?? []);

        return $attribute;
    }

    private function buildAttributePayload(string $code, array $config): array
    {
        $isFilterable = (bool) ($config['use_in_layered_navigation'] ?? false);

        if (array_key_exists('filterable', $config)) {
            $isFilterable = (bool) $config['filterable'];
        }

        $payload = [
            'code'                => $code,
            'admin_name'          => (string) ($config['admin_name'] ?? Str::headline($code)),
            'type'                => (string) ($config['type'] ?? 'text'),
            'validation'          => $config['validation'] ?? null,
            'position'            => (int) ($config['position'] ?? 0),
            'is_required'         => (bool) ($config['is_required'] ?? $config['required'] ?? false),
            'value_per_locale'    => (bool) ($config['value_per_locale'] ?? false),
            'value_per_channel'   => (bool) ($config['value_per_channel'] ?? false),
            'is_configurable'     => (bool) ($config['use_to_create_configurable_product'] ?? $config['configurable'] ?? false),
            'is_visible_on_front' => (bool) ($config['visible_on_product_view_page'] ?? $config['visible_on_front'] ?? true),
            'is_comparable'       => (bool) ($config['comparable'] ?? false),
            'is_filterable'       => $isFilterable,
            'is_user_defined'     => (bool) ($config['is_user_defined'] ?? true),
        ];

        if (Schema::hasColumn('attributes', 'is_searchable')) {
            $payload['is_searchable'] = (bool) ($config['searchable'] ?? false);
        }

        return $payload;
    }

    private function syncTranslations(int $attributeId, string $adminName, array $translations): void
    {
        $translations = array_filter(array_merge([
            config('product_attributes.admin_locale', 'en') => $adminName,
        ], $translations), fn ($value) => is_string($value) && trim($value) !== '');

        foreach ($translations as $locale => $label) {
            $exists = DB::table('attribute_translations')
                ->where('attribute_id', $attributeId)
                ->where('locale', $locale)
                ->exists();

            DB::table('attribute_translations')->updateOrInsert(
                ['attribute_id' => $attributeId, 'locale' => $locale],
                ['name' => $label]
            );

            if (! $exists) {
                $this->log("Created translation: {$label}");
            }
        }
    }

    private function attachToGroup(int $attributeId, int $groupId, int $position): void
    {
        DB::table('attribute_group_mappings')->updateOrInsert(
            ['attribute_id' => $attributeId, 'attribute_group_id' => $groupId],
            ['position' => $position]
        );
    }

    private function normalizeGroupKey(string $name): string
    {
        return Str::of($name)->lower()->replace(' ', '_')->value();
    }

    private function syncOptions(ProductAttribute $attribute, array $options): void
    {
        foreach ($options as $label) {
            $label = trim((string) $label);

            if ($label === '') {
                continue;
            }

            $exists = DB::table('attribute_options')
                ->where('attribute_id', $attribute->id)
                ->whereRaw('LOWER(admin_name) = ?', [mb_strtolower($label)])
                ->exists();

            if ($exists) {
                continue;
            }

            $sortOrder = (int) DB::table('attribute_options')
                ->where('attribute_id', $attribute->id)
                ->max('sort_order');

            $optionId = DB::table('attribute_options')->insertGetId([
                'attribute_id' => $attribute->id,
                'admin_name'   => $label,
                'sort_order'   => $sortOrder + 1,
            ]);

            foreach (['en', 'ar'] as $locale) {
                DB::table('attribute_option_translations')->updateOrInsert(
                    ['attribute_option_id' => $optionId, 'locale' => $locale],
                    ['label' => $label]
                );
            }

            $this->log("Created option: {$label} for {$attribute->code}");
        }
    }

    private function log(string $message): void
    {
        $this->logger->info($message);
    }
}
