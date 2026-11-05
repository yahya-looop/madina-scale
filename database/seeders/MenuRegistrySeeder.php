<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use App\Models\Module;
use Illuminate\Database\Seeder;

class MenuRegistrySeeder extends Seeder
{
    public function run(): void
    {
        $modules = Module::query()->pluck('id', 'slug');

        /** @var array<int, array{label:string,icon:string,module:string,route?:string,permission?:string,developer_only?:bool,children?:array<int,array{label:string,route:string,icon:string,permission?:string,module:string,developer_only?:bool}>}> $definitions */
        $definitions = [
            ['label' => 'Dashboard', 'icon' => 'dashboard', 'permission' => 'system.dashboard.view', 'module' => 'dashboard', 'route' => '/dashboard'],
            ['label' => 'Sales', 'icon' => 'sales', 'module' => 'sales', 'children' => [
                ['label' => 'Sales Orders', 'route' => '/orders', 'icon' => 'orders', 'permission' => 'system.orders.view', 'module' => 'orders'],
                ['label' => 'Sales Returns', 'route' => '/sales-returns', 'icon' => 'undo-2', 'permission' => 'system.sales_returns.view', 'module' => 'finance'],
            ]],
            ['label' => 'Product Management', 'icon' => 'products', 'module' => 'products', 'children' => [
                ['label' => 'Products', 'route' => '/products', 'icon' => 'products', 'permission' => 'system.products.view', 'module' => 'products'],
                ['label' => 'Categories', 'route' => '/product-categories', 'icon' => 'chart-column-stacked', 'permission' => 'system.products.categories.view', 'module' => 'products'],
                ['label' => 'Brands', 'route' => '/brands', 'icon' => 'brand', 'permission' => 'system.products.brands.view', 'module' => 'products'],
                ['label' => 'Units', 'route' => '/units', 'icon' => 'combine', 'permission' => 'system.products.units.view', 'module' => 'products'],
                ['label' => 'Attributes', 'route' => '/attributes', 'icon' => 'book-heart', 'permission' => 'system.products.attributes.view', 'module' => 'products'],
            ]],
            ['label' => 'Inventory', 'icon' => 'inventory', 'module' => 'inventory', 'children' => [
                ['label' => 'Stock Overview', 'route' => '/inventory', 'icon' => 'boxes', 'permission' => 'system.inventory.view', 'module' => 'inventory'],
                ['label' => 'Low Stock', 'route' => '/inventory/low-stock', 'icon' => 'alert-triangle', 'permission' => 'system.inventory.view', 'module' => 'inventory'],
                ['label' => 'Stock Movements', 'route' => '/inventory/movements', 'icon' => 'history', 'permission' => 'system.inventory.view', 'module' => 'inventory'],
                ['label' => 'Stock Operations', 'route' => '/inventory/operations', 'icon' => 'settings-2', 'permission' => 'system.inventory.view', 'module' => 'inventory'],
                ['label' => 'Reservations', 'route' => '/inventory/reservations', 'icon' => 'clipboard-list', 'permission' => 'system.inventory.view', 'module' => 'inventory'],
            ]],
            ['label' => 'Customers', 'icon' => 'customers', 'module' => 'customers', 'children' => [
                ['label' => 'Customer List', 'route' => '/customers', 'icon' => 'customers', 'permission' => 'system.customers.view', 'module' => 'customers'],
            ]],
            ['label' => 'Sales CRM', 'icon' => 'customers', 'module' => 'crm', 'children' => [
                ['label' => 'Lead Management', 'route' => '/sales-crm/leads', 'icon' => 'users', 'permission' => 'system.crm.leads.view', 'module' => 'crm'],
            ]],
            ['label' => 'B2B & Wholesale', 'route' => '/b2b', 'icon' => 'wholesale', 'permission' => 'system.b2b.view', 'module' => 'b2b'],
            ['label' => 'Pricing & Promotions', 'route' => '/pricing', 'icon' => 'pricing', 'permission' => 'system.pricing.view', 'module' => 'pricing'],
            ['label' => 'Accounts & Finance', 'icon' => 'wallet', 'module' => 'finance', 'children' => [
                ['label' => 'Finance Overview', 'route' => '/finance', 'icon' => 'chart-no-axes-combined', 'permission' => 'system.finance.view', 'module' => 'finance'],
            ]],
            ['label' => 'Procurement & Suppliers', 'icon' => 'shopping-cart', 'module' => 'procurement', 'children' => [
                ['label' => 'Procurement Overview', 'route' => '/procurement', 'icon' => 'layout-dashboard', 'permission' => 'system.procurement.view', 'module' => 'procurement'],
            ]],
            ['label' => 'Shipping & Delivery', 'icon' => 'shipping', 'module' => 'shipping', 'children' => [
                ['label' => 'Delivery Dashboard', 'route' => '/shipping-delivery', 'icon' => 'truck', 'permission' => 'system.shipping.view', 'module' => 'shipping'],
            ]],
            ['label' => 'Payments', 'route' => '/payments', 'icon' => 'payments', 'permission' => 'system.payments.view', 'module' => 'payments'],
            ['label' => 'Warranty & Service', 'route' => '/warranty-service', 'icon' => 'service', 'permission' => 'system.warranty.view', 'module' => 'warranty'],
            ['label' => 'Website Management', 'icon' => 'globe', 'module' => 'content', 'children' => [
                ['label' => 'Content Pages', 'route' => '/content/pages', 'icon' => 'cms', 'permission' => 'system.content.view', 'module' => 'content'],
                ['label' => 'Media Library', 'route' => '/content/media', 'icon' => 'image', 'permission' => 'system.content.view', 'module' => 'content'],
                ['label' => 'Banner Management', 'route' => '/content/banners', 'icon' => 'image', 'permission' => 'system.content.view', 'module' => 'content'],
                ['label' => 'Reusable Sections', 'route' => '/content/sections', 'icon' => 'layout', 'permission' => 'system.content.view', 'module' => 'content'],
                ['label' => 'SEO Management', 'route' => '/seo', 'icon' => 'seo', 'permission' => 'system.seo.view', 'module' => 'seo'],
            ]],
            ['label' => 'Marketing', 'icon' => 'marketing', 'module' => 'marketing', 'children' => [
                ['label' => 'Marketing Overview', 'route' => '/marketing', 'icon' => 'dashboard', 'permission' => 'system.marketing.view', 'module' => 'marketing'],
                ['label' => 'Campaigns', 'route' => '/marketing/campaigns', 'icon' => 'target', 'permission' => 'system.marketing.view', 'module' => 'marketing'],
                ['label' => 'Coupons & Offers', 'route' => '/marketing/coupons', 'icon' => 'tag', 'permission' => 'system.marketing.view', 'module' => 'marketing'],
            ]],
            ['label' => 'Analytics', 'icon' => 'analytics', 'module' => 'analytics', 'children' => [
                ['label' => 'Analytics Overview', 'route' => '/analytics', 'icon' => 'chart-column', 'permission' => 'system.analytics.view', 'module' => 'analytics'],
                ['label' => 'Sales Analytics', 'route' => '/analytics/sales', 'icon' => 'sales', 'permission' => 'system.analytics.view', 'module' => 'analytics'],
                ['label' => 'Website Analytics', 'route' => '/analytics/website', 'icon' => 'globe', 'permission' => 'system.analytics.view', 'module' => 'analytics'],
                ['label' => 'Marketing Analytics', 'route' => '/analytics/marketing', 'icon' => 'megaphone', 'permission' => 'system.analytics.view', 'module' => 'analytics'],
                ['label' => 'Reports', 'route' => '/reports', 'icon' => 'reports', 'permission' => 'system.reports.view', 'module' => 'analytics'],
            ]],
            ['label' => 'Team & Users', 'icon' => 'users', 'module' => 'teams', 'children' => [
                ['label' => 'Users', 'route' => '/users', 'icon' => 'users', 'permission' => 'system.users.view', 'module' => 'teams'],
            ]],
            ['label' => 'Settings', 'icon' => 'settings', 'module' => 'settings', 'children' => [
                ['label' => 'Role & Permissions', 'route' => '/settings/roles-permissions', 'icon' => 'users', 'permission' => 'system.roles.view', 'module' => 'settings'],
                ['label' => 'System', 'route' => '/settings/system', 'icon' => 'settings', 'permission' => 'system.settings.view', 'module' => 'settings'],
                ['label' => 'Locations', 'route' => '/locations', 'icon' => 'map-pin', 'permission' => 'system.locations.view', 'module' => 'settings'],
                ['label' => 'Activity Logs', 'route' => '/settings/activity-logs', 'icon' => 'activity', 'permission' => 'system.activity_logs.view', 'module' => 'settings'],
            ]],
            ['label' => 'Developer', 'icon' => 'developer', 'module' => 'system', 'developer_only' => true, 'children' => [
                ['label' => 'Developer Console', 'route' => '/developer/console', 'icon' => 'layout-dashboard', 'permission' => 'system.system.view', 'module' => 'system'],
                ['label' => 'API & Providers', 'route' => '/developer/api-providers', 'icon' => 'plug-zap', 'permission' => 'system.api_providers.view', 'module' => 'api-providers'],
                ['label' => 'Module Management', 'route' => '/developer/modules', 'icon' => 'layers', 'permission' => 'system.modules.view', 'module' => 'modules'],
                ['label' => 'Feature Flags', 'route' => '/developer/feature-flags', 'icon' => 'flag', 'permission' => 'system.system.manage', 'module' => 'system'],
                ['label' => 'Menu Registry', 'route' => '/developer/menu-registry', 'icon' => 'list-tree', 'permission' => 'system.system.manage', 'module' => 'system'],
            ]],
        ];

        foreach ($definitions as $index => $definition) {
            $parentKey = str_replace('-', '.', $definition['module']);
            $parent = MenuItem::query()->firstOrNew(['key' => $parentKey]);
            if (! $parent->exists) {
                $parent->fill([
                    'label' => $definition['label'],
                    'icon' => $definition['icon'],
                    'permission' => $definition['permission'] ?? null,
                    'sort_order' => ($index + 1) * 10,
                    'is_visible' => true,
                ]);
            }
            $parent->forceFill([
                'key' => $parentKey,
                'parent_id' => null,
                'module_id' => $modules[$definition['module']] ?? null,
                'route' => $definition['route'] ?? null,
                'developer_only' => $definition['developer_only'] ?? false,
                'is_system' => true,
            ])->save();

            foreach ($definition['children'] ?? [] as $childIndex => $child) {
                $childKey = $parentKey.'.'.str_replace('-', '.', $child['module']).'.'.strtolower(str_replace([' ', '&'], ['-', 'and'], $child['label']));
                $item = MenuItem::query()->firstOrNew(['key' => $childKey]);
                if (! $item->exists) {
                    $item->fill([
                        'label' => $child['label'],
                        'icon' => $child['icon'],
                        'permission' => $child['permission'] ?? null,
                        'sort_order' => ($childIndex + 1) * 10,
                        'is_visible' => true,
                    ]);
                }
                $item->forceFill([
                    'key' => $childKey,
                    'parent_id' => $parent->id,
                    'module_id' => $modules[$child['module']] ?? null,
                    'route' => $child['route'],
                    'developer_only' => $child['developer_only'] ?? false,
                    'is_system' => true,
                ])->save();
            }
        }
    }
}
