<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
defineProps<{ order: { order_number: string; customer_phone: string; grand_total: string } }>();
const money = (value: string) => new Intl.NumberFormat('bn-BD', { style: 'currency', currency: 'BDT' }).format(Number(value));
</script>
<template>
    <Head title="অর্ডার জমা হয়েছে | Madina Scale"/>
    <main class="grid min-h-screen place-items-center bg-slate-50 px-4 py-10"><section class="w-full max-w-lg rounded-3xl border border-slate-200 bg-white p-8 text-center shadow-sm"><div class="mx-auto grid h-16 w-16 place-items-center rounded-full bg-emerald-100 text-3xl text-emerald-700">✓</div><p class="mt-5 text-xs font-extrabold tracking-[.2em] text-emerald-700">ORDER RECEIVED</p><h1 class="mt-2 text-2xl font-black">আপনার অর্ডারটি জমা হয়েছে!</h1><p class="mt-3 text-sm leading-6 text-slate-600">ধন্যবাদ। আমাদের অফিসিয়াল এক্সিকিউটিভ আপনার দেওয়া মোবাইল নম্বরে ফোন করে অর্ডারটি নিশ্চিত করবেন।</p><div class="mt-6 rounded-2xl bg-slate-50 p-5 text-left"><div class="flex justify-between gap-3 py-2 text-sm"><span class="text-slate-500">অর্ডার নম্বর</span><strong>{{ order.order_number }}</strong></div><div class="flex justify-between gap-3 py-2 text-sm"><span class="text-slate-500">মোবাইল</span><strong>{{ order.customer_phone }}</strong></div><div class="flex justify-between gap-3 border-t pt-3 text-sm"><span class="text-slate-500">মোট</span><strong class="text-emerald-700">{{ money(order.grand_total) }}</strong></div></div><p class="mt-4 text-xs text-amber-700">স্ট্যাটাস: Pending Confirmation — ফোনে নিশ্চিত হওয়ার পর অর্ডার Confirmed হবে।</p><Link href="/shop" class="mt-7 inline-flex rounded-xl bg-emerald-700 px-6 py-3 font-bold text-white">কেনাকাটা চালিয়ে যান</Link></section></main>
</template>
