<script setup lang="ts">
import StaticInfoPage from '../../components/StaticInfoPage.vue'

const page = {
  "eyebrow": "OUR JOURNEY",
  "title": "Our History",
  "subtitle": "A journey shaped by experience, customer relationships and weighing solutions.",
  "icon": "history",
  "notice": "Draft timeline: the 2008 starting year is based on information supplied by the business. Add verified milestones before publishing this page.",
  "sections": [
    {
      "title": "A growing journey",
      "items": [
        {
          "title": "2008 — The beginning",
          "description": "MADINA states that its work in weighing solutions began in 2008.",
          "tag": "MILESTONE"
        },
        {
          "title": "Building customer relationships",
          "description": "Developing service and dealer relationships to support customers in different regions.",
          "tag": "JOURNEY"
        },
        {
          "title": "Today — Digital access",
          "description": "The online storefront brings product discovery, order tools and support information together.",
          "tag": "CURRENT"
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
