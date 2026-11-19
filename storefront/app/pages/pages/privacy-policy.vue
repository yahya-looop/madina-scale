<script setup lang="ts">
import StaticInfoPage from '../../components/StaticInfoPage.vue'

const page = {
  "eyebrow": "YOUR PRIVACY",
  "title": "Privacy Policy",
  "subtitle": "A plain-language overview of information that may be used when you browse or contact MADINA Scale.",
  "icon": "shield-check",
  "notice": "Provisional demo policy — confirm actual data practices, processors, retention periods and legal requirements before publication.",
  "sections": [
    {
      "title": "Information you provide",
      "paragraphs": [
        "Information may include your name, phone number, email address, delivery details, order information and messages you submit through the website or support channels."
      ]
    },
    {
      "title": "How information may be used",
      "paragraphs": [
        "Information may be used to process enquiries and orders, provide customer support, coordinate delivery, maintain account security and improve website services."
      ]
    },
    {
      "title": "Sharing, security & your choices",
      "paragraphs": [
        "Information should only be shared with service providers or partners where needed for the stated purpose and handled with appropriate safeguards. Contact MADINA to ask about information associated with your enquiry or account."
      ]
    }
  ]
} as const
useSeoMeta({ title: `${page.title} | MADINA Scale`, description: page.subtitle })
</script>

<template>
  <StaticInfoPage v-bind="page" />
</template>
