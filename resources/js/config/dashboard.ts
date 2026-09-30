export type Kpi = {
    label: string;
    value: string;
    change: string;
    positive: boolean;
    icon: string;
    tone: 'green' | 'blue' | 'purple' | 'orange';
    spark: number[];
};

export const dashboardKpis: Kpi[] = [
    {
        label: 'Total Revenue',
        value: '৳ 12,48,500',
        change: '12.5%',
        positive: true,
        icon: 'revenue',
        tone: 'green',
        spark: [22, 30, 25, 38, 34, 46, 41, 57, 52, 68],
    },
    {
        label: 'Total Orders',
        value: '1,482',
        change: '8.2%',
        positive: true,
        icon: 'orders',
        tone: 'blue',
        spark: [20, 27, 24, 35, 31, 43, 39, 52, 48, 64],
    },
    {
        label: 'Total Customers',
        value: '892',
        change: '15.4%',
        positive: true,
        icon: 'customers',
        tone: 'purple',
        spark: [18, 28, 24, 37, 33, 46, 42, 54, 50, 65],
    },
    {
        label: 'Conversion Rate',
        value: '3.24%',
        change: '0.8%',
        positive: true,
        icon: 'conversion',
        tone: 'orange',
        spark: [24, 34, 29, 44, 40, 49, 45, 58, 53, 68],
    },
];

export const orderStatus = [
    { label: 'Pending', value: 220, percent: '14.9%', tone: 'warning' },
    { label: 'Processing', value: 340, percent: '22.9%', tone: 'blue' },
    { label: 'Shipped', value: 480, percent: '32.4%', tone: 'sky' },
    { label: 'Delivered', value: 380, percent: '25.6%', tone: 'success' },
    { label: 'Cancelled', value: 62, percent: '4.2%', tone: 'danger' },
];

export const inventoryStatus = [
    { label: 'In Stock', value: 892, percent: '71.7%', tone: 'blue' },
    { label: 'Low Stock', value: 210, percent: '16.9%', tone: 'warning' },
    { label: 'Out of Stock', value: 143, percent: '11.5%', tone: 'danger' },
];

export const topProducts = [
    ['Digital Weighing Scale 300kg', '420', '৳ 12,50,000', '18%'],
    ['Platform Scale 500kg', '380', '৳ 8,90,000', '12%'],
    ['Counting Scale 30kg', '310', '৳ 6,42,050', '8%'],
    ['Price Computing Scale', '280', '৳ 3,80,000', '15%'],
    ['Crane Scale 1 Ton', '240', '৳ 3,24,000', '10%'],
];

export const recentOrders = [
    ['#MS-102938', 'Rahim Ahmed', '৳ 12,500', 'Pending', '2m'],
    ['#MS-102937', 'Karim Uddin', '৳ 18,900', 'Processing', '12m'],
    ['#MS-102936', 'Nusrat Jahan', '৳ 8,750', 'Shipped', '28m'],
    ['#MS-102935', 'Abdul Kader', '৳ 24,990', 'Delivered', '1h'],
    ['#MS-102934', 'Mehedi Hasan', '৳ 6,900', 'Cancelled', '2h'],
];

export const tasks = [
    ['Confirm 12 pending orders', '3m ago'],
    ['Reply to 6 customer chats', '12m ago'],
    ['Update product stock', '25m ago'],
    ['Review return request', '1h ago'],
    ['Check payment verifications', '2h ago'],
];

export const trafficSources = [
    ['Direct', '32.4%'],
    ['Google', '28.1%'],
    ['Facebook', '18.5%'],
    ['TikTok', '12.3%'],
    ['Others', '8.7%'],
];

export const marketingPerformance = [
    ['Meta Ads', '৳ 45,680', '৳ 2,45,000', '4.2'],
    ['Google Ads', '৳ 32,450', '৳ 1,23,900', '3.8'],
    ['TikTok Ads', '৳ 18,290', '৳ 58,700', '3.1'],
    ['Email Marketing', '৳ 8,750', '৳ 28,500', '2.4'],
    ['SMS Campaign', '৳ 4,320', '৳ 12,900', '2.1'],
];

export const chats = [
    ['Rahim Ahmed', '300kg digital scale price koto?', '2m', 'customer'],
    ['Nusrat Jahan', 'Do you have 500kg model?', '5m', 'customer'],
    ['Karim Uddin', 'Delivery time koto?', '12m', 'team'],
    ['Tania Akter', 'Warranty cover kore?', '25m', 'customer'],
    ['Sales Team', 'New stock arrived.', '36m', 'team'],
    ['Visitor #1283', 'Is it available?', '1h', 'customer'],
    ['Admin', 'Updated product prices.', '2h', 'team'],
];
