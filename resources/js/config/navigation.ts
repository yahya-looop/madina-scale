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
        label: 'Shipping & Delivery',
        icon: 'shipping',
        expandable: true,
        permission: 'system.shipping.view',
    },

    {
        label: 'Payments',
        icon: 'payments',
        permission: 'system.payments.view',
    },

    {
        label: 'Warranty & Service',
        icon: 'service',
        permission: 'system.warranty.view',
    },

    {
        label: 'Marketing',
        icon: 'marketing',
        permission: 'system.marketing.view',
    },

    {
        label: 'Analytics',
        icon: 'analytics',
        permission: 'system.analytics.view',
    },

    {
        label: 'Reports',
        icon: 'reports',
        permission: 'system.reports.view',
    },

    {
        label: 'Content (CMS)',
        icon: 'cms',
        permission: 'system.content.view',
    },

    {
        label: 'SEO',
        icon: 'seo',
        permission: 'system.seo.view',
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
                label: 'Module Management',
                href: '/developer/modules',
                icon: 'developer',
                permission: 'system.modules.view',
            },
        ],
    },
];
