<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ColorAttributeSeeder extends Seeder
{
    public function run(): void
    {
        // Set color attribute to use color swatch
        DB::table('attributes')
            ->where('code', 'color')
            ->update(['swatch_type' => 'color']);

        $attributeId = DB::table('attributes')
            ->where('code', 'color')
            ->value('id');

        if (! $attributeId) {
            $this->command->error('Color attribute not found!');
            return;
        }

        $colors = [
            ['name' => 'Green',       'hex' => '#008000', 'ar' => 'أخضر'],
            ['name' => 'Mint Green',   'hex' => '#98FF98', 'ar' => 'أخضر نعناعي'],
            ['name' => 'Navy',         'hex' => '#000080', 'ar' => 'كحلي'],
            ['name' => 'Pink',         'hex' => '#FFC0CB', 'ar' => 'وردي'],
            ['name' => 'Turquoise',    'hex' => '#40E0D0', 'ar' => 'تركواز'],
            ['name' => 'Purple',       'hex' => '#800080', 'ar' => 'بنفسجي'],
            ['name' => 'Black',        'hex' => '#000000', 'ar' => 'أسود'],
            ['name' => 'Blue',         'hex' => '#0000FF', 'ar' => 'أزرق'],
            ['name' => 'Ice Blue',     'hex' => '#D6F0F5', 'ar' => 'أزرق جليدي'],
            ['name' => 'Lavender',     'hex' => '#E6E6FA', 'ar' => 'لافندر'],
            ['name' => 'Pink Mint',    'hex' => '#CCE0B2', 'ar' => 'وردي نعناعي'],
            ['name' => 'Mint',         'hex' => '#BDFCC9', 'ar' => 'نعناعي'],
            ['name' => 'Sky Blue',     'hex' => '#87CEEB', 'ar' => 'أزرق سماوي'],
            ['name' => 'Black Grey',   'hex' => '#404040', 'ar' => 'أسود رمادي'],
            ['name' => 'Navy Grey',    'hex' => '#404080', 'ar' => 'كحلي رمادي'],
            ['name' => 'Black Brown',  'hex' => '#322110', 'ar' => 'أسود بني'],
            ['name' => 'Navy Green',   'hex' => '#004040', 'ar' => 'كحلي أخضر'],
            ['name' => 'Navy Pink',    'hex' => '#8060A6', 'ar' => 'كحلي وردي'],
            ['name' => 'Dark Green',   'hex' => '#006400', 'ar' => 'أخضر داكن'],
            ['name' => 'Beige Yellow', 'hex' => '#FAFA6E', 'ar' => 'بيج أصفر'],
            ['name' => 'Pink Yellow',  'hex' => '#FFE066', 'ar' => 'وردي أصفر'],
            ['name' => 'Mauve',        'hex' => '#E0B0FF', 'ar' => 'موف'],
            ['name' => 'Navy Tan',     'hex' => '#695A86', 'ar' => 'كحلي بيج'],
            ['name' => 'Royal Blue',   'hex' => '#4169E1', 'ar' => 'أزرق ملكي'],
            ['name' => 'Navy Red',     'hex' => '#800040', 'ar' => 'كحلي أحمر'],
            ['name' => 'Red',          'hex' => '#FF0000', 'ar' => 'أحمر'],
            ['name' => 'Olive Green',  'hex' => '#808000', 'ar' => 'أخضر زيتوني'],
            ['name' => 'Grey',         'hex' => '#808080', 'ar' => 'رمادي'],
            ['name' => 'Beige',        'hex' => '#F5F5DC', 'ar' => 'بيج'],
            ['name' => 'Grey Brown',   'hex' => '#726150', 'ar' => 'رمادي بني'],
            ['name' => 'Pink Zebra',   'hex' => '#FFB6C1', 'ar' => 'وردي زيبرا'],
            ['name' => 'Purple Pink',  'hex' => '#CC66CC', 'ar' => 'بنفسجي وردي'],
            ['name' => 'Black Green',  'hex' => '#1A3A1A', 'ar' => 'أسود أخضر'],
            ['name' => 'Black Pink',   'hex' => '#3D1A2B', 'ar' => 'أسود وردي'],
            ['name' => 'Grey Black',   'hex' => '#404040', 'ar' => 'رمادي أسود'],
            ['name' => 'White',        'hex' => '#FFFFFF', 'ar' => 'أبيض'],
            ['name' => 'Yellow',       'hex' => '#FFD700', 'ar' => 'أصفر'],
            ['name' => 'Fuchsia',      'hex' => '#FF00FF', 'ar' => 'فوشيا'],
            ['name' => 'Brown',        'hex' => '#8B4513', 'ar' => 'بني'],
        ];

        $created = 0;
        $updated = 0;

        foreach ($colors as $index => $color) {
            $existing = DB::table('attribute_options')
                ->where('attribute_id', $attributeId)
                ->whereRaw('LOWER(admin_name) = ?', [mb_strtolower($color['name'])])
                ->first();

            if ($existing) {
                DB::table('attribute_options')
                    ->where('id', $existing->id)
                    ->update(['swatch_value' => $color['hex']]);

                $optionId = $existing->id;
                $updated++;
            } else {
                $maxSort = (int) DB::table('attribute_options')
                    ->where('attribute_id', $attributeId)
                    ->max('sort_order');

                $optionId = DB::table('attribute_options')->insertGetId([
                    'attribute_id' => $attributeId,
                    'admin_name'   => $color['name'],
                    'sort_order'   => $maxSort + 1,
                    'swatch_value' => $color['hex'],
                ]);
                $created++;
            }

            // English translation
            DB::table('attribute_option_translations')->updateOrInsert(
                ['attribute_option_id' => $optionId, 'locale' => 'en'],
                ['label' => $color['name']]
            );

            // Arabic translation
            DB::table('attribute_option_translations')->updateOrInsert(
                ['attribute_option_id' => $optionId, 'locale' => 'ar'],
                ['label' => $color['ar']]
            );
        }

        $this->command->info("Color seeder: {$created} created, {$updated} updated (total " . count($colors) . ' colors).');
    }
}
