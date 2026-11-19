<script setup lang="ts">
import StaticInfoPage from '../../components/StaticInfoPage.vue'

const page = {
  "eyebrow": "PURCHASE SUPPORT",
  "title": "Return & Refund Policy",
  "subtitle": "Guidance for customers who need help with an order, return or refund request.",
  "icon": "refresh-ccw",
  "notice": "Provisional demo policy — return windows, eligibility, inspection rules and refund timelines must be confirmed and approved by MADINA before publication.",
  "sections": [
    {
      "title": "Before requesting a return",
      "paragraphs": [
        "Keep your invoice or order reference and the original product packaging where possible. Contact MADINA with the product details and reason for the request before sending anything back."
      ]
    },
    {
      "title": "Review & decision",
      "paragraphs": [
        "The team should review the order, product condition and applicable product terms, then communicate whether a return, replacement, repair or other remedy is available."
      ]
    },
    {
      "title": "Refund handling",
      "paragraphs": [
        "If a refund is approved, the method and expected processing time should be confirmed to the customer. This draft does not promise a specific refund period or unconditional return."
      ]
    }
  ]
} as const
useSeoMeta({ title: `${page.title} | MADINA Scale`, description: page.subtitle })
</script>

<template>
  <StaticInfoPage v-bind="page" />
</template>
