<script setup lang="ts">
useSeoMeta({
  title: 'Warranty & After-Sales Support | MADINA Scale',
  description: 'Learn how to keep your MADINA Scale purchase information ready and request warranty or after-sales assistance.',
  ogTitle: 'Warranty & After-Sales Support | MADINA Scale',
  ogDescription: 'Warranty guidance and after-sales support from MADINA Scale.',
})
const warrantySteps = [
  { number: '01', title: 'Keep your purchase documents', description: 'Keep your invoice, order confirmation and any warranty card supplied with the product.' },
  { number: '02', title: 'Check the product details', description: 'Note the product name, model, serial number (if available) and purchase date.' },
  { number: '03', title: 'Contact our support team', description: 'Tell us what happened and share the purchase details so the team can guide you through the next steps.' },
  { number: '04', title: 'Follow the service guidance', description: 'Our team will review the information and explain the applicable inspection or service process.' },
]
const coveredExamples = [
  'A product fault that appears during the applicable warranty period.',
  'A defect in materials or workmanship, subject to product-specific terms.',
  'A concern that can be verified against the invoice and warranty documentation.',
]
const excludedExamples = [
  'Damage caused by accident, impact, liquid, fire or unsuitable storage.',
  'Damage caused by misuse, overloading, incorrect installation or operation outside product guidance.',
  'Normal wear, cosmetic marks, consumables or accessories unless the product terms say otherwise.',
  'Repairs, alterations or tampering by an unauthorised person.',
  'Issues caused by external electrical supply, environmental conditions or other external factors.',
]
</script>

<template>
  <main class="wc-page warranty-page">
    <section class="wc-hero warranty-hero">
      <div class="wc-container wc-hero__inner">
        <div class="wc-hero__copy">
          <p class="wc-eyebrow"><UiIcon name="shield-check" :size="16" /> AFTER-SALES CARE</p>
          <h1>Warranty &amp; <span>Support</span></h1>
          <p>We want you to feel confident about your weighing solution. Keep your purchase details close and contact our team whenever you need help.</p>
          <div class="wc-hero__actions">
            <NuxtLink class="wc-button wc-button--light" to="/contact">Contact support <UiIcon name="arrow-right" :size="16" /></NuxtLink>
            <NuxtLink class="wc-button wc-button--outline" to="/track-order">Track an order</NuxtLink>
          </div>
        </div>
        <div class="wc-hero__visual" aria-hidden="true">
          <span class="wc-hero__orbit wc-hero__orbit--one" />
          <span class="wc-hero__orbit wc-hero__orbit--two" />
          <div class="wc-hero__shield"><UiIcon name="shield-check" :size="72" /></div>
        </div>
      </div>
    </section>

    <section class="wc-container wc-section">
      <div class="wc-section-heading">
        <p class="wc-eyebrow">HOW IT WORKS</p>
        <h2>Getting warranty assistance</h2>
        <p>A few details help us understand your request and direct you to the right support.</p>
      </div>
      <div class="wc-step-grid">
        <article v-for="step in warrantySteps" :key="step.number" class="wc-step-card">
          <span class="wc-step-number">{{ step.number }}</span>
          <h3>{{ step.title }}</h3>
          <p>{{ step.description }}</p>
        </article>
      </div>
    </section>

    <section class="wc-section wc-section--soft">
      <div class="wc-container wc-policy-grid">
        <article class="wc-policy-card">
          <div class="wc-policy-card__icon"><UiIcon name="shield-check" :size="24" /></div>
          <h2>What may be covered</h2>
          <p>Subject to the warranty supplied with the product and confirmation by MADINA, warranty support may apply to:</p>
          <ul class="wc-check-list">
            <li v-for="item in coveredExamples" :key="item">{{ item }}</li>
          </ul>
        </article>
        <article class="wc-policy-card">
          <div class="wc-policy-card__icon wc-policy-card__icon--muted"><UiIcon name="package-search" :size="24" /></div>
          <h2>What is generally not covered</h2>
          <p>The following are common exclusions unless your product-specific warranty states otherwise:</p>
          <ul class="wc-cross-list">
            <li v-for="item in excludedExamples" :key="item">{{ item }}</li>
          </ul>
        </article>
      </div>
    </section>

    <section class="wc-container wc-section">
      <div class="wc-note">
        <UiIcon name="shield-check" :size="22" />
        <div>
          <h2>Important warranty information</h2>
          <p>This page provides general, provisional guidance. The warranty period, coverage, conditions and remedies may differ by product and are determined by the warranty card, invoice, product documentation and applicable law. Please confirm the terms supplied with your purchase. Nothing on this page reduces any rights you may have under applicable consumer law.</p>
        </div>
      </div>
    </section>

    <section class="wc-container wc-bottom-cta">
      <div>
        <p class="wc-eyebrow">NEED A HAND?</p>
        <h2>Let’s get your issue to the right team.</h2>
        <p>Share your product and purchase details with our support team.</p>
      </div>
      <NuxtLink class="wc-button wc-button--solid" to="/contact">Get in touch <UiIcon name="arrow-right" :size="16" /></NuxtLink>
    </section>
  </main>
</template>

<style scoped>
.wc-page{--wc-green:#087d3b;--wc-dark:#075b36;--wc-ink:#142033;--wc-muted:#64748b;--wc-line:#e2ebe5;color:var(--wc-ink);background:#fff}
.wc-container{width:min(1440px,calc(100% - 48px));margin-inline:auto}
.wc-hero{position:relative;isolation:isolate;overflow:hidden;color:var(--wc-ink);background:linear-gradient(112deg,#fbfefc 0%,#f1faf4 54%,#e5f7eb 100%);border-bottom:1px solid #e3f1e7}
.wc-hero:before,.wc-hero:after{position:absolute;z-index:-1;content:"";pointer-events:none;border-radius:50%;filter:blur(1px)}
.wc-hero:before{width:430px;height:220px;right:12%;bottom:-168px;background:#c8efd5aa}
.wc-hero:after{width:320px;height:180px;right:-80px;top:-120px;background:#d8f4e1aa}
.wc-hero__inner{min-height:218px;display:grid;grid-template-columns:1.25fr .75fr;align-items:center;gap:24px;padding-block:22px}
.wc-hero__copy{max-width:760px}.wc-eyebrow{display:flex;align-items:center;gap:9px;margin:0 0 9px;color:var(--wc-green);font-size:11px;font-weight:900;letter-spacing:.13em}
.wc-hero .wc-eyebrow{color:#087d3b}.wc-hero h1{margin:0;color:#172337;font-size:clamp(32px,3.6vw,44px);line-height:1.04;letter-spacing:-.045em;font-weight:900}
.wc-hero h1 span{color:#087d3b}
.wc-hero__copy>p:not(.wc-eyebrow){max-width:680px;margin:9px 0 0;color:#536477;font-size:14px;line-height:1.55}
.wc-hero__actions{display:flex;flex-wrap:wrap;gap:9px;margin-top:15px}
.wc-button{display:inline-flex;align-items:center;justify-content:center;gap:9px;min-height:38px;padding:0 16px;border-radius:999px;font-size:13px;font-weight:800;text-decoration:none;transition:transform .2s,background .2s,border-color .2s,box-shadow .2s}
.wc-button:hover{transform:translateY(-2px)}
.wc-button--light{background:#087d3b;color:#fff;box-shadow:0 6px 16px #087d3b20}
.wc-button--outline{border:1px solid #9bc9a9;color:#075b36;background:#ffffffb8}
.wc-button--solid{background:var(--wc-green);color:#fff}
.wc-hero__visual{position:relative;display:grid;place-items:center;min-height:174px}
.wc-hero__orbit{position:absolute;border:1px solid #8bd5a355;border-radius:50%;aspect-ratio:1}
.wc-hero__orbit--one{width:172px}.wc-hero__orbit--two{width:226px}
.wc-hero__shield{width:104px;height:104px;display:grid;place-items:center;border:1px solid #c4e8ce;border-radius:29px;background:linear-gradient(145deg,#fff,#e2f7e8);color:#078044;box-shadow:0 18px 42px #267c4720;transform:rotate(-7deg)}
.wc-hero__shield :deep(svg){filter:drop-shadow(0 4px 5px #087d3b20)}
.wc-section{padding-block:64px}.wc-section-heading{max-width:700px;margin-bottom:28px}.wc-section-heading h2,.wc-policy-card h2,.wc-note h2,.wc-bottom-cta h2{margin:0;color:var(--wc-ink);font-size:clamp(25px,3vw,34px);line-height:1.15;font-weight:900;letter-spacing:-.03em}
.wc-section-heading>p:last-child{margin:12px 0 0;color:var(--wc-muted);line-height:1.7}
.wc-step-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px}.wc-step-card{min-height:190px;padding:23px;border:1px solid var(--wc-line);border-radius:18px;background:#fff;box-shadow:0 8px 26px #0c39200a}
.wc-step-number{display:inline-grid;place-items:center;width:38px;height:38px;border-radius:12px;background:#e9f6ee;color:var(--wc-green);font-size:13px;font-weight:900}
.wc-step-card h3{margin:18px 0 8px;font-size:17px;font-weight:850}.wc-step-card p,.wc-policy-card p,.wc-note p,.wc-bottom-cta p{margin:0;color:var(--wc-muted);font-size:14px;line-height:1.75}
.wc-section--soft{background:#f5faf6}.wc-policy-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.wc-policy-card{padding:30px;border:1px solid var(--wc-line);border-radius:20px;background:#fff}
.wc-policy-card__icon{width:46px;height:46px;display:grid;place-items:center;border-radius:14px;background:#e9f6ee;color:var(--wc-green);margin-bottom:18px}.wc-policy-card__icon--muted{background:#f1f4f6;color:#526274}
.wc-policy-card h2{font-size:23px;margin-bottom:10px}.wc-check-list,.wc-cross-list{display:grid;gap:12px;margin:20px 0 0;padding:0;list-style:none}
.wc-check-list li,.wc-cross-list li{position:relative;padding-left:28px;color:#344256;font-size:14px;line-height:1.65}.wc-check-list li:before,.wc-cross-list li:before{position:absolute;left:0;top:0;font-weight:900}.wc-check-list li:before{content:"✓";color:#078044}.wc-cross-list li:before{content:"–";color:#738195}
.wc-note{display:flex;gap:16px;padding:23px 25px;border:1px solid #dcebe1;border-radius:18px;background:#f8fcf9;color:var(--wc-green)}.wc-note>svg{flex:0 0 auto;margin-top:2px}.wc-note h2{font-size:19px;margin-bottom:8px}.wc-bottom-cta{display:flex;align-items:center;justify-content:space-between;gap:24px;margin-bottom:64px;padding:30px;border:1px solid var(--wc-line);border-radius:20px;background:linear-gradient(110deg,#f4faf6,#fff)}
.wc-bottom-cta h2{font-size:25px}.wc-bottom-cta p{margin-top:8px}
@media(max-width:900px){.wc-step-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.wc-hero__inner{grid-template-columns:1fr .42fr}.wc-hero__orbit--two{width:190px}}
@media(max-width:640px){.wc-container{width:min(100% - 32px,1440px)}.wc-hero__inner{grid-template-columns:1fr;min-height:0;padding-block:25px}.wc-hero__visual{display:none}.wc-hero h1{font-size:36px}.wc-hero__copy>p:not(.wc-eyebrow){font-size:13px}.wc-hero__actions{margin-top:14px}.wc-button{min-height:38px;padding-inline:14px}.wc-section{padding-block:44px}.wc-step-grid,.wc-policy-grid{grid-template-columns:1fr}.wc-step-card{min-height:0}.wc-policy-card{padding:23px}.wc-bottom-cta{align-items:flex-start;flex-direction:column;margin-bottom:42px;padding:24px}}
@media(prefers-reduced-motion:reduce){.wc-button{transition:none}.wc-button:hover{transform:none}}
</style>