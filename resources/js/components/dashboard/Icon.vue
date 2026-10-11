<script setup lang="ts">
import { computed, type Component } from "vue";
import * as LucideIcons from "lucide-vue-next";

type IconRegistry = Record<string, unknown>;
type IconComponent = Component;

/**
 * Central icon resolver for Madina Scale.
 *
 * Supports semantic aliases, kebab-case names, PascalCase names, and
 * case-insensitive lookup. Unknown names safely render CircleHelp.
 * Keep this component as the only place that resolves application icons.
 */
const iconAliases: Record<string, string> = {
    dashboard: "LayoutDashboard",
    "dashboard-main": "LayoutDashboard",
    home: "House",
    "home-modern": "House",
    search: "Search",
    plus: "Plus",
    add: "Plus",
    remove: "Minus",
    create: "PlusCircle",
    bell: "Bell",
    calendar: "CalendarDays",
    "calendar-days": "CalendarDays",
    sun: "Sun",
    moon: "Moon",
    settings: "Settings",
    "system-settings": "Settings",
    "general-settings": "Settings2",
    "chevron-down": "ChevronDown",
    "chevron-up": "ChevronUp",
    "chevron-left": "ChevronLeft",
    "chevron-right": "ChevronRight",
    expand: "Maximize2",
    fullscreen: "Fullscreen",
    minimize: "Minimize2",
    menu: "Menu",
    check: "Check",
    "check-circle": "CheckCircle2",
    success: "CircleCheck",
    info: "Info",
    warning: "TriangleAlert",
    danger: "CircleAlert",
    error: "CircleX",
    dots: "MoreHorizontal",
    "more-horizontal": "MoreHorizontal",
    "more-vertical": "MoreVertical",
    chat: "MessageCircle",
    message: "MessageCircle",
    messages: "Messages",
    support: "Headphones",
    users: "Users",
    customers: "Users",
    "users-group": "UsersRound",
    team: "UsersRound",
    user: "User",
    "user-plus": "UserPlus",
    "user-profile": "UserRound",
    "user-create": "UserRoundPlus",
    "user-active": "UserCheck",
    "user-inactive": "UserX",
    "user-settings": "UserCog",
    "customer-profile": "Contact",
    cart: "ShoppingCart",
    orders: "ShoppingCart",
    sales: "ShoppingCart",
    products: "Package",
    product: "Package",
    inventory: "Package",
    "product-add": "PackagePlus",
    "product-edit": "PackageCheck",
    "product-remove": "PackageMinus",
    "product-delete": "PackageX",
    "product-box": "Box",
    "products-boxes": "Boxes",
    wholesale: "BriefcaseBusiness",
    pricing: "Tag",
    shipping: "Truck",
    payments: "CreditCard",
    payment: "CreditCard",
    revenue: "CreditCard",
    checkout: "CreditCard",
    invoice: "ReceiptText",
    receipt: "Receipt",
    wallet: "Wallet",
    bank: "Landmark",
    money: "Banknote",
    cash: "HandCoins",
    coins: "Coins",
    calculator: "Calculator",
    discount: "BadgePercent",
    promotion: "BadgeDollarSign",
    percentage: "Percent",
    service: "Clock3",
    clock: "Clock3",
    timer: "Timer",
    schedule: "AlarmClock",
    marketing: "Megaphone",
    campaign: "Megaphone",
    announcement: "BellRing",
    analytics: "BarChart3",
    chart: "ChartNoAxesCombined",
    "bar-chart": "ChartColumn",
    "line-chart": "ChartLine",
    "pie-chart": "ChartPie",
    growth: "TrendingUp",
    decline: "TrendingDown",
    kpi: "Gauge",
    conversion: "Activity",
    "activity-log": "Activity",
    "activity-history": "History",
    "map-pin": "MapPin",
    location: "MapPin",
    address: "MapPinHouse",
    map: "Map",
    navigation: "Navigation",
    route: "Route",
    reports: "FileBarChart",
    report: "FileBarChart",
    cms: "FileText",
    seo: "FileText",
    developer: "FileText",
    "developer-tools": "Code2",
    lock: "Lock",
    unlock: "ShieldCheck",
    security: "Shield",
    permissions: "ShieldCheck",
    roles: "ShieldCheck",
    role: "BadgeCheck",
    login: "LogIn",
    logout: "LogOut",
    "last-login": "LogIn",
    audit: "FileClock",
    refresh: "RefreshCw",
    edit: "Pencil",
    pencil: "Pencil",
    "edit-3": "Edit3",
    view: "Eye",
    eye: "Eye",
    hide: "EyeOff",
    delete: "Trash2",
    trash: "Trash2",
    close: "X",
    x: "X",
    back: "ArrowLeft",
    "arrow-left": "ArrowLeft",
    "arrow-right": "ArrowRight",
    "arrow-up": "ArrowUp",
    next: "ArrowRight",
    save: "CheckCircle2",
    email: "Mail",
    mail: "Mail",
    "email-open": "MailOpen",
    phone: "Phone",
    "phone-call": "PhoneCall",
    password: "KeyRound",
    key: "KeyRound",
    "api-key": "KeySquare",
    fingerprint: "Fingerprint",
    database: "Database",
    folder: "Folder",
    "folder-open": "FolderOpen",
    "folder-add": "FolderPlus",
    "folder-remove": "FolderMinus",
    file: "File",
    "file-text": "FileText",
    "file-add": "FilePlus",
    "file-edit": "FilePen",
    "file-delete": "FileMinus",
    "file-check": "FileCheck",
    "file-error": "FileX",
    files: "Files",
    spreadsheet: "FileSpreadsheet",
    document: "FileText",
    clipboard: "Clipboard",
    "clipboard-check": "ClipboardCheck",
    "clipboard-list": "ClipboardList",
    copy: "Copy",
    duplicate: "CopyCheck",
    image: "Image",
    images: "Images",
    camera: "Camera",
    video: "Video",
    media: "Images",
    upload: "Upload",
    download: "Download",
    "cloud-upload": "CloudUpload",
    "cloud-download": "CloudDownload",
    printer: "Printer",
    monitor: "Monitor",
    laptop: "Laptop",
    mobile: "Smartphone",
    tablet: "Tablet",
    server: "Server",
    "hard-drive": "HardDrive",
    network: "Network",
    wifi: "Wifi",
    code: "Code2",
    "code-xml": "CodeXml",
    terminal: "Terminal",
    bug: "Bug",
    "bug-fix": "BugOff",
    api: "Webhook",
    webhook: "Webhook",
    link: "Link",
    "external-link": "ExternalLink",
    unlink: "Unlink",
    tools: "Wrench",
    maintenance: "Wrench",
    filter: "Filter",
    "filter-x": "FilterX",
    "search-check": "SearchCheck",
    "search-x": "SearchX",
    "sort-ascending": "ArrowUpAZ",
    "sort-descending": "ArrowDownAZ",
    sliders: "SlidersHorizontal",
    layers: "Layers",
    component: "Component",
    blocks: "Blocks",
    workflow: "Workflow",
    branch: "GitBranch",
    puzzle: "Puzzle",
    palette: "Palette",
    favorite: "Heart",
    star: "Star",
    bookmark: "Bookmark",
    flag: "Flag",
    pin: "Pin",
    sparkles: "Sparkles",
    magic: "WandSparkles",
    "theme-light": "Sun",
    "theme-dark": "Moon",
    "theme-system": "Monitor",
    sunrise: "Sunrise",
    sunset: "Sunset",
    cloud: "Cloud",
    "cloud-sun": "CloudSun",
    "cloud-rain": "CloudRain",
    lightning: "Zap",
    battery: "Battery",
    "battery-charging": "BatteryCharging",
    power: "Power",
    "power-off": "PowerOff",
    "panel-left": "PanelLeft",
    "panel-right": "PanelRight",
    sidebar: "PanelLeft",
    grid: "Grid2X2",
    "layout-grid": "LayoutGrid",
    "layout-list": "List",
    combine: "Combine",
    "chart-column-stacked": "ChartColumnStacked",
    "book-heart": "BookHeart",
    category: "ChartColumnStacked",
    brand: "Badge",
    "brand-ad": "Badge",
    unit: "Ruler",
    attribute: "BookHeart",
    "attribute-value": "Tags",
    tag: "Tag",
    tags: "Tags",
    barcode: "Barcode",
    "qr-code": "QrCode",
    scan: "ScanLine",
    stock: "PackageCheck",
    "stock-in": "PackagePlus",
    "stock-out": "PackageMinus",
    "stock-alert": "PackageX",
    warehouse: "Warehouse",
    store: "Store",
    storefront: "Store",
    shelf: "Layers",
    box: "Box",
    boxes: "Boxes",
    container: "Container",
    pallet: "Package",
    truck: "Truck",
    forklift: "Forklift",
};

function normalizeName(value: string): string {
    return value
        .trim()
        .replace(/([a-z0-9])([A-Z])/g, "$1-$2")
        .replace(/([A-Z])([A-Z][a-z])/g, "$1-$2")
        .replace(/[_\s]+/g, "-")
        .replace(/-+/g, "-")
        .replace(/^-|-$/g, "")
        .toLowerCase();
}

function toPascalCase(value: string): string {
    return value
        .split(/[-_\s]+/)
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join("");
}

const registry = LucideIcons as unknown as IconRegistry;
const fallbackIcon = registry.CircleHelp as IconComponent;

function isVueComponent(value: unknown): value is IconComponent {
    return (
        (typeof value === "object" && value !== null) ||
        typeof value === "function"
    );
}

function resolveIcon(name: string | null | undefined): IconComponent {
    const normalizedName = typeof name === "string" ? normalizeName(name) : "";

    if (!normalizedName) {
        return fallbackIcon;
    }

    const aliasedName = iconAliases[normalizedName] ?? normalizedName;
    const componentName = toPascalCase(aliasedName);
    const candidate = registry[componentName];

    // Only render actual exports; never pass an undefined component to Vue.
    if (isVueComponent(candidate)) {
        return candidate;
    }

    return fallbackIcon;
}

const props = withDefaults(
    defineProps<{
        name?: string | null;
        size?: number;
        strokeWidth?: number;
        absoluteStrokeWidth?: boolean;
    }>(),
    {
        name: "",
        size: 20,
        strokeWidth: 1.8,
        absoluteStrokeWidth: false,
    },
);

const icon = computed(() => resolveIcon(props.name));
const safeSize = computed(() =>
    Number.isFinite(props.size) && props.size > 0 ? props.size : 20,
);
const safeStrokeWidth = computed(() =>
    Number.isFinite(props.strokeWidth) && props.strokeWidth > 0
        ? props.strokeWidth
        : 1.8,
);
</script>

<template>
    <component
        :is="icon"
        :size="safeSize"
        :stroke-width="safeStrokeWidth"
        :absolute-stroke-width="absoluteStrokeWidth"
        aria-hidden="true"
        focusable="false"
    />
</template>
