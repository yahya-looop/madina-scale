<script setup lang="ts">
import StaticInfoPage from '../../components/StaticInfoPage.vue'

const page = {
  "eyebrow": "DELIVERY INFORMATION",
  "title": "Shipping & Delivery Policy",
  "subtitle": "What to expect when arranging delivery for a MADINA Scale order.",
  "icon": "truck",
  "notice": "Provisional demo policy — delivery coverage, charges, courier partners and estimated timelines must be confirmed for each order.",
  "sections": [
    {
      "title": "Delivery arrangements",
      "paragraphs": [
        "Delivery availability and estimated timing may depend on product size, stock location, destination and the delivery method available for the order. Confirm the final arrangement before payment or dispatch."
      ]
    },
    {
      "title": "Receiving your order",
      "paragraphs": [
        "Please check the package and product condition when receiving the delivery. If you notice visible damage or a missing item, contact MADINA promptly and keep the packaging and order details for reference."
      ]
    },
    {
      "title": "Delivery charges & delays",
      "paragraphs": [
        "Any applicable delivery charge should be communicated before order confirmation. Delays may occur due to stock, transport, weather or other operational conditions; the team should share updates when available."
      ]
    }
  ]
} as const
useSeoMeta({ title: `${page.title} | MADINA Scale`, description: page.subtitle })
</script>

<template>
  <StaticInfoPage v-bind="page" />
</template>
