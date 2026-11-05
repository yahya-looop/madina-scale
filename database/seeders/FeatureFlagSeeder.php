<?php

namespace Database\Seeders;

use App\Models\FeatureFlag;
use App\Models\Module;
use Illuminate\Database\Seeder;

class FeatureFlagSeeder extends Seeder
{
    public function run(): void
    {
        $definitions = [
            ['key' => 'storefront.checkout.enabled', 'name' => 'Storefront checkout', 'description' => 'Allow customers to submit storefront orders.', 'module' => 'content', 'is_enabled' => true],
            ['key' => 'marketing.meta.enabled', 'name' => 'Meta integration', 'description' => 'Enable Meta marketing integration workflows.', 'module' => 'marketing', 'is_enabled' => false],
            ['key' => 'marketing.google.enabled', 'name' => 'Google integration', 'description' => 'Enable Google marketing integration workflows.', 'module' => 'marketing', 'is_enabled' => false],
            ['key' => 'support.live_chat.enabled', 'name' => 'Customer live chat', 'description' => 'Enable customer live chat capabilities when implemented.', 'module' => 'content', 'is_enabled' => false],
            ['key' => 'analytics.attribution.enabled', 'name' => 'Marketing attribution', 'description' => 'Enable marketing attribution processing when implemented.', 'module' => 'analytics', 'is_enabled' => false],
        ];

        foreach ($definitions as $definition) {
            $flag = FeatureFlag::query()->firstOrNew(['key' => $definition['key']]);
            if (! $flag->exists) {
                $flag->fill([
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'is_enabled' => $definition['is_enabled'],
                    'rollout_percentage' => 100,
                    'environment' => 'all',
                ]);
            }
            $flag->forceFill([
                'module_id' => Module::query()->where('slug', $definition['module'])->value('id'),
                'is_system' => true,
            ])->save();
        }
    }
}
