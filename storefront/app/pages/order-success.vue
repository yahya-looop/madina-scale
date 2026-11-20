<script setup lang="ts">
const route = useRoute()
const awaitingBankVerification = computed(() => route.query.payment === 'awaiting_verification')

useSeoMeta({
  title: 'Order received | MADINA Scale',
  robots: 'noindex, nofollow',
})
</script>

<template>
  <main class="min-h-[75vh] bg-[#f5f8f6] px-4 py-12 text-slate-900 sm:py-16">
    <div class="mx-auto max-w-3xl">
      <div class="mb-7 text-center">
        <p class="text-[11px] font-extrabold uppercase tracking-[0.2em] text-emerald-800">MADINA Storefront</p>
        <h1 class="mt-2 text-3xl font-black tracking-tight text-slate-950 sm:text-4xl">
          {{ awaitingBankVerification ? 'Your order is submitted' : 'Thank you for your order' }}
        </h1>
        <p class="mt-3 text-sm leading-6 text-slate-600">
          {{ awaitingBankVerification ? 'Your bank transfer details have been received. Here is what happens next.' : 'Your order has been submitted successfully. Our team will contact you to coordinate delivery.' }}
        </p>
      </div>

      <section class="overflow-hidden rounded-[1.75rem] border border-slate-200 bg-white shadow-xl shadow-slate-900/5">
        <div class="bg-[#073f31] px-6 py-9 text-center text-white sm:px-10 sm:py-12">
          <div class="mx-auto grid size-20 place-items-center rounded-[1.75rem] border border-white/15 bg-white/10 text-4xl shadow-inner">
            {{ awaitingBankVerification ? '⌛' : '✓' }}
          </div>
          <p class="mt-6 text-[10px] font-extrabold uppercase tracking-[0.2em] text-emerald-200">
            {{ awaitingBankVerification ? 'Payment verification pending' : 'Order received' }}
          </p>
          <h2 class="mx-auto mt-2 max-w-xl text-2xl font-black tracking-tight sm:text-3xl">
            {{ awaitingBankVerification ? 'Awaiting bank payment verification' : 'We appreciate your trust in MADINA' }}
          </h2>
          <p class="mx-auto mt-3 max-w-xl text-sm leading-6 text-emerald-50/75">
            {{ awaitingBankVerification ? 'Our team will verify your transaction and record the funds as received. Your order will be confirmed only after both steps are completed.' : 'Our team will review your order and contact you if any delivery details need confirmation.' }}
          </p>
        </div>

        <div class="p-6 sm:p-9">
          <div v-if="route.query.order" class="rounded-2xl border border-slate-200 bg-slate-50 p-4 sm:p-5">
            <p class="text-[10px] font-extrabold uppercase tracking-[0.16em] text-slate-500">Your order reference</p>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">
              <p class="break-all text-xl font-black tracking-tight text-slate-950 sm:text-2xl">{{ route.query.order }}</p>
              <span class="rounded-full px-3 py-1.5 text-[10px] font-extrabold uppercase tracking-wider" :class="awaitingBankVerification ? 'bg-amber-100 text-amber-900' : 'bg-emerald-100 text-emerald-900'">{{ awaitingBankVerification ? 'Awaiting verification' : 'Submitted' }}</span>
            </div>
          </div>

          <div class="mt-7">
            <h3 class="text-sm font-extrabold text-slate-950">{{ awaitingBankVerification ? 'What happens next?' : 'What happens next' }}</h3>
            <div v-if="awaitingBankVerification" class="mt-4 space-y-4">
              <div class="flex gap-3"><span class="grid size-8 shrink-0 place-items-center rounded-xl bg-emerald-50 text-xs font-black text-emerald-900">01</span><div><p class="text-sm font-bold text-slate-900">Transaction submitted</p><p class="mt-1 text-xs leading-5 text-slate-500">Your bank transfer reference has been attached to this order.</p></div></div>
              <div class="flex gap-3"><span class="grid size-8 shrink-0 place-items-center rounded-xl bg-emerald-50 text-xs font-black text-emerald-900">02</span><div><p class="text-sm font-bold text-slate-900">Team verification</p><p class="mt-1 text-xs leading-5 text-slate-500">Our team will check the transaction and approve the payment details.</p></div></div>
              <div class="flex gap-3"><span class="grid size-8 shrink-0 place-items-center rounded-xl bg-emerald-50 text-xs font-black text-emerald-900">03</span><div><p class="text-sm font-bold text-slate-900">Funds received and order confirmed</p><p class="mt-1 text-xs leading-5 text-slate-500">After the funds are recorded as received, your order can be confirmed.</p></div></div>
            </div>
            <div v-else class="mt-4 rounded-2xl border border-emerald-100 bg-emerald-50/60 p-4"><p class="text-sm leading-6 text-slate-700">Keep your order reference for future communication. Our team will contact you to confirm delivery details.</p></div>
          </div>

          <div class="mt-8 flex flex-col gap-3 border-t border-slate-100 pt-6 sm:flex-row">
            <NuxtLink to="/products" class="inline-flex min-h-12 flex-1 items-center justify-center gap-2 rounded-xl bg-[#073f31] px-5 py-3 text-sm font-extrabold text-white shadow-lg shadow-emerald-950/10 transition hover:bg-emerald-950">Continue shopping <UiIcon name="arrow-right" :size="17" /></NuxtLink>
            <NuxtLink to="/" class="inline-flex min-h-12 flex-1 items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:border-emerald-700 hover:text-emerald-800">Back to home</NuxtLink>
          </div>
          <p class="mt-5 text-center text-[11px] leading-5 text-slate-400">Need assistance? <NuxtLink to="/pages/contact" class="font-bold text-emerald-800 hover:underline">Contact MADINA support</NuxtLink>.</p>
        </div>
      </section>
    </div>
  </main>
</template>
