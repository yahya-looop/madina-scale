<script setup lang="ts">
import type { UiIconName } from "../types/icons"
type InfoItem = Readonly<{ title: string; description: string; tag?: string }>
type InfoSection = Readonly<{
  title: string
  intro?: string
  items?: readonly InfoItem[]
  paragraphs?: readonly string[]
}>
const props = defineProps<{
  eyebrow: string
  title: string
  subtitle: string
  icon: UiIconName
  sections: readonly InfoSection[]
  notice?: string
  ctaLabel?: string
  ctaTo?: string
}>()
</script>

<template>
  <main class="info-page">
    <section class="info-hero">
      <div class="info-container info-hero__inner">
        <div class="info-hero__copy">
          <p class="info-eyebrow"><UiIcon :name="icon" :size="16" /> {{ eyebrow }}</p>
          <h1>{{ title }}</h1>
          <p class="info-hero__subtitle">{{ subtitle }}</p>
          <NuxtLink v-if="ctaLabel && ctaTo" class="info-cta" :to="ctaTo">
            {{ ctaLabel }} <UiIcon name="arrow-right" :size="16" />
          </NuxtLink>
        </div>
        <div class="info-hero__visual" aria-hidden="true">
          <div class="info-hero__orb info-hero__orb--outer"></div>
          <div class="info-hero__orb info-hero__orb--inner"></div>
          <div class="info-hero__icon"><UiIcon :name="icon" :size="48" /></div>
        </div>
      </div>
    </section>

    <div class="info-container info-content">
      <div v-if="notice" class="info-notice">
        <UiIcon name="info" :size="19" />
        <p>{{ notice }}</p>
      </div>

      <section v-for="(section, index) in sections" :key="section.title" class="info-section">
        <div class="info-section__heading">
          <span class="info-section__number">{{ String(index + 1).padStart(2, '0') }}</span>
          <div>
            <h2>{{ section.title }}</h2>
            <p v-if="section.intro">{{ section.intro }}</p>
          </div>
        </div>
        <div v-if="section.items?.length" class="info-card-grid">
          <article v-for="item in section.items" :key="item.title" class="info-card">
            <span v-if="item.tag" class="info-card__tag">{{ item.tag }}</span>
            <h3>{{ item.title }}</h3>
            <p>{{ item.description }}</p>
          </article>
        </div>
        <div v-else-if="section.paragraphs?.length" class="info-prose">
          <p v-for="paragraph in section.paragraphs" :key="paragraph">{{ paragraph }}</p>
        </div>
      </section>

      <div class="info-bottom">
        <div>
          <span class="info-eyebrow">NEED MORE INFORMATION?</span>
          <h2>We’re ready to help.</h2>
          <p>Talk to the MADINA Scale team about products, orders or business enquiries.</p>
        </div>
        <NuxtLink class="info-cta" to="/pages/contact">Contact MADINA <UiIcon name="arrow-right" :size="16" /></NuxtLink>
      </div>
    </div>
  </main>
</template>

<style scoped>
.info-page{--green:#087d3b;--ink:#172337;--muted:#65758a;color:var(--ink);background:#fff}
.info-container{width:min(1200px,calc(100% - 40px));margin-inline:auto}
.info-hero{position:relative;overflow:hidden;background:linear-gradient(115deg,#fbfefc 0%,#f1faf4 58%,#e5f7eb 100%);border-bottom:1px solid #e2f0e6}
.info-hero__inner{min-height:235px;display:grid;grid-template-columns:1.3fr .7fr;align-items:center;gap:24px;padding-block:28px}
.info-hero__copy{max-width:760px}.info-eyebrow{display:flex;align-items:center;gap:8px;margin:0 0 10px;color:var(--green);font-size:11px;font-weight:850;letter-spacing:.13em}
.info-hero h1{margin:0;color:var(--ink);font-size:clamp(32px,4vw,46px);font-weight:900;letter-spacing:-.045em;line-height:1.08}
.info-hero__subtitle{max-width:680px;margin:12px 0 0;color:#596a7d;font-size:15px;line-height:1.7}
.info-hero__visual{position:relative;display:grid;place-items:center;min-height:170px}
.info-hero__orb{position:absolute;aspect-ratio:1;border:1px solid #8bcda155;border-radius:50%}
.info-hero__orb--outer{width:190px}.info-hero__orb--inner{width:142px;background:#ffffff55}
.info-hero__icon{display:grid;place-items:center;width:92px;height:92px;border:1px solid #c7e8d0;border-radius:27px;background:linear-gradient(145deg,#fff,#e5f7ea);color:var(--green);box-shadow:0 18px 42px #267c4718;transform:rotate(-5deg)}
.info-cta{display:inline-flex;align-items:center;justify-content:center;gap:9px;min-height:42px;margin-top:18px;padding:0 18px;border-radius:999px;background:var(--green);color:#fff;font-size:13px;font-weight:800;text-decoration:none;box-shadow:0 7px 18px #087d3b20;transition:transform .2s,box-shadow .2s}
.info-cta:hover{transform:translateY(-2px);box-shadow:0 11px 24px #087d3b28}
.info-content{padding-block:34px 60px}
.info-notice{display:flex;gap:12px;align-items:flex-start;margin-bottom:30px;padding:15px 17px;border:1px solid #dceee1;border-radius:13px;background:#f5fbf6;color:#42634e}
.info-notice :deep(svg){flex:0 0 auto;color:var(--green);margin-top:1px}.info-notice p{margin:0;font-size:13px;line-height:1.65}
.info-section{padding-block:22px 28px;border-bottom:1px solid #edf1ee}
.info-section__heading{display:flex;align-items:flex-start;gap:14px;margin-bottom:18px}
.info-section__number{display:grid;place-items:center;flex:0 0 38px;width:38px;height:38px;border-radius:12px;background:#eaf7ee;color:var(--green);font-size:12px;font-weight:900}
.info-section h2{margin:0;color:var(--ink);font-size:22px;font-weight:850;letter-spacing:-.025em}
.info-section__heading p{margin:6px 0 0;color:var(--muted);font-size:13px;line-height:1.65}
.info-card-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:13px}
.info-card{position:relative;padding:19px;border:1px solid #e3ece5;border-radius:15px;background:linear-gradient(145deg,#fff,#f7fbf8);box-shadow:0 6px 20px #143d2208}
.info-card__tag{display:inline-block;margin-bottom:10px;padding:4px 8px;border-radius:99px;background:#eaf7ee;color:var(--green);font-size:10px;font-weight:850}
.info-card h3{margin:0 0 7px;color:#1c2b3c;font-size:15px;font-weight:800}
.info-card p,.info-prose p{margin:0;color:var(--muted);font-size:13px;line-height:1.75}
.info-prose{max-width:900px;display:grid;gap:12px}.info-bottom{display:flex;align-items:center;justify-content:space-between;gap:20px;margin-top:34px;padding:25px 27px;border:1px solid #dfeee3;border-radius:18px;background:linear-gradient(110deg,#f2faf4,#fff)}
.info-bottom h2{margin:0;color:var(--ink);font-size:23px;font-weight:900}.info-bottom p{margin:7px 0 0;color:var(--muted);font-size:13px;line-height:1.65}.info-bottom .info-cta{flex:0 0 auto;margin:0}
@media(max-width:760px){.info-hero__inner{grid-template-columns:1fr;min-height:0;padding-block:26px}.info-hero__visual{display:none}.info-card-grid{grid-template-columns:1fr 1fr}.info-bottom{align-items:flex-start;flex-direction:column}.info-bottom .info-cta{margin:0}}
@media(max-width:520px){.info-container{width:calc(100% - 32px)}.info-hero h1{font-size:34px}.info-hero__subtitle{font-size:14px}.info-card-grid{grid-template-columns:1fr}.info-content{padding-block:24px 42px}.info-section h2{font-size:19px}}
@media(prefers-reduced-motion:reduce){.info-cta{transition:none}.info-cta:hover{transform:none}}
</style>
