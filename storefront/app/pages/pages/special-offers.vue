<script setup lang="ts">
import StaticInfoPage from '../../components/StaticInfoPage.vue'

const page = {
  "eyebrow": "OFFERS & SAVINGS",
  "title": "Special Offers",
  "subtitle": "Explore featured promotions and seasonal opportunities from MADINA Scale.",
  "icon": "badge-percent",
  "notice": "Demo content: these offer examples are placeholders for layout review. No discount or product price is currently being advertised as a live offer.",
  "ctaLabel": "Browse products",
  "ctaTo": "/products",
  "sections": [
    {
      "title": "Featured offer types",
      "intro": "This page is ready for the marketing team to replace these examples with approved campaigns.",
      "items": [
        {
          "title": "Seasonal Offers",
          "description": "Highlight selected weighing solutions during approved seasonal campaigns.",
          "tag": "DEMO"
        },
        {
          "title": "Bundle Savings",
          "description": "Showcase approved product bundles and any confirmed bundle pricing.",
          "tag": "DEMO"
        },
        {
          "title": "Business Purchase Offers",
          "description": "Present special terms for eligible business and institutional enquiries.",
          "tag": "DEMO"
        }
      ]
    },
    {
      "title": "How to use an offer",
      "paragraphs": [
        "Check the product page and campaign terms before placing an order.",
        "Offer availability, eligibility, stock and final pricing must be confirmed at checkout or by the MADINA team."
      ]
    }
  ]
} as const
useSeoMeta({ title: `${page.title} | MADINA Scale`, description: page.subtitle })
</script>

<template>
  <StaticInfoPage v-bind="page" />
</template>
