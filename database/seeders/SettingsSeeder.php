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
            /*
            |--------------------------------------------------------------------------
            | General
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | Localization
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | Currency
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | Business
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | System
            |--------------------------------------------------------------------------
            */

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

            /*
            |--------------------------------------------------------------------------
            | Security
            |--------------------------------------------------------------------------
            |
            | These values define the application's configurable security
            | policy. Existing values are preserved when the seeder runs
            | again.
            |
            */

            [
                'group' => 'security',
                'key' => 'security.login_max_attempts',
                'value' => '5',
                'type' => 'integer',
                'description' => 'Maximum consecutive failed login attempts before the account is temporarily locked.',
                'sort_order' => 10,
            ],
            [
                'group' => 'security',
                'key' => 'security.lockout_duration_minutes',
                'value' => '15',
                'type' => 'integer',
                'description' => 'Duration in minutes for a temporary account lock after exceeding the failed login attempt limit.',
                'sort_order' => 20,
            ],
            [
                'group' => 'security',
                'key' => 'security.session_timeout_minutes',
                'value' => '120',
                'type' => 'integer',
                'description' => 'Maximum inactivity period in minutes before an authenticated session expires.',
                'sort_order' => 30,
            ],
            [
                'group' => 'security',
                'key' => 'security.password_min_length',
                'value' => '12',
                'type' => 'integer',
                'description' => 'Minimum number of characters required for user passwords.',
                'sort_order' => 40,
            ],
            [
                'group' => 'security',
                'key' => 'security.password_require_mixed_case',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Require passwords to contain both uppercase and lowercase characters.',
                'sort_order' => 50,
            ],
            [
                'group' => 'security',
                'key' => 'security.password_require_numbers',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Require passwords to contain at least one numeric character.',
                'sort_order' => 60,
            ],
            [
                'group' => 'security',
                'key' => 'security.password_require_symbols',
                'value' => '1',
                'type' => 'boolean',
                'description' => 'Require passwords to contain at least one symbol or special character.',
                'sort_order' => 70,
            ],
            [
                'group' => 'security',
                'key' => 'security.password_expiry_days',
                'value' => '0',
                'type' => 'integer',
                'description' => 'Number of days before a password expires. Set to 0 to disable password expiry.',
                'sort_order' => 80,
            ],
            [
                'group' => 'security',
                'key' => 'security.force_password_change',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Require users to change their password when the security policy requires it.',
                'sort_order' => 90,
            ],
            [
                'group' => 'security',
                'key' => 'security.require_email_verification',
                'value' => '0',
                'type' => 'boolean',
                'description' => 'Require users to verify their email address before accessing protected application features.',
                'sort_order' => 100,
            ],
        ];

        foreach ($settings as $data) {
            $setting = Setting::query()->firstOrNew([
                'key' => $data['key'],
            ]);

            /*
             * Preserve an existing setting value.
             *
             * This makes the seeder safe to run repeatedly without
             * resetting administrator-configured values.
             */
            if (! $setting->exists) {
                $setting->value = $data['value'];
            }

            /*
             * Keep setting metadata synchronized with the application
             * definition while preserving the current value.
             */
            $setting->group = $data['group'];
            $setting->type = $data['type'];
            $setting->description = $data['description'];
            $setting->is_active = true;
            $setting->sort_order = $data['sort_order'];

            $setting->save();
        }
    }
}
