export type NavigationItem = {
    label: string;
    href?: string;
    icon: string;
    badge?: string;
    expandable?: boolean;
    permission?: string;
    developerOnly?: boolean;
    children?: NavigationItem[];
};

export const primaryNavigation: NavigationItem[] = [
    {
        label: 'Dashboard',
        href: '/backend/dashboard',
        icon: 'dashboard',
        permission: 'system.dashboard.view',
    },

    {
        label: 'Sales',
        icon: 'sales',
        expandable: true,
        permission: 'system.sales.view',
        children: [
            {
                label: 'Sales Orders',
                href: '/backend/orders',
                icon: 'orders',
                permission: 'system.orders.view',
            },
            {
                label: 'Sales Returns',
                href: '/backend/sales-returns',
                icon: 'undo-2',
                permission: 'system.sales_returns.view',
            },
        ],
    },

    {
        label: 'Product Management',
        icon: 'products',
        expandable: true,
        permission: 'system.products.view',

        children: [
            {
                label: 'Products',
                href: '/backend/products',
                icon: 'products',
                permission: 'system.products.view',
            },

            {
                label: 'Categories',
                href: '/backend/product-categories',
                icon: 'chart-column-stacked',
                permission: 'system.products.categories.view',
            },

            {
                label: 'Brands',
                href: '/backend/brands',
                icon: 'brand',
                permission: 'system.products.brands.view',
            },

            {
                label: 'Units',
                href: '/backend/units',
                icon: 'combine',
                permission: 'system.products.units.view',
            },

            {
                label: 'Attributes',
                href: '/backend/attributes',
                icon: 'book-heart',
                permission: 'system.products.attributes.view',
            },
        ],
    },

    {
        label: 'Inventory',
        icon: 'inventory',
        expandable: true,
        permission: 'system.inventory.view',

        children: [
            {
                label: 'Stock Overview',
                href: '/backend/inventory',
                icon: 'boxes',
                permission: 'system.inventory.view',
            },
            {
                label: 'Low Stock',
                href: '/backend/inventory/low-stock',
                icon: 'alert-triangle',
                permission: 'system.inventory.view',
            },
            {
                label: 'Stock Movements',
                href: '/backend/inventory/movements',
                icon: 'history',
                permission: 'system.inventory.view',
            },
            {
                label: 'Stock Operations',
                href: '/backend/inventory/operations',
                icon: 'settings-2',
                permission: 'system.inventory.view',
            },
            {
                label: 'Reservations',
                href: '/backend/inventory/reservations',
                icon: 'clipboard-list',
                permission: 'system.inventory.view',
            },
        ],
    },

    {
        label: 'Customers',
        icon: 'customers',
        expandable: true,
        permission: 'system.customers.view',
        children: [
            {
                label: 'Customer List',
                href: '/backend/customers',
                icon: 'customers',
                permission: 'system.customers.view',
            },
        ],
    },

    {
        label: 'Sales CRM',
        icon: 'customers',
        expandable: true,
        permission: 'system.crm.leads.view',
        children: [
            {
                label: 'Lead Management',
                href: '/backend/sales-crm/leads',
                icon: 'users',
                permission: 'system.crm.leads.view',
            },
        ],
    },

    {
        label: 'B2B & Wholesale',
        icon: 'wholesale',
        href: '/backend/b2b',
        permission: 'system.b2b.view',
    },

    {
        label: 'Pricing & Promotions',
        href: '/backend/pricing',
        icon: 'pricing',
        permission: 'system.pricing.view',
    },


    {
        label: 'Accounts & Finance',
        icon: 'wallet',
        expandable: true,
        permission: 'system.finance.view',
        children: [
            {
                label: 'Finance Overview',
                href: '/backend/finance',
                icon: 'chart-no-axes-combined',
                permission: 'system.finance.view',
            },
        ],
    },

    {
        label: 'Procurement & Suppliers',
        icon: 'shopping-cart',
        expandable: true,
        permission: 'system.procurement.view',
        children: [
            { label: 'Procurement Overview', href: '/backend/procurement', icon: 'layout-dashboard', permission: 'system.procurement.view' },
        ],
    },

    {
        label: 'Shipping & Delivery',
        icon: 'shipping',
        expandable: true,
        permission: 'system.shipping.view',
        children: [
            { label: 'Delivery Dashboard', href: '/backend/shipping-delivery', icon: 'truck', permission: 'system.shipping.view' },
        ],
    },

    {
        label: 'Payments',
        href: '/backend/payments',
        icon: 'payments',
        permission: 'system.payments.view',
    },

    {
        label: 'Warranty & Service',
        href: '/backend/warranty-service',
        icon: 'service',
        permission: 'system.warranty.view',
    },

    {
        label: 'Website Management',
        icon: 'globe',
        expandable: true,
        permission: 'system.content.view',
        children: [
            { label: 'Content Pages', href: '/backend/content/pages', icon: 'cms', permission: 'system.content.view' },
            { label: 'Media Library', href: '/backend/content/media', icon: 'image', permission: 'system.content.view' },
            { label: 'Banner Management', href: '/backend/content/banners', icon: 'image', permission: 'system.content.view' },
            { label: 'Reusable Sections', href: '/backend/content/sections', icon: 'layout', permission: 'system.content.view' },
            { label: 'SEO Management', href: '/backend/seo', icon: 'seo', permission: 'system.seo.view' },
        ],
    },
    {
        label: 'Marketing',
        icon: 'marketing',
        expandable: true,
        permission: 'system.marketing.view',
        children: [
            { label: 'Marketing Overview', href: '/backend/marketing', icon: 'dashboard', permission: 'system.marketing.view' },
            { label: 'Campaigns', href: '/backend/marketing/campaigns', icon: 'target', permission: 'system.marketing.view' },
            { label: 'Coupons & Offers', href: '/backend/marketing/coupons', icon: 'tag', permission: 'system.marketing.view' },
        ],
    },
    {
        label: 'Analytics',
        icon: 'analytics',
        expandable: true,
        permission: 'system.analytics.view',
        children: [
            { label: 'Analytics Overview', href: '/backend/analytics', icon: 'chart-column', permission: 'system.analytics.view' },
            { label: 'Sales Analytics', href: '/backend/analytics/sales', icon: 'sales', permission: 'system.analytics.view' },
            { label: 'Website Analytics', href: '/backend/analytics/website', icon: 'globe', permission: 'system.analytics.view' },
            { label: 'Marketing Analytics', href: '/backend/analytics/marketing', icon: 'megaphone', permission: 'system.analytics.view' },
            { label: 'Reports', href: '/backend/reports', icon: 'reports', permission: 'system.reports.view' },
        ],
    },

    {
        label: 'Team & Users',
        icon: 'users',
        expandable: true,

        children: [
            {
                label: 'Users',
                href: '/backend/users',
                icon: 'users',
                permission: 'system.users.view',
            },
        ],
    },

    {
        label: 'Settings',
        icon: 'settings',
        expandable: true,

        children: [
            {
                label: 'Role & Permissions',
                href: '/backend/settings/roles-permissions',
                icon: 'users',
                permission: 'system.roles.view',
            },

            {
                label: 'System',
                href: '/backend/settings/system',
                icon: 'settings',
                permission: 'system.settings.view',
            },

            {
                label: 'Locations',
                href: '/backend/locations',
                icon: 'map-pin',
                permission: 'system.locations.view',
            },

            {
                label: 'Activity Logs',
                href: '/backend/settings/activity-logs',
                icon: 'activity',
                permission: 'system.activity_logs.view',
            },
        ],
    },

    {
        label: 'Developer',
        icon: 'developer',
        expandable: true,
        permission: 'system.system.view',
        developerOnly: true,

        children: [
            {
                label: 'API & Providers',
                href: '/backend/developer/api-providers',
                icon: 'plug-zap',
                permission: 'system.api_providers.view',
            },
            {
                label: 'Module Management',
                href: '/backend/developer/modules',
                icon: 'developer',
                permission: 'system.modules.view',
            },
        ],
    },
];
