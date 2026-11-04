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
        href: '/dashboard',
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
                href: '/orders',
                icon: 'orders',
                permission: 'system.orders.view',
            },
            {
                label: 'Sales Returns',
                href: '/sales-returns',
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
                href: '/products',
                icon: 'products',
                permission: 'system.products.view',
            },

            {
                label: 'Categories',
                href: '/product-categories',
                icon: 'chart-column-stacked',
                permission: 'system.products.categories.view',
            },

            {
                label: 'Brands',
                href: '/brands',
                icon: 'brand',
                permission: 'system.products.brands.view',
            },

            {
                label: 'Units',
                href: '/units',
                icon: 'combine',
                permission: 'system.products.units.view',
            },

            {
                label: 'Attributes',
                href: '/attributes',
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
                href: '/inventory',
                icon: 'boxes',
                permission: 'system.inventory.view',
            },
            {
                label: 'Low Stock',
                href: '/inventory/low-stock',
                icon: 'alert-triangle',
                permission: 'system.inventory.view',
            },
            {
                label: 'Stock Movements',
                href: '/inventory/movements',
                icon: 'history',
                permission: 'system.inventory.view',
            },
            {
                label: 'Stock Operations',
                href: '/inventory/operations',
                icon: 'settings-2',
                permission: 'system.inventory.view',
            },
            {
                label: 'Reservations',
                href: '/inventory/reservations',
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
                href: '/customers',
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
                href: '/sales-crm/leads',
                icon: 'users',
                permission: 'system.crm.leads.view',
            },
        ],
    },

    {
        label: 'B2B & Wholesale',
        icon: 'wholesale',
        href: '/b2b',
        permission: 'system.b2b.view',
    },

    {
        label: 'Pricing & Promotions',
        href: '/pricing',
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
                href: '/finance',
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
            { label: 'Procurement Overview', href: '/procurement', icon: 'layout-dashboard', permission: 'system.procurement.view' },
        ],
    },

    {
        label: 'Shipping & Delivery',
        icon: 'shipping',
        expandable: true,
        permission: 'system.shipping.view',
        children: [
            { label: 'Delivery Dashboard', href: '/shipping-delivery', icon: 'truck', permission: 'system.shipping.view' },
        ],
    },

    {
        label: 'Payments',
        href: '/payments',
        icon: 'payments',
        permission: 'system.payments.view',
    },

    {
        label: 'Warranty & Service',
        href: '/warranty-service',
        icon: 'service',
        permission: 'system.warranty.view',
    },

    {
        label: 'Website Management',
        icon: 'globe',
        expandable: true,
        permission: 'system.content.view',
        children: [
            { label: 'Content Pages', href: '/content/pages', icon: 'cms', permission: 'system.content.view' },
            { label: 'Media Library', href: '/content/media', icon: 'image', permission: 'system.content.view' },
            { label: 'Banner Management', href: '/content/banners', icon: 'image', permission: 'system.content.view' },
            { label: 'Reusable Sections', href: '/content/sections', icon: 'layout', permission: 'system.content.view' },
            { label: 'SEO Management', href: '/seo', icon: 'seo', permission: 'system.seo.view' },
        ],
    },
    {
        label: 'Marketing',
        icon: 'marketing',
        expandable: true,
        permission: 'system.marketing.view',
        children: [
            { label: 'Marketing Overview', href: '/marketing', icon: 'dashboard', permission: 'system.marketing.view' },
            { label: 'Campaigns', href: '/marketing/campaigns', icon: 'target', permission: 'system.marketing.view' },
            { label: 'Coupons & Offers', href: '/marketing/coupons', icon: 'tag', permission: 'system.marketing.view' },
        ],
    },
    {
        label: 'Analytics',
        icon: 'analytics',
        expandable: true,
        permission: 'system.analytics.view',
        children: [
            { label: 'Analytics Overview', href: '/analytics', icon: 'chart-column', permission: 'system.analytics.view' },
            { label: 'Sales Analytics', href: '/analytics/sales', icon: 'sales', permission: 'system.analytics.view' },
            { label: 'Website Analytics', href: '/analytics/website', icon: 'globe', permission: 'system.analytics.view' },
            { label: 'Marketing Analytics', href: '/analytics/marketing', icon: 'megaphone', permission: 'system.analytics.view' },
            { label: 'Reports', href: '/reports', icon: 'reports', permission: 'system.reports.view' },
        ],
    },

    {
        label: 'Team & Users',
        icon: 'users',
        expandable: true,

        children: [
            {
                label: 'Users',
                href: '/users',
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
                href: '/settings/roles-permissions',
                icon: 'users',
                permission: 'system.roles.view',
            },

            {
                label: 'System',
                href: '/settings/system',
                icon: 'settings',
                permission: 'system.settings.view',
            },

            {
                label: 'Locations',
                href: '/locations',
                icon: 'map-pin',
                permission: 'system.locations.view',
            },

            {
                label: 'Activity Logs',
                href: '/settings/activity-logs',
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
                href: '/developer/api-providers',
                icon: 'plug-zap',
                permission: 'system.api_providers.view',
            },
            {
                label: 'Module Management',
                href: '/developer/modules',
                icon: 'developer',
                permission: 'system.modules.view',
            },
        ],
    },
];
