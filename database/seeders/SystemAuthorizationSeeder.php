<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class SystemAuthorizationSeeder extends Seeder
{
    /**
     * Seed the application's complete authorization catalog.

     *

     * The authorization catalog is intentionally data-driven:

     *

     * Module

     *     ↓

     * Permission

     *     ↓

     * Role

     *     ↓

     * User

     *

     * The seeder is idempotent and can safely be executed repeatedly.
     */
    public function run(): void
    {

        $moduleDefinitions = $this->moduleDefinitions();

        $modules = [];

        foreach ($moduleDefinitions as $moduleDefinition) {

            $modules[$moduleDefinition['slug']] = Module::updateOrCreate(

                ['slug' => $moduleDefinition['slug']],

                [

                    'name' => $moduleDefinition['name'],

                    'description' => $moduleDefinition['description'],

                    'icon' => $moduleDefinition['icon'],

                    'sort_order' => $moduleDefinition['sort_order'],

                    'is_system' => true,

                    'is_active' => true,

                ],

            );

        }

        $permissions = [];

        foreach ($moduleDefinitions as $moduleDefinition) {

            $module = $modules[$moduleDefinition['slug']];

            foreach ($moduleDefinition['permissions'] as $permissionDefinition) {

                $permissions[$permissionDefinition['slug']] = Permission::updateOrCreate(

                    ['slug' => $permissionDefinition['slug']],

                    [

                        'module_id' => $module->id,

                        'name' => $permissionDefinition['name'],

                        'description' => $permissionDefinition['description'],

                    ],

                );

            }

        }

        $this->syncDeveloperRole(

            collect($permissions)->values()->all(),

        );

    }

    /**
     * Define the application's authorization modules and permissions.

     *

     * To add a new module:

     * 1. Add one definition here.

     * 2. Add its permissions.

     * 3. Run the authorization seeder.

     *

     * To add a new permission:

     * 1. Add the permission under the appropriate module.

     * 2. Run the authorization seeder.

     *

     * Role-specific access is intentionally NOT hard-coded here.

     * Roles will be configured separately through the role-management

     * system so Developer can control access dynamically.

     *

     * @return array<int, array{

     *     name: string,

     *     slug: string,

     *     description: string,

     *     icon: string,

     *     sort_order: int,

     *     permissions: array<int, array{

     *         name: string,

     *         slug: string,

     *         description: string

     *     }>

     * }>
     */
    private function moduleDefinitions(): array
    {

        return [

            [

                'name' => 'Dashboard',

                'slug' => 'dashboard',

                'description' => 'Application dashboards and operational overview.',

                'icon' => 'dashboard',

                'sort_order' => 10,

                'permissions' => [

                    [

                        'name' => 'View Dashboard',

                        'slug' => 'system.dashboard.view',

                        'description' => 'View the application dashboard.',

                    ],

                    [

                        'name' => 'View Analytics',

                        'slug' => 'system.dashboard.analytics.view',

                        'description' => 'View dashboard analytics and performance metrics.',

                    ],

                ],

            ],

            [

                'name' => 'Sales',

                'slug' => 'sales',

                'description' => 'Sales operations, activities and sales performance.',

                'icon' => 'sales',

                'sort_order' => 20,

                'permissions' => [

                    [

                        'name' => 'View Sales',

                        'slug' => 'system.sales.view',

                        'description' => 'View sales records and sales activity.',

                    ],

                    [

                        'name' => 'Create Sales',

                        'slug' => 'system.sales.create',

                        'description' => 'Create sales records.',

                    ],

                    [

                        'name' => 'Update Sales',

                        'slug' => 'system.sales.update',

                        'description' => 'Update sales records.',

                    ],

                    [

                        'name' => 'Delete Sales',

                        'slug' => 'system.sales.delete',

                        'description' => 'Delete eligible sales records.',

                    ],

                    [

                        'name' => 'View Sales Analytics',

                        'slug' => 'system.sales.analytics.view',

                        'description' => 'View sales performance analytics.',

                    ],

                ],

            ],

            [

                'name' => 'Orders',

                'slug' => 'orders',

                'description' => 'Order creation, processing, fulfillment and lifecycle management.',

                'icon' => 'orders',

                'sort_order' => 30,

                'permissions' => [

                    [

                        'name' => 'View Orders',

                        'slug' => 'system.orders.view',

                        'description' => 'View orders.',

                    ],

                    [

                        'name' => 'Create Orders',

                        'slug' => 'system.orders.create',

                        'description' => 'Create orders.',

                    ],

                    [

                        'name' => 'Update Orders',

                        'slug' => 'system.orders.update',

                        'description' => 'Update order information.',

                    ],

                    [

                        'name' => 'Confirm Orders',

                        'slug' => 'system.orders.confirm',

                        'description' => 'Confirm pending orders.',

                    ],

                    [

                        'name' => 'Cancel Orders',

                        'slug' => 'system.orders.cancel',

                        'description' => 'Cancel eligible orders.',

                    ],

                    [

                        'name' => 'Fulfill Orders',

                        'slug' => 'system.orders.fulfill',

                        'description' => 'Prepare and mark orders for fulfillment.',

                    ],

                    [

                        'name' => 'Manage Order Risk Reviews',

                        'slug' => 'system.orders.risk.manage',

                        'description' => 'Review suspicious orders and verification signals.',

                    ],

                    [

                        'name' => 'Manage Order Communications',

                        'slug' => 'system.orders.communication.manage',

                        'description' => 'Send and review order SMS communications.',

                    ],

                    [

                        'name' => 'Manage Order Shipments',

                        'slug' => 'system.orders.shipment.manage',

                        'description' => 'Book and manage courier shipments.',

                    ],

                    [

                        'name' => 'View Order Analytics',

                        'slug' => 'system.orders.analytics.view',

                        'description' => 'View order analytics and performance metrics.',

                    ],

                ],

            ],

            [

                'name' => 'Customers',

                'slug' => 'customers',

                'description' => 'Customer profiles, customer activity and customer relationship management.',

                'icon' => 'users',

                'sort_order' => 40,

                'permissions' => [

                    [

                        'name' => 'View Customers',

                        'slug' => 'system.customers.view',

                        'description' => 'View customer records.',

                    ],

                    [

                        'name' => 'Create Customers',

                        'slug' => 'system.customers.create',

                        'description' => 'Create customer records.',

                    ],

                    [

                        'name' => 'Update Customers',

                        'slug' => 'system.customers.update',

                        'description' => 'Update customer records.',

                    ],

                    [

                        'name' => 'Delete Customers',

                        'slug' => 'system.customers.delete',

                        'description' => 'Delete eligible customer records.',

                    ],

                    [

                        'name' => 'View Customer Analytics',

                        'slug' => 'system.customers.analytics.view',

                        'description' => 'View customer analytics.',

                    ],

                ],

            ],

            [

                'name' => 'Products',

                'slug' => 'products',

                'description' => 'Product catalog and product information management.',

                'icon' => 'products',

                'sort_order' => 50,

                'permissions' => [

                    [

                        'name' => 'View Products',

                        'slug' => 'system.products.view',

                        'description' => 'View products.',

                    ],

                    [

                        'name' => 'Create Products',

                        'slug' => 'system.products.create',

                        'description' => 'Create products.',

                    ],

                    [

                        'name' => 'Update Products',

                        'slug' => 'system.products.update',

                        'description' => 'Update products.',

                    ],

                    [

                        'name' => 'Delete Products',

                        'slug' => 'system.products.delete',

                        'description' => 'Delete eligible products.',

                    ],

                    [

                        'name' => 'View Product Analytics',

                        'slug' => 'system.products.analytics.view',

                        'description' => 'View product performance analytics.',

                    ],

                    /*

                     * Product Categories

                     */

                    [

                        'name' => 'View Product Categories',

                        'slug' => 'system.products.categories.view',

                        'description' => 'View product categories.',

                    ],

                    [

                        'name' => 'Create Product Categories',

                        'slug' => 'system.products.categories.create',

                        'description' => 'Create product categories.',

                    ],

                    [

                        'name' => 'Update Product Categories',

                        'slug' => 'system.products.categories.update',

                        'description' => 'Update product categories.',

                    ],

                    [

                        'name' => 'Delete Product Categories',

                        'slug' => 'system.products.categories.delete',

                        'description' => 'Delete eligible product categories.',

                    ],

                    [

                        'name' => 'Manage Product Categories',

                        'slug' => 'system.products.categories.manage',

                        'description' => 'Manage product category hierarchy, status and visibility.',

                    ],

                    /*

                    * Brands

                    */

                    [

                        'name' => 'View Brands',

                        'slug' => 'system.products.brands.view',

                        'description' => 'View product brands.',

                    ],

                    [

                        'name' => 'Create Brands',

                        'slug' => 'system.products.brands.create',

                        'description' => 'Create product brands.',

                    ],

                    [

                        'name' => 'Update Brands',

                        'slug' => 'system.products.brands.update',

                        'description' => 'Update product brands.',

                    ],

                    [

                        'name' => 'Delete Brands',

                        'slug' => 'system.products.brands.delete',

                        'description' => 'Delete eligible product brands.',

                    ],

                    [

                        'name' => 'Manage Brands',

                        'slug' => 'system.products.brands.manage',

                        'description' => 'Manage product brand status, ordering and configuration.',

                    ],

                    /*

                    * Units

                    */

                    [

                        'name' => 'View Units',

                        'slug' => 'system.products.units.view',

                        'description' => 'View product units.',

                    ],

                    [

                        'name' => 'Create Units',

                        'slug' => 'system.products.units.create',

                        'description' => 'Create product units.',

                    ],

                    [

                        'name' => 'Update Units',

                        'slug' => 'system.products.units.update',

                        'description' => 'Update product units.',

                    ],

                    [

                        'name' => 'Delete Units',

                        'slug' => 'system.products.units.delete',

                        'description' => 'Delete eligible product units.',

                    ],

                    [

                        'name' => 'Manage Units',

                        'slug' => 'system.products.units.manage',

                        'description' => 'Manage product unit status, ordering and configuration.',

                    ],

                    /*

                    * Attributes

                    */

                    [

                        'name' => 'View Attributes',

                        'slug' => 'system.products.attributes.view',

                        'description' => 'View product attributes.',

                    ],

                    [

                        'name' => 'Create Attributes',

                        'slug' => 'system.products.attributes.create',

                        'description' => 'Create product attributes.',

                    ],

                    [

                        'name' => 'Update Attributes',

                        'slug' => 'system.products.attributes.update',

                        'description' => 'Update product attributes.',

                    ],

                    [

                        'name' => 'Delete Attributes',

                        'slug' => 'system.products.attributes.delete',

                        'description' => 'Delete eligible product attributes.',

                    ],

                    [

                        'name' => 'Manage Attributes',

                        'slug' => 'system.products.attributes.manage',

                        'description' => 'Manage product attribute status, ordering and configuration.',

                    ],

                    /*

                    * Attributes

                    */

                    [

                        'name' => 'View Attribute Values',

                        'slug' => 'system.products.attributes.values.view',

                        'description' => 'View product attribute values.',

                    ],

                    [

                        'name' => 'Create Attribute Values',

                        'slug' => 'system.products.attributes.values.create',

                        'description' => 'Create product attribute values.',

                    ],

                    [

                        'name' => 'Update Attribute Values',

                        'slug' => 'system.products.attributes.values.update',

                        'description' => 'Update product attribute values.',

                    ],

                    [

                        'name' => 'Delete Attribute Values',

                        'slug' => 'system.products.attributes.values.delete',

                        'description' => 'Delete product attribute values.',

                    ],

                    [

                        'name' => 'Manage Attribute Values',

                        'slug' => 'system.products.attributes.values.manage',

                        'description' => 'Manage product attribute values.',

                    ],

                ],

            ],

            [

                'name' => 'Inventory',

                'slug' => 'inventory',

                'description' => 'Inventory levels, stock movement and inventory operations.',

                'icon' => 'inventory',

                'sort_order' => 60,

                'permissions' => [

                    [

                        'name' => 'View Inventory',

                        'slug' => 'system.inventory.view',

                        'description' => 'View inventory and stock levels.',

                    ],

                    [

                        'name' => 'Create Inventory Adjustment',

                        'slug' => 'system.inventory.adjust',

                        'description' => 'Create authorized inventory adjustments.',

                    ],

                    [

                        'name' => 'Update Inventory',

                        'slug' => 'system.inventory.update',

                        'description' => 'Update inventory records.',

                    ],

                    [

                        'name' => 'View Inventory Analytics',

                        'slug' => 'system.inventory.analytics.view',

                        'description' => 'View inventory analytics and stock performance.',

                    ],

                ],

            ],

            [

                'name' => 'Warehouse & Fulfillment',

                'slug' => 'warehouse',

                'description' => 'Warehouse operations, picking, packing and fulfillment.',

                'icon' => 'warehouse',

                'sort_order' => 70,

                'permissions' => [

                    [

                        'name' => 'View Warehouse',

                        'slug' => 'system.warehouse.view',

                        'description' => 'View warehouse operations.',

                    ],

                    [

                        'name' => 'Pick Orders',

                        'slug' => 'system.warehouse.pick',

                        'description' => 'Pick products for confirmed orders.',

                    ],

                    [

                        'name' => 'Pack Orders',

                        'slug' => 'system.warehouse.pack',

                        'description' => 'Pack products for shipment.',

                    ],

                    [

                        'name' => 'Prepare Shipment',

                        'slug' => 'system.warehouse.shipment.prepare',

                        'description' => 'Prepare orders for courier handover.',

                    ],

                    [

                        'name' => 'View Fulfillment Analytics',

                        'slug' => 'system.warehouse.analytics.view',

                        'description' => 'View warehouse and fulfillment analytics.',

                    ],

                ],

            ],

            [

                'name' => 'Shipping & Delivery',

                'slug' => 'shipping',

                'description' => 'Courier, shipping and delivery management.',

                'icon' => 'truck',

                'sort_order' => 80,

                'permissions' => [

                    [

                        'name' => 'View Shipping',

                        'slug' => 'system.shipping.view',

                        'description' => 'View shipping and delivery information.',

                    ],

                    [

                        'name' => 'Create Shipment',

                        'slug' => 'system.shipping.create',

                        'description' => 'Create shipments.',

                    ],

                    [

                        'name' => 'Update Shipment',

                        'slug' => 'system.shipping.update',

                        'description' => 'Update shipment information.',

                    ],

                    [

                        'name' => 'Manage Delivery Status',

                        'slug' => 'system.shipping.delivery.manage',

                        'description' => 'Manage delivery status information.',

                    ],

                    [

                        'name' => 'View Shipping Analytics',

                        'slug' => 'system.shipping.analytics.view',

                        'description' => 'View shipping and delivery analytics.',

                    ],

                ],

            ],

            [

                'name' => 'Payments',

                'slug' => 'payments',

                'description' => 'Payment records, transactions and payment operations.',

                'icon' => 'payments',

                'sort_order' => 90,

                'permissions' => [

                    [

                        'name' => 'View Payments',

                        'slug' => 'system.payments.view',

                        'description' => 'View payment records.',

                    ],

                    [

                        'name' => 'Create Payments',

                        'slug' => 'system.payments.create',

                        'description' => 'Create payment records.',

                    ],

                    [

                        'name' => 'Update Payments',

                        'slug' => 'system.payments.update',

                        'description' => 'Update payment records.',

                    ],

                    [

                        'name' => 'Refund Payments',

                        'slug' => 'system.payments.refund',

                        'description' => 'Process authorized payment refunds.',

                    ],

                    [

                        'name' => 'View Payment Analytics',

                        'slug' => 'system.payments.analytics.view',

                        'description' => 'View payment and transaction analytics.',

                    ],

                ],

            ],

            [

                'name' => 'B2B & Wholesale',

                'slug' => 'b2b',

                'description' => 'Business customers, wholesale accounts and B2B operations.',

                'icon' => 'building',

                'sort_order' => 100,

                'permissions' => [

                    [

                        'name' => 'View B2B',

                        'slug' => 'system.b2b.view',

                        'description' => 'View B2B and wholesale records.',

                    ],

                    [

                        'name' => 'Create B2B',

                        'slug' => 'system.b2b.create',

                        'description' => 'Create B2B and wholesale records.',

                    ],

                    [

                        'name' => 'Update B2B',

                        'slug' => 'system.b2b.update',

                        'description' => 'Update B2B and wholesale records.',

                    ],

                    [

                        'name' => 'Delete B2B',

                        'slug' => 'system.b2b.delete',

                        'description' => 'Delete eligible B2B records.',

                    ],

                    [

                        'name' => 'View B2B Analytics',

                        'slug' => 'system.b2b.analytics.view',

                        'description' => 'View B2B and wholesale analytics.',

                    ],

                ],

            ],

            [

                'name' => 'Pricing & Promotions',

                'slug' => 'pricing',

                'description' => 'Pricing rules, discounts, promotions and commercial offers.',

                'icon' => 'tag',

                'sort_order' => 110,

                'permissions' => [

                    [

                        'name' => 'View Pricing',

                        'slug' => 'system.pricing.view',

                        'description' => 'View pricing configuration.',

                    ],

                    [

                        'name' => 'Create Pricing',

                        'slug' => 'system.pricing.create',

                        'description' => 'Create pricing rules and offers.',

                    ],

                    [

                        'name' => 'Update Pricing',

                        'slug' => 'system.pricing.update',

                        'description' => 'Update pricing rules and offers.',

                    ],

                    [

                        'name' => 'Delete Pricing',

                        'slug' => 'system.pricing.delete',

                        'description' => 'Delete eligible pricing rules and offers.',

                    ],

                    [

                        'name' => 'Manage Promotions',

                        'slug' => 'system.pricing.promotions.manage',

                        'description' => 'Manage discounts, promotions and commercial campaigns.',

                    ],

                ],

            ],

            [

                'name' => 'Marketing',

                'slug' => 'marketing',

                'description' => 'Marketing campaigns, channels, audiences and marketing operations.',

                'icon' => 'megaphone',

                'sort_order' => 120,

                'permissions' => [

                    [

                        'name' => 'View Marketing',

                        'slug' => 'system.marketing.view',

                        'description' => 'View marketing activities and campaigns.',

                    ],

                    [

                        'name' => 'Create Marketing Campaigns',

                        'slug' => 'system.marketing.create',

                        'description' => 'Create marketing campaigns.',

                    ],

                    [

                        'name' => 'Update Marketing Campaigns',

                        'slug' => 'system.marketing.update',

                        'description' => 'Update marketing campaigns.',

                    ],

                    [

                        'name' => 'Delete Marketing Campaigns',

                        'slug' => 'system.marketing.delete',

                        'description' => 'Delete eligible marketing campaigns.',

                    ],

                    [

                        'name' => 'View Marketing Analytics',

                        'slug' => 'system.marketing.analytics.view',

                        'description' => 'View marketing performance analytics.',

                    ],

                ],

            ],

            [

                'name' => 'Social Media',

                'slug' => 'social-media',

                'description' => 'Social media channels, publishing and social activity management.',

                'icon' => 'social',

                'sort_order' => 130,

                'permissions' => [

                    [

                        'name' => 'View Social Media',

                        'slug' => 'system.social_media.view',

                        'description' => 'View social media activity.',

                    ],

                    [

                        'name' => 'Create Social Content',

                        'slug' => 'system.social_media.create',

                        'description' => 'Create social media content.',

                    ],

                    [

                        'name' => 'Update Social Content',

                        'slug' => 'system.social_media.update',

                        'description' => 'Update social media content.',

                    ],

                    [

                        'name' => 'Publish Social Content',

                        'slug' => 'system.social_media.publish',

                        'description' => 'Publish approved social media content.',

                    ],

                    [

                        'name' => 'Delete Social Content',

                        'slug' => 'system.social_media.delete',

                        'description' => 'Delete eligible social media content.',

                    ],

                    [

                        'name' => 'View Social Analytics',

                        'slug' => 'system.social_media.analytics.view',

                        'description' => 'View social media performance analytics.',

                    ],

                ],

            ],

            [

                'name' => 'Content CMS',

                'slug' => 'content',

                'description' => 'Website content, pages, media and publishing workflows.',

                'icon' => 'file-text',

                'sort_order' => 140,

                'permissions' => [

                    [

                        'name' => 'View Content',

                        'slug' => 'system.content.view',

                        'description' => 'View CMS content.',

                    ],

                    [

                        'name' => 'Create Content',

                        'slug' => 'system.content.create',

                        'description' => 'Create CMS content.',

                    ],

                    [

                        'name' => 'Update Content',

                        'slug' => 'system.content.update',

                        'description' => 'Update CMS content.',

                    ],

                    [

                        'name' => 'Publish Content',

                        'slug' => 'system.content.publish',

                        'description' => 'Publish approved CMS content.',

                    ],

                    [

                        'name' => 'Delete Content',

                        'slug' => 'system.content.delete',

                        'description' => 'Delete eligible CMS content.',

                    ],

                ],

            ],

            [

                'name' => 'SEO',

                'slug' => 'seo',

                'description' => 'Search engine optimization and website search visibility management.',

                'icon' => 'search',

                'sort_order' => 150,

                'permissions' => [

                    [

                        'name' => 'View SEO',

                        'slug' => 'system.seo.view',

                        'description' => 'View SEO configuration and performance.',

                    ],

                    [

                        'name' => 'Create SEO Configuration',

                        'slug' => 'system.seo.create',

                        'description' => 'Create SEO configuration.',

                    ],

                    [

                        'name' => 'Update SEO Configuration',

                        'slug' => 'system.seo.update',

                        'description' => 'Update SEO configuration.',

                    ],

                    [

                        'name' => 'View SEO Analytics',

                        'slug' => 'system.seo.analytics.view',

                        'description' => 'View SEO performance analytics.',

                    ],

                ],

            ],

            [

                'name' => 'Warranty',

                'slug' => 'warranty',

                'description' => 'Warranty records, claims and warranty service operations.',

                'icon' => 'shield-check',

                'sort_order' => 160,

                'permissions' => [

                    [

                        'name' => 'View Warranty',

                        'slug' => 'system.warranty.view',

                        'description' => 'View warranty records and claims.',

                    ],

                    [

                        'name' => 'Create Warranty',

                        'slug' => 'system.warranty.create',

                        'description' => 'Create warranty records and claims.',

                    ],

                    [

                        'name' => 'Update Warranty',

                        'slug' => 'system.warranty.update',

                        'description' => 'Update warranty records and claims.',

                    ],

                    [

                        'name' => 'Process Warranty Claims',

                        'slug' => 'system.warranty.process',

                        'description' => 'Process warranty claims.',

                    ],

                    [

                        'name' => 'View Warranty Analytics',

                        'slug' => 'system.warranty.analytics.view',

                        'description' => 'View warranty analytics.',

                    ],

                ],

            ],

            [

                'name' => 'Analytics',

                'slug' => 'analytics',

                'description' => 'Cross-system analytics, metrics and business intelligence.',

                'icon' => 'chart',

                'sort_order' => 170,

                'permissions' => [

                    [

                        'name' => 'View Analytics',

                        'slug' => 'system.analytics.view',

                        'description' => 'View system analytics.',

                    ],

                    [

                        'name' => 'View Business Analytics',

                        'slug' => 'system.analytics.business.view',

                        'description' => 'View business performance analytics.',

                    ],

                    [

                        'name' => 'Export Analytics',

                        'slug' => 'system.analytics.export',

                        'description' => 'Export analytics data.',

                    ],

                ],

            ],

            [

                'name' => 'Reports',

                'slug' => 'reports',

                'description' => 'Operational, financial and business reporting.',

                'icon' => 'reports',

                'sort_order' => 180,

                'permissions' => [

                    [

                        'name' => 'View Reports',

                        'slug' => 'system.reports.view',

                        'description' => 'View system reports.',

                    ],

                    [

                        'name' => 'Create Reports',

                        'slug' => 'system.reports.create',

                        'description' => 'Create reports.',

                    ],

                    [

                        'name' => 'Export Reports',

                        'slug' => 'system.reports.export',

                        'description' => 'Export reports.',

                    ],

                ],

            ],

            [

                'name' => 'Customer Support',

                'slug' => 'support',

                'description' => 'Customer service, support conversations and support operations.',

                'icon' => 'support',

                'sort_order' => 190,

                'permissions' => [

                    [

                        'name' => 'View Support',

                        'slug' => 'system.support.view',

                        'description' => 'View customer support records and conversations.',

                    ],

                    [

                        'name' => 'Create Support Case',

                        'slug' => 'system.support.create',

                        'description' => 'Create customer support cases.',

                    ],

                    [

                        'name' => 'Update Support Case',

                        'slug' => 'system.support.update',

                        'description' => 'Update customer support cases.',

                    ],

                    [

                        'name' => 'Resolve Support Case',

                        'slug' => 'system.support.resolve',

                        'description' => 'Resolve customer support cases.',

                    ],

                    [

                        'name' => 'View Support Analytics',

                        'slug' => 'system.support.analytics.view',

                        'description' => 'View support performance analytics.',

                    ],

                ],

            ],

            [

                'name' => 'Teams & Users',

                'slug' => 'teams',

                'description' => 'Users, teams, assignments and workforce access management.',

                'icon' => 'users',

                'sort_order' => 200,

                'permissions' => [

                    [

                        'name' => 'View Users',

                        'slug' => 'system.users.view',

                        'description' => 'View application users.',

                    ],

                    [

                        'name' => 'Create Users',

                        'slug' => 'system.users.create',

                        'description' => 'Create application users.',

                    ],

                    [

                        'name' => 'Update Users',

                        'slug' => 'system.users.update',

                        'description' => 'Update application users.',

                    ],

                    [

                        'name' => 'Deactivate Users',

                        'slug' => 'system.users.deactivate',

                        'description' => 'Deactivate application users.',

                    ],

                    [

                        'name' => 'View Teams',

                        'slug' => 'system.teams.view',

                        'description' => 'View teams.',

                    ],

                    [

                        'name' => 'Create Teams',

                        'slug' => 'system.teams.create',

                        'description' => 'Create teams.',

                    ],

                    [

                        'name' => 'Update Teams',

                        'slug' => 'system.teams.update',

                        'description' => 'Update teams.',

                    ],

                    [

                        'name' => 'Manage User Roles',

                        'slug' => 'system.users.manage_roles',

                        'description' => 'Assign and revoke roles from users.',

                    ],

                ],

            ],

            [

                'name' => 'Roles & Permissions',

                'slug' => 'roles-permissions',

                'description' => 'Role, permission and authorization management.',

                'icon' => 'shield',

                'sort_order' => 210,

                'permissions' => [

                    [

                        'name' => 'View Roles',

                        'slug' => 'system.roles.view',

                        'description' => 'View system roles.',

                    ],

                    [

                        'name' => 'Create Roles',

                        'slug' => 'system.roles.create',

                        'description' => 'Create non-system roles.',

                    ],

                    [

                        'name' => 'Update Roles',

                        'slug' => 'system.roles.update',

                        'description' => 'Update non-system roles.',

                    ],

                    [

                        'name' => 'Delete Roles',

                        'slug' => 'system.roles.delete',

                        'description' => 'Delete eligible non-system roles.',

                    ],

                    [

                        'name' => 'Manage Role Permissions',

                        'slug' => 'system.roles.manage_permissions',

                        'description' => 'Assign and revoke permissions from roles.',

                    ],

                    [

                        'name' => 'View Permissions',

                        'slug' => 'system.permissions.view',

                        'description' => 'View system permissions.',

                    ],

                    [

                        'name' => 'Create Permissions',

                        'slug' => 'system.permissions.create',

                        'description' => 'Create eligible permissions.',

                    ],

                    [

                        'name' => 'Update Permissions',

                        'slug' => 'system.permissions.update',

                        'description' => 'Update eligible permissions.',

                    ],

                    [

                        'name' => 'Delete Permissions',

                        'slug' => 'system.permissions.delete',

                        'description' => 'Delete eligible permissions.',

                    ],

                ],

            ],

            [

                'name' => 'Settings',

                'slug' => 'settings',

                'description' => 'Application settings and configurable system behavior.',

                'icon' => 'settings',

                'sort_order' => 220,

                'permissions' => [

                    [

                        'name' => 'View Settings',

                        'slug' => 'system.settings.view',

                        'description' => 'View application settings.',

                    ],

                    [

                        'name' => 'Update Settings',

                        'slug' => 'system.settings.update',

                        'description' => 'Update application settings.',

                    ],

                ],

            ],

            [

                'name' => 'Activity Logs',

                'slug' => 'activity-logs',

                'description' => 'Immutable user, administrative and system activity audit logs.',

                'icon' => 'activity',

                'sort_order' => 225,

                'permissions' => [

                    [

                        'name' => 'View Activity Logs',

                        'slug' => 'system.activity_logs.view',

                        'description' => 'View immutable user, administrative and system activity audit logs.',

                    ],

                ],

            ],

            [

                'name' => 'Locations',

                'slug' => 'locations',

                'description' => 'Business locations, branches, stores, warehouses and operational hubs.',

                'icon' => 'map-pin',

                'sort_order' => 235,

                'permissions' => [

                    [

                        'name' => 'View Locations',

                        'slug' => 'system.locations.view',

                        'description' => 'View business locations and operational details.',

                    ],

                    [

                        'name' => 'Create Locations',

                        'slug' => 'system.locations.create',

                        'description' => 'Create business locations.',

                    ],

                    [

                        'name' => 'Update Locations',

                        'slug' => 'system.locations.update',

                        'description' => 'Update existing business locations.',

                    ],

                    [

                        'name' => 'Delete Locations',

                        'slug' => 'system.locations.delete',

                        'description' => 'Delete eligible business locations.',

                    ],

                    [

                        'name' => 'Manage Locations',

                        'slug' => 'system.locations.manage',

                        'description' => 'Manage location configuration and operational settings.',

                    ],

                ],

            ],

            [
                'name' => 'Inventory',
                'slug' => 'inventory',
                'description' => 'Inventory balances, stock movements, receipts, issues, adjustments and transfers.',
                'icon' => 'package',
                'sort_order' => 236,
                'permissions' => [
                    [
                        'name' => 'View Inventory',
                        'slug' => 'system.inventory.view',
                        'description' => 'View stock balances and stock movement history.',
                    ],
                    [
                        'name' => 'Receive Stock',
                        'slug' => 'system.inventory.receive',
                        'description' => 'Receive stock into a location.',
                    ],
                    [
                        'name' => 'Issue Stock',
                        'slug' => 'system.inventory.issue',
                        'description' => 'Issue stock from a location.',
                    ],
                    [
                        'name' => 'Adjust Stock',
                        'slug' => 'system.inventory.adjust',
                        'description' => 'Perform authorized stock adjustments.',
                    ],
                    [
                        'name' => 'Transfer Stock',
                        'slug' => 'system.inventory.transfer',
                        'description' => 'Transfer stock between locations.',
                    ],
                    [
                        'name' => 'Manage Inventory Reservations',
                        'slug' => 'system.inventory.reservations.manage',
                        'description' => 'Create, release, cancel and fulfill stock reservations.',
                    ],
                ],
            ],

            [
                'name' => 'Module Management',
                'slug' => 'modules',
                'description' => 'Manage application modules and their configuration.',
                'icon' => 'layers',
                'sort_order' => 240,
                'permissions' => [
                    [
                        'name' => 'View Modules',
                        'slug' => 'system.modules.view',
                        'description' => 'View application modules.',
                    ],
                    [
                        'name' => 'Create Modules',
                        'slug' => 'system.modules.create',
                        'description' => 'Create application modules.',
                    ],
                    [
                        'name' => 'Update Modules',
                        'slug' => 'system.modules.update',
                        'description' => 'Update application module configuration.',
                    ],
                ],
            ],

            [
                'name' => 'System',

                'slug' => 'system',

                'description' => 'System-level controls and protected application operations.',

                'icon' => 'server',

                'sort_order' => 230,

                'permissions' => [

                    [

                        'name' => 'View System',

                        'slug' => 'system.system.view',

                        'description' => 'View protected system information.',

                    ],

                    [

                        'name' => 'Manage System',

                        'slug' => 'system.system.manage',

                        'description' => 'Manage protected system configuration.',

                    ],

                    [

                        'name' => 'Lock System',

                        'slug' => 'system.system.lock',

                        'description' => 'Enable or disable system-wide maintenance lock.',

                    ],

                    [

                        'name' => 'View System Logs',

                        'slug' => 'system.system.logs.view',

                        'description' => 'View system and administrative logs.',

                    ],

                ],

            ],

        ];

    }

    /**
     * Synchronize all authorization permissions to the protected

     * Developer role.

     *

     * The Developer role is the supreme system role and therefore

     * receives every registered permission.

     *

     * @param  array<int, Permission>  $permissions
     */
    private function syncDeveloperRole(array $permissions): void
    {

        $developerRole = Role::updateOrCreate(

            ['slug' => 'developer'],

            [

                'name' => 'Developer',

                'description' => 'Supreme system role with unrestricted administrative access.',

                'is_system' => true,

                'is_active' => true,

            ],

        );

        $developerRole->permissions()->syncWithoutDetaching(
            collect($permissions)
                ->pluck('id')
                ->values()
                ->all(),
        );

    }
}
