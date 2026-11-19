<script setup lang="ts">
import StaticInfoPage from '../../components/StaticInfoPage.vue'

const page = {
  "eyebrow": "PARTNER WITH MADINA",
  "title": "Become a Dealer",
  "subtitle": "Explore a potential dealer relationship for weighing solutions in your area.",
  "icon": "handshake",
  "notice": "This is an introductory enquiry page, not a dealer approval or appointment. Territory availability, eligibility and commercial terms require review by MADINA.",
  "ctaLabel": "Discuss a partnership",
  "ctaTo": "/pages/contact",
  "sections": [
    {
      "title": "Dealer enquiry information",
      "items": [
        {
          "title": "Your business",
          "description": "Share your business name, experience and current product categories."
        },
        {
          "title": "Your location",
          "description": "Tell us your district, service area and existing customer segments."
        },
        {
          "title": "Your requirements",
          "description": "Outline the product range, estimated demand and support you may need."
        }
      ]
    },
    {
      "title": "Next steps",
      "paragraphs": [
        "The MADINA team can review your enquiry and discuss any applicable requirements, documentation, territory and commercial terms."
      ]
    }
  ]
} as const
useSeoMeta({ title: `${page.title} | MADINA Scale`, description: page.subtitle })
</script>

<template>
  <StaticInfoPage v-bind="page" />
</template>
