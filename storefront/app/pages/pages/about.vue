<script setup lang="ts">
import StaticInfoPage from '../../components/StaticInfoPage.vue'

const page = {
  "eyebrow": "ABOUT MADINA",
  "title": "About MADINA Scale",
  "subtitle": "A Bangladesh-focused weighing solutions business serving customers and businesses.",
  "icon": "building-2",
  "notice": "Company profile draft based on information supplied for this project. Please review and approve all public-facing company statements before launch.",
  "sections": [
    {
      "title": "Our focus",
      "paragraphs": [
        "MADINA Scale focuses on weighing solutions and related equipment for business and everyday use. Our storefront is designed to help customers explore products, compare published details and request support."
      ]
    },
    {
      "title": "What customers can expect",
      "items": [
        {
          "title": "Product information",
          "description": "Clear product details to support a more informed purchase decision."
        },
        {
          "title": "Dealer reach",
          "description": "The business has stated a dealer presence across all 64 districts of Bangladesh."
        },
        {
          "title": "Customer assistance",
          "description": "Support for product enquiries, order guidance and after-sales questions."
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
