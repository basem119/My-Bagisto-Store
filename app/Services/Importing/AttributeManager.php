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
        $familyId = (int) ($config['attribute_family_id'] ?? 1);
        $groups = $this->syncGroups($familyId, $config['groups'] ?? []);

        foreach ($config['attributes'] ?? [] as $key => $attributeConfig) {
            if (! is_array($attributeConfig)) {
                continue;
            }

            $code = $attributeConfig['attribute_code'] ?? (is_string($key) ? $key : null);

            if (! $code) {
                continue;
            }

            $groupName = (string) ($attributeConfig['group'] ?? 'General');
            $groupId = $groups[$this->normalizeGroupKey($groupName)] ?? null;
            $attribute = $this->upsertAttribute($code, $attributeConfig);

            if ($groupId) {
                $this->attachToGroup($attribute->id, $groupId, (int) ($attributeConfig['position'] ?? 0));
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
        $attributes = $this->getConfig()['attributes'] ?? [];

        return is_array($attributes[$code] ?? null) ? $attributes[$code] : [];
    }

    public function getConfigurableAttributeConfigs(): array
    {
        $result = [];

        foreach ($this->getConfig()['attributes'] ?? [] as $key => $config) {
            if (! is_array($config)) {
                continue;
            }

            $code = $config['attribute_code'] ?? (is_string($key) ? $key : null);

            if (! $code || ! (bool) ($config['use_to_create_configurable_product'] ?? false)) {
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
            'validation'          => $config['validation'] ?: null,
            'position'            => (int) ($config['position'] ?? 0),
            'is_required'         => (bool) ($config['is_required'] ?? false),
            'value_per_locale'    => (bool) ($config['value_per_locale'] ?? false),
            'value_per_channel'   => (bool) ($config['value_per_channel'] ?? false),
            'is_configurable'     => (bool) ($config['use_to_create_configurable_product'] ?? false),
            'is_visible_on_front' => (bool) ($config['visible_on_product_view_page'] ?? true),
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

    private function log(string $message): void
    {
        $this->logger->info($message);
    }
}
