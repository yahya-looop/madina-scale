<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Move seeded system navigation URLs into the staff backend namespace.
     *
     * Custom menu entries and menu IDs/permissions are intentionally untouched.
     */
    public function up(): void
    {
        $routes = [
            '/dashboard' => '/backend/dashboard',
            '/orders' => '/backend/orders',
            '/sales-returns' => '/backend/sales-returns',
            '/products' => '/backend/products',
            '/product-categories' => '/backend/product-categories',
            '/brands' => '/backend/brands',
            '/units' => '/backend/units',
            '/attributes' => '/backend/attributes',
            '/inventory' => '/backend/inventory',
            '/inventory/low-stock' => '/backend/inventory/low-stock',
            '/inventory/movements' => '/backend/inventory/movements',
            '/inventory/operations' => '/backend/inventory/operations',
            '/inventory/reservations' => '/backend/inventory/reservations',
            '/customers' => '/backend/customers',
            '/sales-crm/leads' => '/backend/sales-crm/leads',
            '/b2b' => '/backend/b2b',
            '/pricing' => '/backend/pricing',
            '/finance' => '/backend/finance',
            '/procurement' => '/backend/procurement',
            '/shipping-delivery' => '/backend/shipping-delivery',
            '/payments' => '/backend/payments',
            '/warranty-service' => '/backend/warranty-service',
            '/content/pages' => '/backend/content/pages',
            '/content/media' => '/backend/content/media',
            '/content/banners' => '/backend/content/banners',
            '/content/sections' => '/backend/content/sections',
            '/seo' => '/backend/seo',
            '/marketing' => '/backend/marketing',
            '/marketing/campaigns' => '/backend/marketing/campaigns',
            '/marketing/coupons' => '/backend/marketing/coupons',
            '/analytics' => '/backend/analytics',
            '/analytics/sales' => '/backend/analytics/sales',
            '/analytics/website' => '/backend/analytics/website',
            '/analytics/marketing' => '/backend/analytics/marketing',
            '/reports' => '/backend/reports',
            '/users' => '/backend/users',
            '/settings/roles-permissions' => '/backend/settings/roles-permissions',
            '/settings/system' => '/backend/settings/system',
            '/locations' => '/backend/locations',
            '/settings/activity-logs' => '/backend/settings/activity-logs',
            '/developer/console' => '/backend/developer/console',
            '/developer/api-providers' => '/backend/developer/api-providers',
            '/developer/modules' => '/backend/developer/modules',
            '/developer/feature-flags' => '/backend/developer/feature-flags',
            '/developer/menu-registry' => '/backend/developer/menu-registry',
        ];

        foreach ($routes as $legacy => $backend) {
            DB::table('menu_items')
                ->where('is_system', true)
                ->where('route', $legacy)
                ->update([
                    'route' => $backend,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        $routes = [
            '/backend/dashboard' => '/dashboard',
            '/backend/orders' => '/orders',
            '/backend/sales-returns' => '/sales-returns',
            '/backend/products' => '/products',
            '/backend/product-categories' => '/product-categories',
            '/backend/brands' => '/brands',
            '/backend/units' => '/units',
            '/backend/attributes' => '/attributes',
            '/backend/inventory' => '/inventory',
            '/backend/inventory/low-stock' => '/inventory/low-stock',
            '/backend/inventory/movements' => '/inventory/movements',
            '/backend/inventory/operations' => '/inventory/operations',
            '/backend/inventory/reservations' => '/inventory/reservations',
            '/backend/customers' => '/customers',
            '/backend/sales-crm/leads' => '/sales-crm/leads',
            '/backend/b2b' => '/b2b',
            '/backend/pricing' => '/pricing',
            '/backend/finance' => '/finance',
            '/backend/procurement' => '/procurement',
            '/backend/shipping-delivery' => '/shipping-delivery',
            '/backend/payments' => '/payments',
            '/backend/warranty-service' => '/warranty-service',
            '/backend/content/pages' => '/content/pages',
            '/backend/content/media' => '/content/media',
            '/backend/content/banners' => '/content/banners',
            '/backend/content/sections' => '/content/sections',
            '/backend/seo' => '/seo',
            '/backend/marketing' => '/marketing',
            '/backend/marketing/campaigns' => '/marketing/campaigns',
            '/backend/marketing/coupons' => '/marketing/coupons',
            '/backend/analytics' => '/analytics',
            '/backend/analytics/sales' => '/analytics/sales',
            '/backend/analytics/website' => '/analytics/website',
            '/backend/analytics/marketing' => '/analytics/marketing',
            '/backend/reports' => '/reports',
            '/backend/users' => '/users',
            '/backend/settings/roles-permissions' => '/settings/roles-permissions',
            '/backend/settings/system' => '/settings/system',
            '/backend/locations' => '/locations',
            '/backend/settings/activity-logs' => '/settings/activity-logs',
            '/backend/developer/console' => '/developer/console',
            '/backend/developer/api-providers' => '/developer/api-providers',
            '/backend/developer/modules' => '/developer/modules',
            '/backend/developer/feature-flags' => '/developer/feature-flags',
            '/backend/developer/menu-registry' => '/developer/menu-registry',
        ];

        foreach ($routes as $backend => $legacy) {
            DB::table('menu_items')
                ->where('is_system', true)
                ->where('route', $backend)
                ->update([
                    'route' => $legacy,
                    'updated_at' => now(),
                ]);
        }
    }
};
