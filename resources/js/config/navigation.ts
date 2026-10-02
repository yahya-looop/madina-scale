export type NavigationItem = {
    label: string;
    href?: string;
    icon: string;
    badge?: string;
    expandable?: boolean;
    children?: NavigationItem[];
};

export const primaryNavigation: NavigationItem[] = [
    { label: 'Dashboard', href: '/dashboard', icon: 'dashboard' },
    { label: 'Sales', icon: 'sales', expandable: true },
    { label: 'Orders', icon: 'orders', badge: '18' },
    { label: 'Products', icon: 'products', expandable: true },
    { label: 'Inventory', icon: 'inventory', expandable: true },
    { label: 'Customers', icon: 'customers', expandable: true },
    { label: 'B2B & Wholesale', icon: 'wholesale' },
    { label: 'Pricing & Promotions', icon: 'pricing', expandable: true },
    { label: 'Shipping & Delivery', icon: 'shipping', expandable: true },
    { label: 'Payments', icon: 'payments' },
    { label: 'Warranty & Service', icon: 'service' },
    { label: 'Marketing', icon: 'marketing' },
    { label: 'Analytics', icon: 'analytics' },
    { label: 'Reports', icon: 'reports' },
    { label: 'Content (CMS)', icon: 'cms' },
    { label: 'SEO', icon: 'seo' },
    { label: 'Team & Users', icon: 'users', expandable: true },
    {
        label: 'Settings',
        icon: 'settings',
        expandable: true,
        children: [
            {
                label: 'Role & Permissions',
                href: '/settings/roles-permissions',
                icon: 'users',
            },
        ],
    },
    { label: 'Developer', icon: 'developer' },
];
