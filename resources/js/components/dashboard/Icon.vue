<script setup lang="ts">
import { computed, type Component } from "vue";
import * as LucideIcons from "lucide-vue-next";

type IconComponent = Component;

/**
 * Core application icon aliases.
 * Keep these semantic names stable so navigation/components do not depend
 * directly on Lucide component names.
 */
const iconAliases: Record<string, string> = {
    "dashboard": "LayoutDashboard",
    "search": "Search",
    "plus": "Plus",
    "bell": "Bell",
    "calendar": "CalendarDays",
    "sun": "Sun",
    "moon": "Moon",
    "settings": "Settings",
    "chevron-down": "ChevronDown",
    "chevron-left": "ChevronLeft",
    "chevron-right": "ChevronRight",
    "expand": "Maximize2",
    "menu": "Menu",
    "check": "Check",
    "check-circle": "CheckCircle2",
    "info": "Info",
    "dots": "MoreHorizontal",
    "chat": "MessageCircle",
    "support": "Headphones",
    "users": "Users",
    "customers": "Users",
    "user": "User",
    "user-plus": "UserPlus",
    "cart": "ShoppingCart",
    "orders": "ShoppingCart",
    "sales": "ShoppingCart",
    "products": "Package",
    "inventory": "Package",
    "wholesale": "BriefcaseBusiness",
    "pricing": "Tag",
    "shipping": "Truck",
    "payments": "CreditCard",
    "revenue": "CreditCard",
    "service": "Clock3",
    "marketing": "Megaphone",
    "analytics": "BarChart3",
    "conversion": "Activity",
    "map-pin": "MapPin",
    "reports": "FileBarChart",
    "cms": "FileText",
    "seo": "FileText",
    "developer": "FileText",
    "lock": "Lock",
    "unlock": "ShieldCheck",
    "security": "Shield",
    "permissions": "ShieldCheck",
    "roles": "ShieldCheck",
    "login": "LogIn",
    "last-login": "LogIn",
    "refresh": "RefreshCw",
    "edit": "Pencil",
    "pencil": "Pencil",
    "edit-3": "Edit3",
    "view": "Eye",
    "eye": "Eye",
    "delete": "Trash2",
    "trash": "Trash2",
    "close": "X",
    "x": "X",
    "back": "ArrowLeft",
    "arrow-left": "ArrowLeft",
    "arrow-right": "ArrowRight",
    "save": "CheckCircle2",
    "email": "Mail",
    "mail": "Mail",
    "password": "KeyRound",
    "key": "KeyRound",
    "database": "Database",
    "folder": "Folder",
    "help": "CircleHelp",
};

/**
 * 200 additional future-ready icons selected for Madina Scale.
 * These cover ERP, catalog, inventory, sales, finance, analytics,
 * marketing, communication, security, files, media, developer tools,
 * layout and system states.
 */
const futureIconAliases: Record<string, string> = {
    "dashboard-main": "LayoutDashboard",
    "home": "House",
    "home-modern": "House",
    "activity-log": "Activity",
    "system-settings": "Settings",
    "general-settings": "Settings2",
    "search-check": "SearchCheck",
    "search-x": "SearchX",
    "filter": "Filter",
    "filter-x": "FilterX",
    "sort-ascending": "ArrowUpAZ",
    "sort-descending": "ArrowDownAZ",
    "add": "Plus",
    "remove": "Minus",
    "create": "PlusCircle",
    "hide": "EyeOff",
    "next": "ArrowRight",
    "success": "CircleCheck",
    "warning": "TriangleAlert",
    "danger": "CircleAlert",
    "error": "CircleX",
    "users-group": "UsersRound",
    "user-profile": "UserRound",
    "user-create": "UserRoundPlus",
    "user-active": "UserCheck",
    "user-inactive": "UserX",
    "user-settings": "UserCog",
    "customer-profile": "Contact",
    "team": "UsersRound",
    "role": "BadgeCheck",
    "developer-tools": "Code2",
    "audit": "FileClock",
    "activity-history": "History",
    "api-key": "KeySquare",
    "fingerprint": "Fingerprint",
    "logout": "LogOut",
    "email-open": "MailOpen",
    "phone": "Phone",
    "phone-call": "PhoneCall",
    "website": "Globe",
    "address": "MapPinHouse",
    "location": "MapPin",
    "map": "Map",
    "navigation": "Navigation",
    "route": "Route",
    "product": "Package",
    "product-add": "PackagePlus",
    "product-edit": "PackageCheck",
    "product-remove": "PackageMinus",
    "product-delete": "PackageX",
    "product-box": "Box",
    "products-boxes": "Boxes",
    "category": "ChartColumnStacked",
    "brand": "Badge",
    "brand-ad": "Badge",
    "unit": "Ruler",
    "attribute": "BookHeart",
    "attribute-value": "Tags",
    "tag": "Tag",
    "tags": "Tags",
    "barcode": "Barcode",
    "qr-code": "QrCode",
    "scan": "ScanLine",
    "stock": "PackageCheck",
    "stock-in": "PackagePlus",
    "stock-out": "PackageMinus",
    "stock-alert": "PackageX",
    "warehouse": "Warehouse",
    "store": "Store",
    "storefront": "Store",
    "shelf": "Layers",
    "box": "Box",
    "boxes": "Boxes",
    "container": "Container",
    "pallet": "Package",
    "truck": "Truck",
    "forklift": "Forklift",
    "checkout": "CreditCard",
    "invoice": "ReceiptText",
    "receipt": "Receipt",
    "payment": "CreditCard",
    "wallet": "Wallet",
    "bank": "Landmark",
    "money": "Banknote",
    "cash": "HandCoins",
    "coins": "Coins",
    "calculator": "Calculator",
    "discount": "BadgePercent",
    "promotion": "BadgeDollarSign",
    "percentage": "Percent",
    "chart": "ChartNoAxesCombined",
    "bar-chart": "ChartColumn",
    "line-chart": "ChartLine",
    "pie-chart": "ChartPie",
    "growth": "TrendingUp",
    "decline": "TrendingDown",
    "kpi": "Gauge",
    "report": "FileBarChart",
    "campaign": "Megaphone",
    "announcement": "BellRing",
    "message": "MessageCircle",
    "messages": "Messages",
    "send": "Send",
    "attachment": "Paperclip",
    "calendar-days": "CalendarDays",
    "calendar-add": "CalendarPlus",
    "calendar-check": "CalendarCheck",
    "calendar-remove": "CalendarX",
    "clock": "Clock3",
    "timer": "Timer",
    "history": "History",
    "schedule": "AlarmClock",
    "file": "File",
    "file-text": "FileText",
    "file-add": "FilePlus",
    "file-edit": "FilePen",
    "file-delete": "FileMinus",
    "file-check": "FileCheck",
    "file-error": "FileX",
    "files": "Files",
    "spreadsheet": "FileSpreadsheet",
    "document": "FileText",
    "folder-open": "FolderOpen",
    "folder-add": "FolderPlus",
    "folder-remove": "FolderMinus",
    "clipboard": "Clipboard",
    "clipboard-check": "ClipboardCheck",
    "clipboard-list": "ClipboardList",
    "copy": "Copy",
    "duplicate": "CopyCheck",
    "image": "Image",
    "images": "Images",
    "camera": "Camera",
    "video": "Video",
    "media": "Images",
    "upload": "Upload",
    "download": "Download",
    "cloud-upload": "CloudUpload",
    "cloud-download": "CloudDownload",
    "printer": "Printer",
    "monitor": "Monitor",
    "laptop": "Laptop",
    "mobile": "Smartphone",
    "tablet": "Tablet",
    "server": "Server",
    "hard-drive": "HardDrive",
    "network": "Network",
    "wifi": "Wifi",
    "code": "Code2",
    "code-xml": "CodeXml",
    "terminal": "Terminal",
    "bug": "Bug",
    "bug-fix": "BugOff",
    "api": "Webhook",
    "webhook": "Webhook",
    "link": "Link",
    "external-link": "ExternalLink",
    "unlink": "Unlink",
    "tools": "Wrench",
    "maintenance": "Wrench",
    "sliders": "SlidersHorizontal",
    "layers": "Layers",
    "component": "Component",
    "blocks": "Blocks",
    "workflow": "Workflow",
    "branch": "GitBranch",
    "puzzle": "Puzzle",
    "palette": "Palette",
    "favorite": "Heart",
    "star": "Star",
    "bookmark": "Bookmark",
    "flag": "Flag",
    "pin": "Pin",
    "sparkles": "Sparkles",
    "magic": "WandSparkles",
    "theme-light": "Sun",
    "theme-dark": "Moon",
    "theme-system": "Monitor",
    "sunrise": "Sunrise",
    "sunset": "Sunset",
    "cloud": "Cloud",
    "cloud-sun": "CloudSun",
    "cloud-rain": "CloudRain",
    "lightning": "Zap",
    "battery": "Battery",
    "battery-charging": "BatteryCharging",
    "power": "Power",
    "power-off": "PowerOff",
    "minimize": "Minimize2",
    "fullscreen": "Fullscreen",
    "panel-left": "PanelLeft",
    "panel-right": "PanelRight",
    "sidebar": "PanelLeft",
    "grid": "Grid2X2",
    "layout-grid": "LayoutGrid",
    "layout-list": "List",
    "chevron-up": "ChevronUp",
    "more-horizontal": "MoreHorizontal",
    "more-vertical": "MoreVertical",
    "arrow-up": "ArrowUp",
};

Object.assign(iconAliases, futureIconAliases);

function toPascalCase(value: string): string {
    return value
        .trim()
        .split(/[-_\s]+/)
        .filter(Boolean)
        .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
        .join("");
}

function resolveIcon(name: string): IconComponent {
    const normalizedName = name.trim();

    if (!normalizedName) {
        return LucideIcons.CircleHelp;
    }

    const aliasName = iconAliases[normalizedName] ?? normalizedName;
    const componentName = toPascalCase(aliasName);
    const lucideIcon = (LucideIcons as Record<string, unknown>)[componentName];

    if (typeof lucideIcon === "object" || typeof lucideIcon === "function") {
        return lucideIcon as IconComponent;
    }

    return LucideIcons.CircleHelp;
}

const props = withDefaults(
    defineProps<{
        name: string;
        size?: number;
        strokeWidth?: number;
    }>(),
    {
        size: 20,
        strokeWidth: 1.8,
    },
);

const icon = computed(() => resolveIcon(props.name));
</script>

<template>
    <component
        :is="icon"
        :size="size"
        :stroke-width="strokeWidth"
        aria-hidden="true"
        focusable="false"
    />
</template>
