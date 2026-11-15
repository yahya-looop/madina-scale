<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Icon from '@/components/dashboard/Icon.vue';
import StorefrontImage from '@/components/storefront/StorefrontImage.vue';

interface StoreProduct {
    id: number;
    name: string;
    slug: string;
    short_description: string | null;
    category: string | null;
    brand: string | null;
    unit: string | null;
    image_url: string | null;
    price: string | null;
    regular_price: string | null;
    has_flash_sale: boolean;
    has_wholesale_pricing: boolean;
    variant_count: number;
    default_variant_id: number | null;
}
interface Category { id: number; name: string; slug: string; image_url: string | null; children: Category[] }
interface StoreBanner {
    id: number; title: string; image_url: string;
    alt_text: string | null; link_url: string | null;
}
interface PaginationLink { url: string | null; label: string; active: boolean }
interface ProductPaginator {
    data: StoreProduct[]; current_page: number; last_page: number;
    total: number; links: PaginationLink[];
}
const props = defineProps<{
    products: ProductPaginator;
    categories: Category[];
    banners: StoreBanner[];
    filters: { q: string; category: string };
}>();

const language = ref<'en' | 'bn'>('en');
const search = ref(props.filters.q);
const category = ref(props.filters.category);
const mobileMenuOpen = ref(false);
const categoriesOpen = ref(false);
const expandedCategoryIds = ref<number[]>([]);
const cartCount = ref(0);

const copy = computed(() => language.value === 'bn' ? {
    delivery: 'সারা বাংলাদেশে দ্রুত ডেলিভারি',
    authentic: 'অরিজিনাল ও মানসম্মত পণ্য',
    track: 'অর্ডার ট্র্যাক',
    help: 'সাহায্য: 09612-123456',
    account: 'লগইন / রেজিস্টার',
    productsMenu: 'সব পণ্য',
    specialOffers: 'বিশেষ অফার / স্টক ক্লিয়ারেন্স',
    aboutMenu: 'আমাদের সম্পর্কে',
    warrantyMenu: 'ওয়ারেন্টি',
    supportMenu: 'সাপোর্ট',
    contactMenu: 'যোগাযোগ',
    digitalScaleCollection: 'ডিজিটাল স্কেল কালেকশন',
    search: 'পণ্য, ক্যাটাগরি বা ব্র্যান্ড খুঁজুন...',
    allCategories: 'সব ক্যাটাগরি',
    digital: 'ডিজিটাল স্কেল',
    tools: 'পাওয়ার টুলস',
    ladder: 'ল্যাডার',
    accessories: 'অ্যাক্সেসরিজ',
    brands: 'ব্র্যান্ড',
    flash: 'ফ্ল্যাশ ডিল',
    heroEyebrow: 'প্রফেশনাল ওয়েইং সল্যুশন',
    heroTitle: 'প্রতিটি ব্যবসার জন্য নির্ভরযোগ্য ডিজিটাল ওয়েইং স্কেল',
    heroBody: 'দোকান, শিল্পপ্রতিষ্ঠান ও দৈনন্দিন ব্যবহারের জন্য নির্ভুল ও টেকসই ওজন পরিমাপের সমাধান।',
    shopNow: 'কেনাকাটা শুরু করুন',
    explore: 'সব পণ্য দেখুন',
    fastDelivery: 'দ্রুত ডেলিভারি',
    across: 'সারা বাংলাদেশে',
    genuine: 'অরিজিনাল পণ্য',
    warranty: 'ওয়ারেন্টি সাপোর্ট',
    support: 'বিশেষজ্ঞ সহায়তা',
    categoriesTitle: 'প্রধান ক্যাটাগরি',
    browse: 'ডিজিটাল স্কেলের ক্যাটাগরি দেখুন',
    viewAll: 'সব দেখুন',
    dealsTitle: 'ফ্ল্যাশ ডিল',
    limited: 'নির্বাচিত পণ্যে সীমিত সময়ের অফার',
    collections: 'কালেকশন এক্সপ্লোর করুন',
    powerTools: 'পাওয়ার টুলস কালেকশন',
    ladders: 'ডিজিটাল স্কেল কালেকশন',
    trackTitle: 'আপনার অর্ডার ট্র্যাক করুন',
    trackBody: 'অর্ডারের সর্বশেষ অবস্থা জানতে অর্ডার নম্বর ব্যবহার করুন।',
    why: 'কেন MADINA বেছে নেবেন?',
    accuracy: 'উচ্চ নির্ভুলতা',
    durable: 'টেকসই নির্মাণ',
    expert: 'বিশেষজ্ঞ সাপোর্ট',
    trusted: 'ব্যবসার বিশ্বস্ত সঙ্গী',
    newsletter: 'আপডেট পেতে সাবস্ক্রাইব করুন',
    email: 'আপনার ইমেইল ঠিকানা',
    subscribe: 'সাবস্ক্রাইব',
    products: 'জনপ্রিয় পণ্য',
    add: 'কার্টে যোগ করুন',
    details: 'বিস্তারিত',
    contact: 'মূল্য জানতে যোগাযোগ করুন',
    noImage: 'ছবি শীঘ্রই আসছে',
    empty: 'এখনো কোনো পণ্য পাওয়া যায়নি',
    emptyHint: 'অন্য শব্দ দিয়ে খুঁজুন অথবা সব পণ্য দেখুন।',
    footerAbout: 'আপনার নির্ভরযোগ্য ওয়েইং সল্যুশন পার্টনার',
    company: 'কোম্পানি',
    customerCare: 'কাস্টমার সাপোর্ট',
    about: 'আমাদের সম্পর্কে',
    contactUs: 'যোগাযোগ',
    policies: 'নীতি ও শর্তাবলি',
    return: 'রিটার্ন ও এক্সচেঞ্জ',
    warrantyPolicy: 'ওয়ারেন্টি পলিসি',
    login: 'কাস্টমার লগইন',
    noDeals: 'এই মুহূর্তে কোনো সক্রিয় অফার নেই',
} : {
    delivery: 'Fast delivery across Bangladesh',
    authentic: 'Authentic & quality products',
    track: 'Track Order',
    help: 'Help: 09612-123456',
    account: 'Sign In / Register',
    productsMenu: 'Products',
    specialOffers: 'Special Offers / Stock Clearance',
    aboutMenu: 'About Us',
    warrantyMenu: 'Warranty',
    supportMenu: 'Support',
    contactMenu: 'Contact',
    digitalScaleCollection: 'Digital Scale Collection',
    search: 'Search products, categories, brands...',
    allCategories: 'All Categories',
    digital: 'Digital Scale',
    tools: 'Power Tools',
    ladder: 'Ladder',
    accessories: 'Accessories',
    brands: 'Brands',
    flash: 'Flash Deals',
    heroEyebrow: 'PROFESSIONAL WEIGHING SOLUTIONS',
    heroTitle: 'Digital Weighing Scales for Every Business',
    heroBody: 'Accurate, durable and reliable weighing solutions for shops, industries and everyday use.',
    shopNow: 'Shop Now',
    explore: 'Explore Digital Scales',
    fastDelivery: 'Fast Delivery',
    across: 'Across Bangladesh',
    genuine: 'Authentic Products',
    warranty: 'Warranty Support',
    support: 'Expert Support',
    categoriesTitle: 'Shop by Main Category',
    browse: 'Browse by Digital Scale Category',
    viewAll: 'View All',
    dealsTitle: 'Flash Deals',
    limited: 'Limited time offers on selected products',
    collections: 'Explore Collections',
    powerTools: 'Power Tools Collection',
    ladders: 'Digital Scale Collection',
    trackTitle: 'Track Your Order',
    trackBody: 'Use your order number to check the latest order and delivery status.',
    why: 'Why Choose MADINA™?',
    accuracy: 'High Accuracy',
    durable: 'Durable & Long-Lasting',
    expert: 'Expert Support',
    trusted: 'Trusted by Businesses',
    newsletter: 'Stay Updated',
    email: 'Enter your email address',
    subscribe: 'Subscribe',
    products: 'Popular Products',
    add: 'Add to Cart',
    details: 'Details',
    contact: 'Contact for price',
    noImage: 'Image coming soon',
    empty: 'No products available yet',
    emptyHint: 'Try another search or browse all products.',
    footerAbout: 'Your trusted partner for weighing solutions',
    company: 'Company',
    customerCare: 'Customer Support',
    about: 'About Us',
    contactUs: 'Contact Us',
    policies: 'Privacy & Terms',
    return: 'Return & Exchange',
    warrantyPolicy: 'Warranty Policy',
    login: 'Customer Login',
    noDeals: 'No active offers at the moment',
});

const heroBanner = computed(() => props.banners[0] ?? null);
const flashProducts = computed(() => props.products.data.filter(p => p.has_flash_sale));
const categoryCards = computed(() => props.categories.slice(0, 4));
const digitalScaleCategory = computed(() => props.categories.find(item => /digital[- ]?scale/i.test(item.slug) || /digital scale/i.test(item.name)) ?? props.categories[0] ?? null);
const subCategories = computed(() => digitalScaleCategory.value?.children ?? []);
const navigationItems = computed(() => [
    { label: copy.value.flash, href: '/shop/flash-sales', icon: 'zap' },
    { label: copy.value.productsMenu, href: '/products', icon: 'package' },
    { label: copy.value.specialOffers, href: '/shop/flash-sales?type=special', icon: 'tag' },
    { label: copy.value.aboutMenu, href: '/about', icon: 'info' },
    { label: copy.value.warrantyMenu, href: '/warranty-policy', icon: 'shield-check' },
    { label: copy.value.track, href: '#track-order', icon: 'package-search' },
    { label: copy.value.supportMenu, href: '/support', icon: 'headset' },
    { label: copy.value.contactMenu, href: '/contact', icon: 'phone' },
]);
const visibleProducts = computed(() => props.products.data.slice(0, 10));

function applyFilters(): void {
    router.get('/products', {
        q: search.value.trim() || undefined,
        category: category.value || undefined,
    }, { preserveState: true, preserveScroll: true, replace: true });
}
function formatPrice(value: string | null): string {
    if (value === null) return copy.value.contact;
    return new Intl.NumberFormat(language.value === 'bn' ? 'bn-BD' : 'en-BD', {
        style: 'currency', currency: 'BDT', maximumFractionDigits: 2,
    }).format(Number(value));
}
function addToCart(product: StoreProduct): void {
    if (!product.default_variant_id) return;
    router.post('/cart/items', {
        product_variant_id: product.default_variant_id, quantity: 1,
    }, { preserveScroll: true });
}
function categoryHref(slug: string): string {
    return `/products?category=${encodeURIComponent(slug)}`;
}
function toggleCategory(id: number): void {
    expandedCategoryIds.value = expandedCategoryIds.value.includes(id)
        ? expandedCategoryIds.value.filter(value => value !== id)
        : [...expandedCategoryIds.value, id];
}
function closeCategoryMenu(): void {
    categoriesOpen.value = false;
    expandedCategoryIds.value = [];
}
function submitTracking(event: Event): void {
    const form = event.currentTarget as HTMLFormElement;
    const input = form.querySelector<HTMLInputElement>('input[name=order_number]');
    const orderNumber = input?.value.trim() ?? '';
    if (orderNumber) window.location.href = '/contact?topic=order-tracking';
}
</script>

<template>
    <Head :title="language === 'bn' ? 'MADINA™ | ওয়েইং সল্যুশন' : 'MADINA™ | Weighing Solutions'" />
    <div class="min-h-screen bg-white text-slate-900">
        <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-[100] focus:rounded-lg focus:bg-white focus:px-4 focus:py-3 focus:shadow-lg">Skip to content</a>

        <div class="bg-[#02391D] text-white">
            <div class="mx-auto flex max-w-[1440px] flex-wrap items-center justify-between gap-x-5 gap-y-1 px-4 py-2 text-[11px] font-medium sm:px-6 lg:px-8">
                <span class="inline-flex items-center gap-2"><Icon name="truck" :size="15" />{{ copy.delivery }}</span>
                <span class="hidden items-center gap-2 md:inline-flex"><Icon name="shield-check" :size="15" />{{ copy.authentic }}</span>
                <a href="tel:09612123456" class="inline-flex items-center gap-2 hover:underline"><Icon name="headset" :size="15" />{{ copy.help }}</a>
                <button type="button" class="inline-flex items-center gap-2 rounded-md px-2 py-1 hover:bg-white/10" @click="language = language === 'en' ? 'bn' : 'en'" :aria-label="language === 'en' ? 'Switch to Bangla' : 'Switch to English'"><Icon name="languages" :size="15" />{{ language === 'en' ? 'বাংলা' : 'English' }}</button>
            </div>
        </div>

        <header class="sticky top-0 z-40 border-b border-slate-100 bg-white/95 backdrop-blur">
            <div class="mx-auto flex max-w-[1440px] items-center gap-3 px-4 py-3 sm:px-6 lg:px-8">
                <button class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-slate-200 lg:hidden" type="button" :aria-expanded="mobileMenuOpen" aria-label="Toggle navigation" @click="mobileMenuOpen = !mobileMenuOpen">
                    <Icon :name="mobileMenuOpen ? 'x' : 'menu'" :size="21" />
                </button>
                <Link href="/" class="flex w-[148px] shrink-0 items-center" aria-label="MADINA home">
                    <span class="text-[25px] font-black tracking-[-0.07em] text-slate-950">MADINA<span class="align-top text-[9px]">™</span></span>
                </Link>
                <form class="hidden min-w-0 flex-1 md:flex" role="search" @submit.prevent="applyFilters">
                    <label class="sr-only" for="header-search">{{ copy.search }}</label>
                    <input id="header-search" v-model="search" type="search" :placeholder="copy.search" class="h-11 min-w-0 flex-1 rounded-l-full border border-slate-300 px-5 text-sm outline-none focus:border-emerald-600 focus:ring-2 focus:ring-emerald-100" />
                    <button class="flex h-11 w-12 items-center justify-center rounded-r-full bg-[#087A32] text-white hover:bg-[#056329]" type="submit" :aria-label="copy.search"><Icon name="search" :size="20" /></button>
                </form>
                <div class="ml-auto flex shrink-0 items-center gap-1 sm:gap-3">
                    <button type="button" disabled title="Customer sign-in will be enabled when customer authentication is configured" class="hidden cursor-not-allowed items-center gap-2 rounded-lg px-2 py-2 text-xs font-semibold text-slate-500 opacity-70 sm:flex"><Icon name="user-round" :size="20" />{{ copy.account }}</button>
                    <Link href="/cart" class="relative inline-flex h-10 w-10 items-center justify-center rounded-lg hover:bg-slate-50" aria-label="Shopping cart"><Icon name="shopping-cart" :size="22" /><span class="absolute right-0 top-0 flex h-4 min-w-4 items-center justify-center rounded-full bg-green-700 px-1 text-[10px] font-bold text-white">{{ cartCount }}</span></Link>
                </div>
            </div>
            <form class="flex px-4 pb-3 md:hidden" role="search" @submit.prevent="applyFilters">
                <label class="sr-only" for="mobile-search">{{ copy.search }}</label>
                <input id="mobile-search" v-model="search" type="search" :placeholder="copy.search" class="h-10 min-w-0 flex-1 rounded-l-full border border-slate-300 px-4 text-sm outline-none focus:border-emerald-600" />
                <button class="flex h-10 w-11 items-center justify-center rounded-r-full bg-[#087A32] text-white" type="submit" aria-label="Search"><Icon name="search" :size="18" /></button>
            </form>
            <nav class="hidden border-t border-slate-100 lg:block" aria-label="Primary navigation">
                <div class="mx-auto flex max-w-[1440px] items-center gap-5 px-4 py-3 text-[12px] font-bold sm:px-6 lg:px-8">
                    <div class="relative shrink-0">
                        <button type="button" class="inline-flex items-center gap-2 rounded-full bg-[#087A32] px-5 py-3 text-white shadow-sm transition hover:bg-[#056329] focus-visible:outline-none focus-visible:ring-4 focus-visible:ring-green-200" :aria-expanded="categoriesOpen" aria-controls="all-categories-menu" @click="categoriesOpen = !categoriesOpen">
                            <Icon name="menu" :size="17" />{{ copy.allCategories }}<Icon :name="categoriesOpen ? 'chevron-up' : 'chevron-down'" :size="15" />
                        </button>
                        <Transition enter-active-class="transition duration-200 ease-out" enter-from-class="translate-y-2 opacity-0" enter-to-class="translate-y-0 opacity-100" leave-active-class="transition duration-150 ease-in" leave-from-class="translate-y-0 opacity-100" leave-to-class="translate-y-2 opacity-0">
                            <div v-if="categoriesOpen" id="all-categories-menu" class="absolute left-0 top-full z-50 mt-3 max-h-[70vh] w-[min(92vw,760px)] overflow-y-auto rounded-2xl border border-slate-200 bg-white p-4 shadow-2xl">
                                <div class="mb-3 flex items-center justify-between"><div><p class="text-sm font-extrabold text-slate-900">{{ copy.allCategories }}</p><p class="mt-1 text-xs font-normal text-slate-500">Browse product categories and subcategories</p></div><button type="button" class="rounded-full p-2 text-slate-500 hover:bg-slate-100" aria-label="Close categories" @click="closeCategoryMenu"><Icon name="x" :size="18" /></button></div>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div v-for="item in categories" :key="item.id" class="overflow-hidden rounded-xl border border-slate-100 bg-white">
                                        <div class="flex items-center gap-3 p-3">
                                            <Link :href="categoryHref(item.slug)" class="flex min-w-0 flex-1 items-center gap-3" @click="closeCategoryMenu">
                                                <span class="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-[#E8F5EC]"> <StorefrontImage v-if="item.image_url" :src="item.image_url" :alt="item.name" kind="category" aspect="1 / 1" dimensions="480 × 480 px" image-class="h-full w-full object-contain p-1" /><Icon v-else name="scale" class="text-green-700" :size="22" /></span>
                                                <span class="truncate text-sm font-bold text-slate-800">{{ item.name }}</span>
                                            </Link>
                                            <button v-if="item.children.length" type="button" class="rounded-full p-2 text-green-800 transition hover:bg-green-50" :aria-expanded="expandedCategoryIds.includes(item.id)" :aria-label="`Toggle ${item.name} subcategories`" @click="toggleCategory(item.id)"><Icon :name="expandedCategoryIds.includes(item.id) ? 'chevron-up' : 'chevron-down'" :size="17" /></button>
                                        </div>
                                        <div v-if="item.children.length && expandedCategoryIds.includes(item.id)" class="grid gap-1 border-t border-slate-100 bg-slate-50 p-2">
                                            <Link v-for="child in item.children" :key="child.id" :href="categoryHref(child.slug)" class="rounded-lg px-3 py-2 text-xs font-medium text-slate-600 transition hover:bg-green-50 hover:text-green-800" @click="closeCategoryMenu">{{ child.name }}</Link>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </Transition>
                    </div>
                    <div class="flex min-w-0 flex-1 items-center justify-between gap-4 overflow-x-auto whitespace-nowrap [scrollbar-width:none]">
                        <Link v-for="item in navigationItems" :key="item.href" :href="item.href" class="inline-flex shrink-0 items-center gap-1.5 text-slate-700 transition hover:text-green-700"><Icon :name="item.icon" :size="15" />{{ item.label }}</Link>
                    </div>
                </div>
            </nav>
            <div v-if="mobileMenuOpen" class="border-t border-slate-100 bg-white px-4 py-3 shadow-lg lg:hidden">
                <nav class="grid grid-cols-2 gap-2" aria-label="Mobile navigation">
                    <button type="button" class="col-span-2 flex items-center justify-between rounded-xl bg-green-50 px-3 py-3 text-sm font-bold text-green-900" :aria-expanded="categoriesOpen" @click="categoriesOpen = !categoriesOpen">{{ copy.allCategories }}<Icon :name="categoriesOpen ? 'chevron-up' : 'chevron-down'" :size="17" /></button>
                    <div v-if="categoriesOpen" class="col-span-2 grid gap-2 rounded-xl border border-green-100 p-2">
                        <div v-for="item in categories" :key="item.id" class="rounded-lg bg-slate-50 p-2">
                            <div class="flex items-center justify-between gap-2"><Link :href="categoryHref(item.slug)" class="text-sm font-bold" @click="mobileMenuOpen = false">{{ item.name }}</Link><button v-if="item.children.length" type="button" class="p-2" @click="toggleCategory(item.id)"><Icon :name="expandedCategoryIds.includes(item.id) ? 'chevron-up' : 'chevron-down'" :size="16" /></button></div>
                            <div v-if="item.children.length && expandedCategoryIds.includes(item.id)" class="mt-2 grid grid-cols-2 gap-1 border-t border-slate-200 pt-2"><Link v-for="child in item.children" :key="child.id" :href="categoryHref(child.slug)" class="rounded-md px-2 py-2 text-xs text-slate-600 hover:bg-green-50" @click="mobileMenuOpen = false">{{ child.name }}</Link></div>
                        </div>
                    </div>
                    <Link v-for="item in navigationItems" :key="item.href" :href="item.href" class="rounded-lg bg-slate-50 px-3 py-3 text-sm font-semibold text-slate-700">{{ item.label }}</Link>
                    <button type="button" disabled class="col-span-2 rounded-lg border border-slate-200 px-3 py-3 text-left text-sm font-semibold text-slate-500">{{ copy.account }} · Coming soon</button>
                </nav>
            </div>
        </header>

        <main id="main-content">
            <section class="mx-auto max-w-[1440px] px-4 pt-4 sm:px-6 lg:px-8">
                <div class="relative grid min-h-[310px] overflow-hidden rounded-[22px] bg-[#E8F5EC] md:min-h-[360px] md:grid-cols-2">
                    <div class="relative z-10 flex flex-col justify-center px-6 py-10 sm:px-10 lg:px-16">
                        <p class="text-[11px] font-extrabold tracking-[0.22em] text-[#056329]">{{ copy.heroEyebrow }}</p>
                        <h1 class="mt-3 max-w-xl text-3xl leading-[1.12] font-black tracking-tight text-[#101828] sm:text-4xl lg:text-[44px]">{{ copy.heroTitle }}</h1>
                        <p class="mt-4 max-w-lg text-sm leading-6 text-slate-700 sm:text-base">{{ copy.heroBody }}</p>
                        <div class="mt-6 flex flex-wrap gap-3">
                            <Link href="/products" class="inline-flex min-h-11 items-center gap-2 rounded-full bg-[#087A32] px-6 text-sm font-bold text-white hover:bg-[#056329]">{{ copy.shopNow }}<Icon name="arrow-right" :size="17" /></Link>
                            <Link href="/products" class="inline-flex min-h-11 items-center rounded-full border border-slate-500 bg-white/80 px-6 text-sm font-bold text-slate-900 hover:bg-white">{{ copy.explore }}</Link>
                        </div>
                    </div>
                    <div class="relative min-h-[190px] md:min-h-full">
                        <StorefrontImage v-if="heroBanner" :src="heroBanner.image_url" :alt="heroBanner.alt_text || heroBanner.title" kind="hero" aspect="16 / 9" dimensions="1920 × 600 px" class="absolute inset-0 h-full w-full" image-class="h-full w-full object-cover" />
                        <div v-else class="absolute inset-0 flex items-center justify-center bg-gradient-to-br from-[#D9F0E0] via-[#F4FAF6] to-[#C5E6D0]">
                            <div class="text-center text-[#087A32]"><Icon name="scale" :size="76" /><p class="mt-3 text-xs font-bold tracking-widest">HERO BANNER · 1920 × 600 PX</p></div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto grid max-w-[1440px] grid-cols-2 gap-4 px-4 py-6 sm:px-6 md:grid-cols-4 lg:px-8">
                <div class="flex items-center gap-3 border-r border-slate-200 px-2 py-2"><Icon name="truck" class="shrink-0 text-green-700" :size="29" /><div><p class="text-xs font-extrabold">{{ copy.fastDelivery }}</p><p class="text-[11px] text-slate-500">{{ copy.across }}</p></div></div>
                <div class="flex items-center gap-3 px-2 py-2 md:border-r md:border-slate-200"><Icon name="shield-check" class="shrink-0 text-green-700" :size="29" /><div><p class="text-xs font-extrabold">{{ copy.genuine }}</p><p class="text-[11px] text-slate-500">{{ copy.authentic }}</p></div></div>
                <div class="flex items-center gap-3 border-r border-slate-200 px-2 py-2"><Icon name="badge-check" class="shrink-0 text-green-700" :size="29" /><div><p class="text-xs font-extrabold">{{ copy.warranty }}</p><p class="text-[11px] text-slate-500">{{ copy.warranty }}</p></div></div>
                <div class="flex items-center gap-3 px-2 py-2"><Icon name="headset" class="shrink-0 text-green-700" :size="29" /><div><p class="text-xs font-extrabold">{{ copy.support }}</p><p class="text-[11px] text-slate-500">{{ copy.help }}</p></div></div>
            </section>

            <section class="mx-auto max-w-[1440px] px-4 py-5 sm:px-6 lg:px-8">
                <div class="mb-4 flex items-center justify-between"><h2 class="text-xl font-black tracking-tight sm:text-2xl">{{ copy.products }}</h2><Link href="/products" class="text-xs font-bold text-green-800">{{ copy.viewAll }} →</Link></div>
                <div v-if="visibleProducts.length" class="grid grid-cols-2 gap-3 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5">
                    <article v-for="product in visibleProducts" :key="product.id" class="group flex min-w-0 flex-col overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:border-green-200 hover:shadow-md">
                        <Link :href="`/products/${product.slug}`" class="block"><div class="relative aspect-square bg-slate-50"><StorefrontImage :src="product.image_url" :alt="product.name" kind="product" aspect="1 / 1" dimensions="800 × 800 px" image-class="h-full w-full object-contain p-4" /><span v-if="product.has_flash_sale" class="absolute left-2 top-2 rounded-full bg-red-600 px-2 py-1 text-[10px] font-bold text-white">SALE</span></div><div class="p-3"><p v-if="product.brand" class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ product.brand }}</p><h3 class="mt-1 line-clamp-2 min-h-10 text-xs font-bold leading-5">{{ product.name }}</h3><p class="mt-2 text-sm font-black text-green-700">{{ formatPrice(product.price) }}</p><p v-if="product.has_flash_sale && product.regular_price" class="text-[11px] text-slate-400 line-through">{{ formatPrice(product.regular_price) }}</p><p v-if="product.has_wholesale_pricing" class="mt-1 text-[10px] text-slate-500">{{ language === 'bn' ? 'পাইকারি মূল্য পাওয়া যায়' : 'Wholesale pricing available' }}</p></div></Link>
                        <div class="mt-auto grid grid-cols-2 gap-2 px-3 pb-3"><Link :href="`/products/${product.slug}`" class="rounded-lg border border-green-700 py-2 text-center text-[11px] font-bold text-green-800">{{ copy.details }}</Link><button v-if="product.default_variant_id" type="button" class="rounded-lg bg-green-700 py-2 text-[11px] font-bold text-white hover:bg-green-800" @click="addToCart(product)">{{ copy.add }}</button></div>
                    </article>
                </div>
                <div v-else class="rounded-xl border border-dashed border-slate-200 p-12 text-center"><p class="font-bold">{{ copy.empty }}</p><p class="mt-2 text-sm text-slate-500">{{ copy.emptyHint }}</p></div>
            </section>

            <section class="mx-auto max-w-[1440px] px-4 py-5 sm:px-6 lg:px-8">
                <div class="mb-4 flex items-center justify-between gap-3"><h2 class="text-xl font-black tracking-tight sm:text-2xl">{{ copy.categoriesTitle }}</h2><Link href="/products" class="text-xs font-bold text-green-800 hover:underline">{{ copy.viewAll }} <Icon name="arrow-right" class="inline" :size="14" /></Link></div>
                <div v-if="categoryCards.length" class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <Link v-for="(item, index) in categoryCards" :key="item.id" :href="categoryHref(item.slug)" class="group flex min-h-28 items-center justify-between gap-2 overflow-hidden rounded-xl border border-slate-100 p-4 transition hover:-translate-y-0.5 hover:shadow-md" :class="index % 2 ? 'bg-slate-50' : 'bg-[#E8F5EC]'">
                        <div class="min-w-0"><h3 class="text-sm font-extrabold sm:text-base">{{ item.name }}</h3><p class="mt-1 text-xs text-slate-600">{{ copy.viewAll }}</p></div>
                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-green-700 text-white"><Icon name="arrow-right" :size="16" /></span>
                    </Link>
                </div>
                <div v-else class="rounded-xl bg-slate-50 p-6 text-sm text-slate-500">{{ copy.emptyHint }}</div>
            </section>

            <section v-if="subCategories.length" class="mx-auto max-w-[1440px] px-4 py-5 sm:px-6 lg:px-8">
                <div class="mb-4 flex items-center justify-between"><h2 class="text-xl font-black tracking-tight sm:text-2xl">{{ copy.browse }}</h2><Link href="/products" class="text-xs font-bold text-green-800">{{ copy.viewAll }} →</Link></div>
                <div class="grid grid-cols-3 gap-2 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 xl:grid-cols-11">
                    <Link v-for="item in subCategories" :key="item.id" :href="categoryHref(item.slug)" class="flex min-h-20 items-center justify-center rounded-xl border border-slate-200 bg-white p-3 text-center text-xs font-bold transition hover:border-green-300 hover:bg-green-50">{{ item.name }}</Link>
                </div>
            </section>

            <section class="mx-auto max-w-[1440px] px-4 py-6 sm:px-6 lg:px-8">
                <div class="mb-4 flex flex-wrap items-end justify-between gap-3"><div><h2 class="text-xl font-black tracking-tight sm:text-2xl"><Icon name="zap" class="mr-1 inline text-green-700" :size="24" />{{ copy.dealsTitle }}</h2><p class="mt-1 text-xs text-slate-500">{{ copy.limited }}</p></div><Link href="/shop/flash-sales" class="text-xs font-bold text-green-800">{{ copy.viewAll }} →</Link></div>
                <div v-if="flashProducts.length" class="grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-5">
                    <article v-for="product in flashProducts.slice(0, 5)" :key="product.id" class="group overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:shadow-md">
                        <Link :href="`/products/${product.slug}`" class="block"><div class="relative aspect-square bg-slate-50"><StorefrontImage :src="product.image_url" :alt="product.name" kind="product" aspect="1 / 1" dimensions="800 × 800 px" image-class="h-full w-full object-contain p-4" /><span class="absolute left-2 top-2 rounded-full bg-red-600 px-2 py-1 text-[10px] font-bold text-white">SALE</span></div><div class="p-3"><h3 class="line-clamp-2 min-h-10 text-xs font-bold">{{ product.name }}</h3><p class="mt-2 text-sm font-black text-green-700">{{ formatPrice(product.price) }}</p><p class="text-[11px] text-slate-400 line-through" v-if="product.regular_price">{{ formatPrice(product.regular_price) }}</p></div></Link>
                        <div class="px-3 pb-3"><button v-if="product.default_variant_id" type="button" class="w-full rounded-lg bg-green-700 py-2 text-xs font-bold text-white hover:bg-green-800" @click="addToCart(product)">{{ copy.add }}</button></div>
                    </article>
                </div>
                <p v-else class="rounded-xl border border-dashed border-slate-200 px-5 py-8 text-center text-sm text-slate-500">{{ copy.noDeals }}</p>
            </section>

            <section class="mx-auto grid max-w-[1440px] gap-3 px-4 py-5 sm:px-6 md:grid-cols-2 lg:px-8">
                <Link href="/products" class="flex min-h-36 items-center justify-between overflow-hidden rounded-2xl bg-[#034B25] p-6 text-white"><div><h2 class="text-xl font-black">{{ copy.powerTools }}</h2><p class="mt-1 text-sm text-green-100">{{ copy.collections }}</p><span class="mt-4 inline-flex rounded-full bg-white px-4 py-2 text-xs font-bold text-green-900">{{ copy.shopNow }} →</span></div><Icon name="wrench" :size="72" class="text-green-200" /></Link>
                <Link :href="digitalScaleCategory ? categoryHref(digitalScaleCategory.slug) : '/products'" class="flex min-h-36 items-center justify-between overflow-hidden rounded-2xl bg-[#E8F5EC] p-6 text-slate-950"><div><h2 class="text-xl font-black">{{ copy.ladders }}</h2><p class="mt-1 text-sm text-slate-600">{{ copy.collections }}</p><span class="mt-4 inline-flex rounded-full border border-green-700 bg-white px-4 py-2 text-xs font-bold text-green-900">{{ copy.shopNow }} →</span></div><Icon name="scale" :size="72" class="text-green-700" /></Link>
            </section>



            <section id="track-order" class="mx-auto max-w-[1440px] scroll-mt-36 px-4 py-5 sm:px-6 lg:px-8">
                <div class="flex flex-col items-start gap-4 rounded-2xl bg-[#034B25] p-6 text-white sm:flex-row sm:items-center sm:justify-between sm:px-10"><div class="flex items-center gap-4"><Icon name="package-search" :size="44" /><div><h2 class="text-xl font-black">{{ copy.trackTitle }}</h2><p class="mt-1 max-w-lg text-sm text-green-100">{{ copy.trackBody }}</p></div></div><form class="flex w-full flex-col gap-2 sm:w-auto sm:min-w-[390px] sm:flex-row" @submit.prevent="submitTracking"><label class="sr-only" for="track-order-number">{{ language === 'bn' ? 'অর্ডার নম্বর' : 'Order number' }}</label><input id="track-order-number" name="order_number" type="text" required minlength="4" maxlength="40" :placeholder="language === 'bn' ? 'আপনার অর্ডার নম্বর লিখুন' : 'Enter your order number'" class="h-11 min-w-0 flex-1 rounded-full border border-white/50 bg-white px-4 text-sm text-slate-900 outline-none placeholder:text-slate-400 focus:ring-4 focus:ring-green-200" /><button type="submit" class="inline-flex h-11 items-center justify-center rounded-full bg-green-500 px-5 text-sm font-bold text-white hover:bg-green-400">{{ copy.track }} <Icon name="arrow-right" class="ml-2" :size="16" /></button></form></div>
            </section>

            <section class="mx-auto max-w-[1440px] px-4 py-7 sm:px-6 lg:px-8">
                <h2 class="mb-5 text-xl font-black sm:text-2xl">{{ copy.why }}</h2>
                <div class="grid grid-cols-2 gap-4 md:grid-cols-4"><div class="flex items-center gap-3"><Icon name="target" class="text-green-700" :size="30" /><span class="text-xs font-bold">{{ copy.accuracy }}</span></div><div class="flex items-center gap-3"><Icon name="layers" class="text-green-700" :size="30" /><span class="text-xs font-bold">{{ copy.durable }}</span></div><div class="flex items-center gap-3"><Icon name="headset" class="text-green-700" :size="30" /><span class="text-xs font-bold">{{ copy.expert }}</span></div><div class="flex items-center gap-3"><Icon name="shield-check" class="text-green-700" :size="30" /><span class="text-xs font-bold">{{ copy.trusted }}</span></div></div>
            </section>

            <section class="mx-auto max-w-[1440px] px-4 pb-7 sm:px-6 lg:px-8">
                <div class="flex flex-col gap-4 rounded-xl bg-[#E8F5EC] p-5 sm:flex-row sm:items-center sm:justify-between sm:px-8"><div><h2 class="text-lg font-black">{{ copy.newsletter }}</h2><p class="text-xs text-slate-600">{{ language === 'bn' ? 'নতুন পণ্য ও অফারের খবর পেতে আমাদের সঙ্গে থাকুন।' : 'Get updates about new products and offers.' }}</p></div><a href="mailto:info@madinascale.online?subject=Newsletter%20Subscription" class="inline-flex min-h-10 items-center justify-center rounded-full bg-green-700 px-5 text-sm font-bold text-white hover:bg-green-800">{{ copy.subscribe }} <Icon name="arrow-right" class="ml-2" :size="16" /></a></div>
            </section>
        </main>

        <footer class="bg-[#02391D] text-white">
            <div class="mx-auto grid max-w-[1440px] gap-8 px-4 py-9 sm:px-6 md:grid-cols-4 lg:px-8">
                <div><div class="text-2xl font-black tracking-tight">MADINA™</div><p class="mt-3 max-w-xs text-xs leading-5 text-green-100">{{ copy.footerAbout }}</p><a href="tel:09612123456" class="mt-4 inline-flex items-center gap-2 text-sm font-bold"><Icon name="headset" :size="18" />09612-123456</a></div>
                <div><h3 class="text-sm font-extrabold">{{ copy.categoriesTitle }}</h3><div class="mt-3 grid gap-2 text-xs text-green-100"><Link v-for="item in categoryCards" :key="item.id" :href="categoryHref(item.slug)" class="hover:text-white">{{ item.name }}</Link></div></div>
                <div><h3 class="text-sm font-extrabold">{{ copy.company }}</h3><div class="mt-3 grid gap-2 text-xs text-green-100"><Link href="/products" class="hover:text-white">{{ copy.about }}</Link><a href="mailto:info@madinascale.online" class="hover:text-white">{{ copy.contactUs }}</a><Link href="/pages/privacy-policy" class="hover:text-white">{{ copy.policies }}</Link></div></div>
                <div><h3 class="text-sm font-extrabold">{{ copy.customerCare }}</h3><div class="mt-3 grid gap-2 text-xs text-green-100"><a href="#track-order" class="hover:text-white">{{ copy.track }}</a><Link href="/pages/warranty-policy" class="hover:text-white">{{ copy.warrantyPolicy }}</Link><Link href="/pages/return-policy" class="hover:text-white">{{ copy.return }}</Link></div></div>
            </div>
            <div class="mx-auto flex max-w-[1440px] flex-col gap-2 border-t border-white/20 px-4 py-4 text-[11px] text-green-100 sm:flex-row sm:justify-between sm:px-6 lg:px-8"><span>© {{ new Date().getFullYear() }} Madina Scale. All rights reserved.</span><span>www.madinascale.online</span></div>
        </footer>
    </div>
</template>
