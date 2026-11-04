<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import Icon from '@/components/dashboard/Icon.vue';

interface StoreVariant {
    id: number;
    name: string;
    price: string;
    has_wholesale_pricing: boolean;
    is_default: boolean;
}

interface StoreProduct {
    id: number;
    name: string;
    slug: string;
    short_description: string | null;
    description: string | null;
    category: string | null;
    brand: string | null;
    unit: string | null;
    image_url: string | null;
    price: string | null;
    has_wholesale_pricing: boolean;
    images: Array<{ url: string; alt: string }>;
    variants: StoreVariant[];
}

const props = defineProps<{ product: StoreProduct }>();
const language = ref<'bn' | 'en'>('bn');
const selectedVariantId = ref<number | null>(
    props.product.variants.find((variant) => variant.is_default)?.id ??
        props.product.variants[0]?.id ??
        null,
);
const activeImage = ref(
    props.product.image_url ?? props.product.images[0]?.url ?? null,
);
const copy = computed(() =>
    language.value === 'bn'
        ? {
              back: 'সব পণ্য',
              details: 'পণ্যের বিবরণ',
              price: 'খুচরা মূল্য',
              variants: 'ভ্যারিয়েন্ট',
              wholesale: 'পাইকারি মূল্য পাওয়া যায়',
              noImage: 'ছবি নেই',
              language: 'English',
          }
        : {
              back: 'All products',
              details: 'Product details',
              price: 'Retail price',
              variants: 'Variants',
              wholesale: 'Wholesale pricing available',
              noImage: 'No image',
              language: 'বাংলা',
          },
);
const selectedVariant = computed(
    () =>
        props.product.variants.find(
            (variant) => variant.id === selectedVariantId.value,
        ) ??
        props.product.variants[0] ??
        null,
);
const displayedPrice = computed(
    () => selectedVariant.value?.price ?? props.product.price,
);

function addToCart(): void {
    if (!selectedVariant.value) return;
    router.post('/cart/items', { product_variant_id: selectedVariant.value.id, quantity: 1 }, { preserveScroll: true });
}

function formatPrice(value: string | null): string {
    if (value === null)
        return language.value === 'bn'
            ? 'মূল্য জানতে যোগাযোগ করুন'
            : 'Contact for price';
    return new Intl.NumberFormat(language.value === 'bn' ? 'bn-BD' : 'en-BD', {
        style: 'currency',
        currency: 'BDT',
        maximumFractionDigits: 2,
    }).format(Number(value));
}
</script>

<template>
    <Head :title="`${product.name} | Madina Scale`" />
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header class="border-b border-slate-200 bg-white">
            <div
                class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6 lg:px-8"
            >
                <Link href="/shop" class="flex items-center gap-3">
                    <span
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-600 text-white"
                        ><Icon name="scale" :size="25"
                    /></span>
                    <span
                        ><span
                            class="block text-base font-extrabold tracking-tight"
                            >MADINA SCALE</span
                        ><span
                            class="block text-[10px] font-semibold tracking-[0.18em] text-slate-500 uppercase"
                            >WEIGHING A BETTER TOMORROW</span
                        ></span
                    >
                </Link>
                <button
                    type="button"
                    class="rounded-lg border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    @click="language = language === 'bn' ? 'en' : 'bn'"
                >
                    {{ copy.language }}
                </button>
            </div>
        </header>
        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 lg:py-12">
            <Link
                href="/shop"
                class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-700 hover:text-emerald-800"
                ><Icon name="arrow-left" :size="17" />{{ copy.back }}</Link
            >
            <div class="mt-6 grid gap-8 lg:grid-cols-2 lg:gap-12">
                <section class="min-w-0">
                    <div
                        class="flex aspect-square max-h-[620px] items-center justify-center overflow-hidden rounded-3xl border border-slate-200 bg-white p-6 sm:p-10"
                    >
                        <img
                            v-if="activeImage"
                            :src="activeImage"
                            :alt="product.name"
                            class="max-h-full w-full object-contain"
                        />
                        <div
                            v-else
                            class="flex flex-col items-center gap-3 text-slate-400"
                        >
                            <Icon name="package" :size="56" /><span
                                class="text-sm"
                                >{{ copy.noImage }}</span
                            >
                        </div>
                    </div>
                    <div
                        v-if="product.images.length > 1"
                        class="mt-3 flex gap-3 overflow-x-auto pb-1"
                    >
                        <button
                            v-for="(image, index) in product.images"
                            :key="`${image.url}-${index}`"
                            type="button"
                            class="h-20 w-20 shrink-0 overflow-hidden rounded-xl border bg-white p-2"
                            :class="
                                activeImage === image.url
                                    ? 'border-emerald-600 ring-2 ring-emerald-100'
                                    : 'border-slate-200'
                            "
                            @click="activeImage = image.url"
                        >
                            <img
                                :src="image.url"
                                :alt="image.alt"
                                class="h-full w-full object-contain"
                            />
                        </button>
                    </div>
                </section>
                <section
                    class="min-w-0 rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8"
                >
                    <div class="flex flex-wrap gap-2">
                        <span
                            v-if="product.category"
                            class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-bold text-emerald-800"
                            >{{ product.category }}</span
                        ><span
                            v-if="product.brand"
                            class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600"
                            >{{ product.brand }}</span
                        >
                    </div>
                    <h1
                        class="mt-4 text-3xl leading-tight font-extrabold tracking-tight text-slate-950 sm:text-4xl"
                    >
                        {{ product.name }}
                    </h1>
                    <p
                        v-if="product.short_description"
                        class="mt-4 text-base leading-7 text-slate-600"
                    >
                        {{ product.short_description }}
                    </p>
                    <div class="mt-6 border-y border-slate-100 py-5">
                        <p class="text-sm font-semibold text-slate-500">
                            {{ copy.price }}
                        </p>
                        <p
                            class="mt-1 text-3xl font-extrabold tracking-tight text-emerald-700"
                        >
                            {{ formatPrice(displayedPrice)
                            }}<span
                                v-if="product.unit"
                                class="ml-2 text-sm font-medium text-slate-500"
                                >/ {{ product.unit }}</span
                            >
                        </p>
                        <p
                            v-if="
                                selectedVariant?.has_wholesale_pricing ||
                                product.has_wholesale_pricing
                            "
                            class="mt-2 inline-flex items-center gap-2 text-sm font-semibold text-slate-600"
                        >
                            <Icon name="building-store" :size="17" />{{
                                copy.wholesale
                            }}
                        </p>
                    </div>
                    <div v-if="product.variants.length > 1" class="mt-6">
                        <label
                            for="variant-select"
                            class="mb-2 block text-sm font-bold text-slate-800"
                            >{{ copy.variants }}</label
                        >
                        <select
                            id="variant-select"
                            v-model.number="selectedVariantId"
                            class="w-full rounded-xl border border-slate-200 bg-white px-3 py-3 text-sm outline-none focus:border-emerald-500 focus:ring-4 focus:ring-emerald-100"
                        >
                            <option
                                v-for="variant in product.variants"
                                :key="variant.id"
                                :value="variant.id"
                            >
                                {{ variant.name }} —
                                {{ formatPrice(variant.price) }}
                            </option>
                        </select>
                    </div>
                    <div class="mt-7 flex flex-wrap gap-3">
                        <button type="button" :disabled="!selectedVariant" @click="addToCart" class="inline-flex flex-1 items-center justify-center gap-2 rounded-xl bg-emerald-700 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-emerald-900/10 transition hover:bg-emerald-800 disabled:opacity-50">
                            <Icon name="shopping-cart" :size="18" /> {{ language === 'bn' ? 'কার্টে যোগ করুন' : 'Add to cart' }}
                        </button>
                        <Link href="/cart" class="inline-flex items-center justify-center rounded-xl border border-slate-200 px-5 py-3.5 text-sm font-bold text-slate-700 hover:bg-slate-50">{{ language === 'bn' ? 'কার্ট দেখুন' : 'View cart' }}</Link>
                    </div>
                    <div v-if="product.description" class="mt-7">
                        <h2 class="text-base font-bold text-slate-900">
                            {{ copy.details }}
                        </h2>
                        <div
                            class="mt-3 text-sm leading-7 whitespace-pre-line text-slate-600"
                        >
                            {{ product.description }}
                        </div>
                    </div>
                </section>
            </div>
        </main>
        <footer class="mt-10 border-t border-slate-200 bg-white">
            <div
                class="mx-auto max-w-7xl px-4 py-6 text-sm text-slate-500 sm:px-6 lg:px-8"
            >
                MADINA SCALE · © {{ new Date().getFullYear() }} Madina Scale
            </div>
        </footer>
    </div>
</template>
