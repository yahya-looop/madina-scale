<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Seed the application's default settings.
     */
    public function run(): void
    {
        $settings = [
            [
                'group' => 'general',
                'key' => 'general.site_name',
                'value' => 'Madina Scale',
                'type' => 'string',
                'description' => 'The public name of the application.',
                'sort_order' => 10,
            ],
            [
                'group' => 'general',
                'key' => 'general.site_description',
                'value' => 'Modern enterprise business management platform.',
                'type' => 'string',
                'description' => 'The default application description.',
                'sort_order' => 20,
            ],
            [
                'group' => 'general',
                'key' => 'general.contact_email',
                'value' => '',
                'type' => 'string',
                'description' => 'Primary business contact email.',
                'sort_order' => 30,
            ],
            [
                'group' => 'general',
                'key' => 'general.contact_phone',
                'value' => '',
                'type' => 'string',
                'description' => 'Primary business contact phone.',
                'sort_order' => 40,
            ],
            [
                'group' => 'general',
                'key' => 'general.support_channels',
                'value' => json_encode(
                    ['chat', 'email', 'phone'],
                    JSON_THROW_ON_ERROR,
                ),
                'type' => 'json',
                'description' => 'Enabled customer support channels.',
                'sort_order' => 50,
            ],

            [
                'group' => 'localization',
                'key' => 'localization.timezone',
                'value' => 'Asia/Dhaka',
                'type' => 'string',
                'description' => 'Default application timezone.',
                'sort_order' => 10,
            ],
            [
                'group' => 'localization',
                'key' => 'localization.locale',
                'value' => 'en',
                'type' => 'string',
                'description' => 'Default application locale.',
                'sort_order' => 20,
            ],
            [
                'group' => 'localization',
                'key' => 'localization.date_format',
                'value' => 'DD/MM/YYYY',
                'type' => 'string',
                'description' => 'Default date display format.',
                'sort_order' => 30,
            ],
            [
                'group' => 'localization',
                'key' => 'localization.time_format',
                'value' => '12-hour',
                'type' => 'string',
                'description' => 'Default time display format.',
                'sort_order' => 40,
            ],
            [
                'group' => 'localization',
                'key' => 'localization.week_starts_on',
                'value' => '6',
                'type' => 'integer',
                'description' => 'First day of the business week. 0 = Sunday, 6 = Saturday.',
                'sort_order' => 50,
            ],

            [
                'group' => 'currency',
                'key' => 'currency.default',
                'value' => 'BDT',
                'type' => 'string',
                'description' => 'Default application currency code.',
                'sort_order' => 10,
            ],
            [
                'group' => 'currency',
                'key' => 'currency.symbol',
                'value' => '৳',
                'type' => 'string',
                'description' => 'Default currency symbol.',
                'sort_order' => 20,
            ],
            [
                'group' => 'currency',
                'key' => 'currency.decimal_places',
                'value' => '2',
                'type' => 'integer',
                'description' => 'Number of decimal places used for monetary values.',
                'sort_order' => 30,
            ],
            [
                'group' => 'currency',
                'key' => 'currency.thousand_separator',
                'value' => ',',
                'type' => 'string',
                'description' => 'Thousands separator for monetary values.',
                'sort_order' => 40,
            ],
            [
                'group' => 'currency',
                'key' => 'currency.decimal_separator',
                'value' => '.',
                'type' => 'string',
                'description' => 'Decimal separator for monetary values.',
                'sort_order' => 50,
            ],

            [
                'group' => 'business',
                'key' => 'business.name',
                'value' => 'Madina Scale',
                'type' => 'string',
                'description' => 'Registered business display name.',
                'sort_order' => 10,
            ],
            [
                'group' => 'business',
                'key' => 'business.email',
                'value' => '',
                'type' => 'string',
                'description' => 'Primary business email address.',
                'sort_order' => 20,
            ],
            [
                'group' => 'business',
                'key' => 'business.phone',
                'value' => '',
                'type' => 'string',
                'description' => 'Primary business phone number.',
                'sort_order' => 30,
            ],
            [
                'group' => 'business',
                'key' => 'business.address',
                'value' => '',
                'type' => 'string',
                'description' => 'Primary business address.',
                'sort_order' => 40,
            ],
            [
                'group' => 'business',
                'key' => 'business.business_hours',
                'value' => json_encode(
                    [],
                    JSON_THROW_ON_ERROR,
                ),
                'type' => 'json',
                'description' => 'Configured business operating hours.',
                'sort_order' => 50,
            ],

            [
                'group' => 'system',
                'key' => 'system.maintenance_mode',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Enable application maintenance mode.',
                'sort_order' => 10,
            ],
            [
                'group' => 'system',
                'key' => 'system.items_per_page',
                'value' => '25',
                'type' => 'integer',
                'description' => 'Default number of records displayed per page.',
                'sort_order' => 20,
            ],
            [
                'group' => 'system',
                'key' => 'system.support_channels',
                'value' => json_encode(
                    ['chat', 'email', 'phone'],
                    JSON_THROW_ON_ERROR,
                ),
                'type' => 'json',
                'description' => 'Legacy system support channel configuration.',
                'sort_order' => 30,
            ],
        ];

        foreach ($settings as $data) {
            $setting = Setting::query()->firstOrNew([
                'key' => $data['key'],
            ]);

            if (! $setting->exists) {
                $setting->value = $data['value'];
            }

            $setting->group = $data['group'];
            $setting->type = $data['type'];
            $setting->description = $data['description'];
            $setting->is_active = true;
            $setting->sort_order = $data['sort_order'];

            $setting->save();
        }
    }
}
