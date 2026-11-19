<script setup lang="ts">
import StaticInfoPage from '../../components/StaticInfoPage.vue'

const page = {
  "eyebrow": "LEGAL INFORMATION",
  "title": "Terms & Conditions",
  "subtitle": "General terms for using the MADINA Scale online storefront.",
  "icon": "file-check",
  "notice": "Provisional demo policy — not legal advice. Have this document reviewed and approved by MADINA management and qualified Bangladesh counsel before publication.",
  "sections": [
    {
      "title": "Website use",
      "paragraphs": [
        "Use the website lawfully and provide accurate information when placing an order or contacting the business. Do not attempt to disrupt the website, misuse accounts or submit unlawful content."
      ]
    },
    {
      "title": "Products, prices & orders",
      "paragraphs": [
        "Product images and descriptions are provided for information. Availability, specifications and prices may change. An order is subject to confirmation by MADINA and the final order summary."
      ]
    },
    {
      "title": "Liability & updates",
      "paragraphs": [
        "Nothing in these provisional terms limits rights that cannot lawfully be excluded. MADINA should replace this draft with its approved terms, including governing law, dispute handling and business contact details."
      ]
    }
  ]
} as const
useSeoMeta({ title: `${page.title} | MADINA Scale`, description: page.subtitle })
</script>

<template>
  <StaticInfoPage v-bind="page" />
</template>
