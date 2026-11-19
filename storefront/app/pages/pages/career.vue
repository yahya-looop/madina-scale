<script setup lang="ts">
import StaticInfoPage from '../../components/StaticInfoPage.vue'

const page = {
  "eyebrow": "CAREERS AT MADINA",
  "title": "Build Your Career With Us",
  "subtitle": "Interested in working with a team focused on weighing solutions and customer service?",
  "icon": "briefcase-business",
  "notice": "No live vacancies are confirmed. The role categories below are examples only and should not be treated as active job openings.",
  "ctaLabel": "Send your CV enquiry",
  "ctaTo": "/pages/contact",
  "sections": [
    {
      "title": "Example career areas",
      "items": [
        {
          "title": "Sales & Business Development",
          "description": "Customer relationships, product consultation and business growth.",
          "tag": "EXAMPLE ROLE"
        },
        {
          "title": "Technical Service",
          "description": "Product inspection, troubleshooting and after-sales assistance.",
          "tag": "EXAMPLE ROLE"
        },
        {
          "title": "Operations & Support",
          "description": "Order coordination, inventory support and customer care.",
          "tag": "EXAMPLE ROLE"
        }
      ]
    },
    {
      "title": "How to express interest",
      "paragraphs": [
        "Send your name, contact details, preferred role area and a short introduction through the Contact page. Attachments and formal CV submission can be enabled when a dedicated recruitment workflow is configured."
      ]
    }
  ]
} as const
useSeoMeta({ title: `${page.title} | MADINA Scale`, description: page.subtitle })
</script>

<template>
  <StaticInfoPage v-bind="page" />
</template>
