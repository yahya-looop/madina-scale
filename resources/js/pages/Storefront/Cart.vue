<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import Icon from '@/components/dashboard/Icon.vue';

type CartItem = { variant_id: number; product_name: string; variant_name: string; sku: string | null; price: string; quantity: number };
const props = defineProps<{ items: CartItem[]; subtotal: number }>();
const form = useForm({ customer_name: '', customer_phone: '', division: '', district: '', area: '', address_line: '', notes: '', idempotency_key: globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(36).slice(2)}` });
const total = computed(() => props.items.reduce((sum, item) => sum + Number(item.price) * item.quantity, 0));
const cartError = computed(() => (form.errors as Record<string, string | undefined>).cart);
const money = (value: number) => new Intl.NumberFormat('bn-BD', { style: 'currency', currency: 'BDT', maximumFractionDigits: 2 }).format(value);
function checkout() { form.post('/checkout'); }
</script>

<template>
    <Head title="কার্ট ও অর্ডার | Madina Scale" />
    <div class="min-h-screen bg-slate-50 text-slate-900">
        <header class="border-b bg-white"><div class="mx-auto flex max-w-6xl items-center justify-between px-4 py-4"><Link href="/shop" class="text-lg font-black tracking-tight">MADINA SCALE</Link><Link href="/shop" class="text-sm font-semibold text-emerald-700">← কেনাকাটা চালিয়ে যান</Link></div></header>
        <main class="mx-auto grid max-w-6xl gap-6 px-4 py-8 lg:grid-cols-[1.1fr_.9fr]">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                <div class="mb-6 flex items-center justify-between"><div><p class="text-xs font-bold tracking-widest text-emerald-700">YOUR CART</p><h1 class="mt-1 text-2xl font-extrabold">আপনার কার্ট</h1></div><span class="rounded-full bg-emerald-50 px-3 py-1 text-sm font-bold text-emerald-800">{{ items.length }} পণ্য</span></div>
                <div v-if="!items.length" class="py-14 text-center"><Icon name="shopping-cart" :size="42" class="mx-auto text-slate-300"/><h2 class="mt-4 font-bold">আপনার কার্ট খালি</h2><p class="mt-1 text-sm text-slate-500">পছন্দের পণ্য কার্টে যোগ করে অর্ডার করুন।</p><Link href="/shop" class="mt-5 inline-flex rounded-xl bg-emerald-700 px-5 py-3 text-sm font-bold text-white">পণ্য দেখুন</Link></div>
                <div v-else class="divide-y divide-slate-100">
                    <article v-for="item in items" :key="item.variant_id" class="flex flex-wrap items-center justify-between gap-3 py-4 first:pt-0"><div class="min-w-0"><h3 class="font-bold">{{ item.product_name }}</h3><p class="mt-1 text-xs text-slate-500">{{ item.variant_name }} <span v-if="item.sku">· {{ item.sku }}</span></p><p class="mt-2 text-sm font-bold text-emerald-700">{{ money(Number(item.price)) }}</p></div><div class="flex items-center gap-2"><input v-model.number="item.quantity" type="number" min="1" max="99" aria-label="Quantity" class="w-16 rounded-lg border px-2 py-2 text-center text-sm"/><button type="button" @click="router.patch(`/cart/items/${item.variant_id}`, { quantity: item.quantity }, { preserveScroll: true })" class="rounded-lg border px-3 py-2 text-xs font-bold">আপডেট</button><button type="button" @click="router.delete(`/cart/items/${item.variant_id}`, { preserveScroll: true })" class="rounded-lg p-2 text-rose-600 hover:bg-rose-50" aria-label="Remove item"><Icon name="trash-2" :size="17"/></button></div></article>
                </div>
                <div v-if="items.length" class="mt-5 flex justify-between border-t pt-5 text-lg font-extrabold"><span>সর্বমোট</span><span>{{ money(total) }}</span></div>
            </section>
            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-7">
                <p class="text-xs font-bold tracking-widest text-emerald-700">QUICK CHECKOUT</p><h2 class="mt-1 text-2xl font-extrabold">সহজে অর্ডার করুন</h2><p class="mt-2 text-sm leading-6 text-slate-500">শুধু মোবাইল নম্বর দিলেই অর্ডার করা যাবে। নাম ও ঠিকানা এখন দিতে পারেন, অথবা আমাদের এক্সিকিউটিভ ফোনে জেনে নেবেন। কোনো অ্যাকাউন্ট, পাসওয়ার্ড বা OTP লাগবে না।</p>
                <form class="mt-6 space-y-4" @submit.prevent="checkout">
                    <label class="block text-sm font-semibold">আপনার নাম (ঐচ্ছিক)<input v-model="form.customer_name" maxlength="160" autocomplete="name" class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-3 outline-none focus:border-emerald-600" placeholder="সম্পূর্ণ নাম"/><small v-if="form.errors.customer_name" class="text-rose-600">{{ form.errors.customer_name }}</small></label>
                    <label class="block text-sm font-semibold">মোবাইল নম্বর<input v-model="form.customer_phone" required inputmode="tel" autocomplete="tel" maxlength="16" class="mt-1.5 w-full rounded-xl border border-slate-200 px-3 py-3 outline-none focus:border-emerald-600" placeholder="01XXXXXXXXX বা ০১XXXXXXXXX"/><small class="mt-1 block font-normal text-slate-500">বাংলাদেশি মোবাইল নম্বর দিন।</small><small v-if="form.errors.customer_phone" class="text-rose-600">{{ form.errors.customer_phone }}</small></label>
                    <div class="grid gap-3 sm:grid-cols-2"><label class="block text-sm font-semibold">বিভাগ<input v-model="form.division" class="mt-1.5 w-full rounded-xl border px-3 py-3" placeholder="যেমন: ঢাকা"/></label><label class="block text-sm font-semibold">জেলা (ঐচ্ছিক)<input v-model="form.district" class="mt-1.5 w-full rounded-xl border px-3 py-3" placeholder="জেলার নাম"/></label></div>
                    <label class="block text-sm font-semibold">এলাকা / থানা<input v-model="form.area" class="mt-1.5 w-full rounded-xl border px-3 py-3" placeholder="এলাকা বা থানা"/></label>
                    <label class="block text-sm font-semibold">সম্পূর্ণ ডেলিভারি ঠিকানা (ঐচ্ছিক)<textarea v-model="form.address_line" rows="3" class="mt-1.5 w-full rounded-xl border px-3 py-3" placeholder="বাসা/রোড/মার্কেট, পরিচিত স্থানসহ ঠিকানা"/></label>
                    <label class="block text-sm font-semibold">অতিরিক্ত নির্দেশনা <span class="font-normal text-slate-400">(ঐচ্ছিক)</span><textarea v-model="form.notes" rows="2" class="mt-1.5 w-full rounded-xl border px-3 py-3" placeholder="ডেলিভারি সংক্রান্ত কোনো তথ্য…"/></label>
                    <div v-if="cartError" class="rounded-lg bg-rose-50 p-3 text-sm text-rose-700">{{ cartError }}</div>
                    <div class="rounded-xl bg-emerald-50 p-4 text-sm leading-6 text-emerald-900">মোবাইল নম্বরটি ঠিক আছে কি না নিশ্চিত করুন। অর্ডার জমা দেওয়ার পর আমাদের টিম এই নম্বরে ফোন করবে। ফোনে নিশ্চিত হওয়ার আগে অর্ডারটি Pending থাকবে।</div>
                    <button type="submit" :disabled="form.processing || !items.length" class="flex w-full items-center justify-center gap-2 rounded-xl bg-emerald-700 px-5 py-4 font-extrabold text-white shadow-lg shadow-emerald-900/10 hover:bg-emerald-800 disabled:cursor-not-allowed disabled:opacity-50"><Icon name="phone-call" :size="18"/>{{ form.processing ? 'অর্ডার জমা হচ্ছে…' : `অর্ডার প্লেস করুন · ${money(total)}` }}</button>
                    <p class="text-center text-xs text-slate-400">আপনার তথ্য শুধু অর্ডার প্রসেসিং ও যোগাযোগের জন্য ব্যবহার করা হবে।</p>
                </form>
            </section>
        </main>
    </div>
</template>
