<script setup lang="ts">
import StaticInfoPage from '../../components/StaticInfoPage.vue'

const page = {
  "eyebrow": "HELP CENTRE",
  "title": "Frequently Asked Questions",
  "subtitle": "Quick guidance for product selection, orders, delivery and after-sales support.",
  "icon": "circle-help",
  "ctaLabel": "Contact support",
  "ctaTo": "/pages/contact",
  "sections": [
    {
      "title": "Shopping & products",
      "items": [
        {
          "title": "How do I find a product?",
          "description": "Use the product catalogue, category menu or search bar to find available products."
        },
        {
          "title": "Are displayed prices final?",
          "description": "Review the product page and checkout summary. Any applicable charges should be shown before order confirmation."
        },
        {
          "title": "Can I ask for bulk pricing?",
          "description": "Yes. Send your product list and estimated quantities through the Contact page."
        }
      ]
    },
    {
      "title": "Orders & after-sales",
      "items": [
        {
          "title": "How can I track an order?",
          "description": "Open Track Order and enter the requested order details."
        },
        {
          "title": "Where can I find warranty information?",
          "description": "Visit the Warranty page and check the warranty documents supplied with your product."
        },
        {
          "title": "How do I contact support?",
          "description": "Use the phone, email or chat channels currently published on the Contact page."
        }
      ]
    }
  ]
} as const
useSeoMeta({ title: `${page.title} | MADINA Scale`, description: page.subtitle })
</script>

<template>
  <StaticInfoPage v-bind="page" />
</template>
