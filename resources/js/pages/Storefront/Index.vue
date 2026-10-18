<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Icon from '@/components/dashboard/Icon.vue';

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
    has_wholesale_pricing: boolean;
    variant_count: number;
}

interface Category {
    id: number;
    name: string;
    slug: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface ProductPaginator {
    data: StoreProduct[];
    current_page: number;
    last_page: number;
    total: number;
    links: PaginationLink[];
}

const props = defineProps<{
    products: ProductPaginator;
    categories: Category[];
    filters: { q: string; category: string };
}>();

const language = ref<'bn' | 'en'>('bn');
const search = ref(props.filters.q);
const category = ref(props.filters.category);

const copy = computed(() => language.value === 'bn'
    ? {
        shop: 'অনলাইন স্টোর', login: 'ERP লগইন', eyebrow: 'MADINA SCALE · OFFICIAL STORE',
        title: 'আপনার কাজের জন্য সঠিক স্কেল খুঁজুন',
        subtitle: 'শিল্প, বাণিজ্য ও ব্যবসার জন্য weighing scale এবং সংশ্লিষ্ট পণ্য।',
        search: 'পণ্যের নাম দিয়ে খুঁজুন…', all: 'সব ক্যাটাগরি', filter: 'খুঁজুন',
        products: 'পণ্যসমূহ', results: 'টি পণ্য', view: 'পণ্য দেখুন',
        wholesale: 'পাইকারি মূল্য পাওয়া যায়', noImage: 'ছবি নেই', empty: 'কোনো পণ্য পাওয়া যায়নি',
        emptyHint: 'অন্য শব্দ দিয়ে খুঁজুন অথবা ক্যাটাগরি পরিবর্তন করুন।', previous: 'আগের', next: 'পরের',
        footer: 'নির্ভরযোগ্য ওজন পরিমাপের সমাধান',
    }
    : {
        shop: 'Online Store', login: 'ERP Login', eyebrow: 'MADINA SCALE · OFFICIAL STORE',
        title: 'Find the right scale for your work',
        subtitle: 'Weighing scales and related products for industry, trade, and business.',
        search: 'Search products by name…', all: 'All categories', filter: 'Search',
        products: 'Products', results: 'products', view: 'View product',
        wholesale: 'Wholesale pricing available', noImage: 'No image', empty: 'No products found',
        emptyHint: 'Try another search term or choose a different category.', previous: 'Previous', next: 'Next',
        footer: 'Reliable weighing solutions',
    });

function applyFilters(): void {
    router.get('/shop', {
        q: search.value.trim() || undefined,
        category: category.value || undefined,
    }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function formatPrice(value: string | null): string {
    if (value === null) {
        return language.value === 'bn' ? 'মূল্য জানতে যোগাযোগ করুন' : 'Contact for price';
    }

    return new Intl.NumberFormat(language.value === 'bn' ? 'bn-BD' : 'en-BD', {
        style: 'currency',
        currency: 'BDT',
        maximumFractionDigits: 2,
    }).format(Number(value));
}

function pageLabel(label: string): string {
    return label.replace('&laquo;', '‹').replace('&raquo;', '›');
}
</script>

<template>
    <Head :title="language === 'bn' ? 'অনলাইন স্টোর | Madina Scale' : 'Online Store | Madina Scale'" />

    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8">
                <Link href="/shop" class="flex min-w-0 items-center gap-3" aria-label="Madina Scale home">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm">
                        <Icon name="scale" :size="25" />
                    </span>
                    <span class="min-w-0">
                        <span class="block truncate text-base font-extrabold tracking-tight">MADINA SCALE</span>
                        <span class="block text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-500">WEIGHING A BETTER TOMORROW</span>
                    </span>
                </Link>
                <nav class="flex shrink-0 items-center gap-2 sm:gap-3">
                    <button type="button" class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" @click="language = language === 'bn' ? 'en' : 'bn'">
                        {{ language === 'bn' ? 'English' : 'বাংলা' }}
                    </button>
                    <a href="/login" class="hidden rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700 sm:inline-flex">{{ copy.login }}</a>
                </nav>
            </div>
        </header>

        <main>
            <section class="border-b border-emerald-100 bg-gradient-to-br from-emerald-50 via-white to-white">
                <div class="mx-auto grid max-w-7xl gap-8 px-4 py-12 sm:px-6 sm:py-16 lg:grid-cols-[1.2fr_0.8fr] lg:items-center lg:px-8 lg:py-20">
                    <div>
                        <p class="mb-4 inline-flex items-center gap-2 rounded-full border border-emerald-200 bg-white px-3 py-1.5 text-xs font-bold tracking-wide text-emerald-800">
                            <span class="h-2 w-2 rounded-full bg-emerald-500"></span>{{ copy.eyebrow }}
                        </p>
                        <h1 class="max-w-3xl text-3xl font-extrabold leading-tight tracking-tight text-slate-950 sm:text-5xl">{{ copy.title }}</h1>
                        <p class="mt-4 max-w-2xl text-base leading-7 text-slate-600 sm:text-lg">{{ copy.subtitle }}</p>
                    </div>
                    <div class="rounded-2xl border border-emerald-100 bg-white p-5 shadow-sm sm:p-6">
                        <form class="grid gap-3" @submit.prevent="applyFilters">
                            <label class="text-sm font-semibold text-slate-800" for="store-search">{{ copy.products }}</label>
                            <div class="relative">
                                <Icon name="search" :size="18" class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                                <input id="store-search" v-model="search" type="search" :placeholder="copy.search" class="w-full rounded-xl border border-slate-200 bg-white py-3 pl-10 pr-3 text-sm outline-none transition focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100" />
                            </div>
                            <select v-model="category" class="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100">
                                <option value="">{{ copy.all }}</option>
                                <option v-for="item in categories" :key="item.id" :value="item.slug">{{ item.name }}</option>
                            </select>
                            <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-bold text-white transition hover:bg-emerald-700 focus:outline-none focus:ring-4 focus:ring-emerald-200">
                                <Icon name="search" :size="17" />{{ copy.filter }}
                            </button>
                        </form>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8 lg:py-14">
                <div class="mb-6 flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[0.16em] text-emerald-700">MADINA SCALE</p>
                        <h2 class="mt-1 text-2xl font-extrabold tracking-tight text-slate-950">{{ copy.products }}</h2>
                    </div>
                    <span class="rounded-full bg-white px-3 py-1.5 text-sm font-semibold text-slate-600 ring-1 ring-slate-200">{{ products.total }} {{ copy.results }}</span>
                </div>

                <div v-if="products.data.length" class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                    <article v-for="product in products.data" :key="product.id" class="group flex min-w-0 flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition duration-200 hover:-translate-y-1 hover:border-emerald-200 hover:shadow-lg">
                        <Link :href="`/shop/${product.slug}`" class="relative block aspect-[4/3] overflow-hidden bg-slate-100">
                            <img v-if="product.image_url" :src="product.image_url" :alt="product.name" loading="lazy" class="h-full w-full object-contain p-5 transition duration-300 group-hover:scale-[1.03]" />
                            <div v-else class="flex h-full flex-col items-center justify-center gap-2 text-slate-400">
                                <Icon name="package" :size="42" />
                                <span class="text-xs font-medium">{{ copy.noImage }}</span>
                            </div>
                            <span v-if="product.category" class="absolute left-3 top-3 rounded-full bg-white/95 px-2.5 py-1 text-[11px] font-semibold text-slate-600 shadow-sm">{{ product.category }}</span>
                        </Link>
                        <div class="flex flex-1 flex-col p-4">
                            <p v-if="product.brand" class="text-xs font-semibold uppercase tracking-wide text-slate-400">{{ product.brand }}</p>
                            <h3 class="mt-1 line-clamp-2 text-base font-bold leading-6 text-slate-900">{{ product.name }}</h3>
                            <p v-if="product.short_description" class="mt-2 line-clamp-2 min-h-10 text-sm leading-5 text-slate-500">{{ product.short_description }}</p>
                            <div class="mt-auto pt-4">
                                <p class="text-lg font-extrabold tracking-tight text-emerald-700">{{ formatPrice(product.price) }}<span v-if="product.unit" class="ml-1 text-xs font-medium text-slate-500">/ {{ product.unit }}</span></p>
                                <p v-if="product.has_wholesale_pricing" class="mt-1 text-xs font-medium text-slate-500">{{ copy.wholesale }}</p>
                                <Link :href="`/shop/${product.slug}`" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl border border-emerald-600 px-3 py-2.5 text-sm font-bold text-emerald-700 transition hover:bg-emerald-600 hover:text-white">
                                    {{ copy.view }}<Icon name="arrow-right" :size="16" />
                                </Link>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-else class="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-16 text-center">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-500"><Icon name="search" :size="26" /></span>
                    <h3 class="mt-4 text-lg font-bold text-slate-900">{{ copy.empty }}</h3>
                    <p class="mt-2 text-sm text-slate-500">{{ copy.emptyHint }}</p>
                </div>

                <div v-if="products.last_page > 1" class="mt-8 flex flex-wrap justify-center gap-2">
                    <template v-for="link in products.links" :key="`${link.label}-${link.url}`">
                        <Link v-if="link.url" :href="link.url" class="rounded-lg border px-3 py-2 text-sm font-semibold transition" :class="link.active ? 'border-emerald-600 bg-emerald-600 text-white' : 'border-slate-200 bg-white text-slate-700 hover:border-emerald-300'">{{ pageLabel(link.label) }}</Link>
                        <span v-else class="rounded-lg border border-slate-100 px-3 py-2 text-sm text-slate-300">{{ pageLabel(link.label) }}</span>
                    </template>
                </div>
            </section>
        </main>

        <footer class="border-t border-slate-200 bg-white">
            <div class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-6 text-sm text-slate-500 sm:flex-row sm:items-center sm:justify-between sm:px-6 lg:px-8">
                <span class="font-bold tracking-wide text-slate-800">MADINA SCALE</span>
                <span>{{ copy.footer }} · © {{ new Date().getFullYear() }} Madina Scale</span>
            </div>
        </footer>
    </div>
</template>
