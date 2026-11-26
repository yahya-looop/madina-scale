<script setup lang="ts">
import StaticInfoPage from '../../components/StaticInfoPage.vue'

const page = {
  "eyebrow": "BUSINESS & BULK ORDERS",
  "title": "Wholesale Purchase",
  "subtitle": "Looking to purchase weighing equipment in quantity? Tell us about your business requirements.",
  "icon": "boxes",
  "notice": "Submit your requirements through the RFQ form. A quotation is prepared after the corporate sales team reviews your request; submission does not confirm an order.",
  "ctaLabel": "Submit a corporate RFQ",
  "ctaTo": "/corporate-rfq",
  "sections": [
    {
      "title": "Who can enquire?",
      "items": [
        {
          "title": "Retailers & Resellers",
          "description": "For shops and resellers sourcing products for onward sale."
        },
        {
          "title": "Institutions & Organisations",
          "description": "For schools, factories, laboratories and other organisations."
        },
        {
          "title": "Project & Bulk Buyers",
          "description": "For multi-unit requirements, procurement projects and repeat purchases."
        }
      ]
    },
    {
      "title": "What to include in your enquiry",
      "paragraphs": [
        "Share the product or equipment type, estimated quantity, delivery district and expected purchase timeline.",
        "Our team can review the requirement and confirm product availability, applicable pricing and delivery arrangements."
      ]
    }
  ]
} as const
useSeoMeta({ title: `${page.title} | MADINA Scale`, description: page.subtitle })
</script>

<template>
  <StaticInfoPage v-bind="page" />
</template>
