<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (! DB::getSchemaBuilder()->hasTable('menu_items')) {
            return;
        }

        $parentId = DB::table('menu_items')
            ->where('key', 'shipping')
            ->whereNull('parent_id')
            ->value('id');

        if (! $parentId) {
            return;
        }

        $moduleId = DB::table('modules')->where('slug', 'shipping')->value('id');
        $now = now();

        DB::table('menu_items')->updateOrInsert(
            ['key' => 'shipping.shipping.delivery-partners'],
            [
                'parent_id' => $parentId,
                'module_id' => $moduleId,
                'label' => 'Delivery Partners',
                'route' => '/backend/shipping-delivery/courier-providers',
                'icon' => 'truck',
                'permission' => 'system.shipping.view',
                'sort_order' => 20,
                'is_visible' => true,
                'developer_only' => false,
                'is_system' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        if (DB::getSchemaBuilder()->hasTable('menu_items')) {
            DB::table('menu_items')
                ->where('key', 'shipping.shipping.delivery-partners')
                ->delete();
        }
    }
};
