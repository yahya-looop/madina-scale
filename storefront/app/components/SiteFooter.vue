<script setup lang="ts">
const currentYear = new Date().getFullYear()

const footerGroups = [
  {
    title: 'Shop',
    label: 'Shop links',
    links: [
      { label: 'All Products', to: '/products' },
      { label: 'Special Offers', to: '/pages/special-offers' },
      { label: 'Flash Sales', to: '/flash-sales' },
      { label: 'Shopping Cart', to: '/cart' },
      { label: 'Wholesale Purchase', to: '/pages/wholesale-purchase' },
    ],
  },
  {
    title: 'Customer Support',
    label: 'Customer support links',
    links: [
      { label: 'Track Order', to: '/track-order' },
      { label: 'Warranty & After-Sales', to: '/pages/warranty' },
      { label: 'Contact Us', to: '/pages/contact' },
      { label: 'FAQs', to: '/pages/faqs' },
    ],
  },
  {
    title: 'Company',
    label: 'Company links',
    links: [
      { label: 'About MADINA', to: '/pages/about' },
      { label: 'Our History', to: '/pages/our-history' },
      { label: 'Career', to: '/pages/career' },
      { label: 'Become a Dealer', to: '/pages/dealer-partnership' },
    ],
  },
  {
    title: 'Policies',
    label: 'Policies and legal links',
    links: [
      { label: 'Terms & Conditions', to: '/pages/terms-and-conditions' },
      { label: 'Privacy Policy', to: '/pages/privacy-policy' },
      { label: 'Return & Refund Policy', to: '/pages/return-refund-policy' },
      { label: 'Shipping & Delivery Policy', to: '/pages/shipping-delivery-policy' },
    ],
  },
]
</script>

<template>
  <footer class="site-footer">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-brand">
          <NuxtLink to="/" class="brand-lockup brand-lockup--footer" aria-label="MADINA Scale home">
            <img src="/brand-mark.png" alt="" width="48" height="48">
            <span class="brand-lockup__text">MADINA<span>SCALE</span></span>
          </NuxtLink>

          <p class="footer-brand__description">
            Your catalogue for weighing solutions and related equipment.
          </p>

          <div class="footer-trust">
            <span class="footer-trust__dot" aria-hidden="true"></span>
            We’re here to help you choose with confidence.
          </div>
        </div>

        <nav
          v-for="group in footerGroups"
          :key="group.title"
          class="footer-column"
          :aria-label="group.label"
        >
          <h2>{{ group.title }}</h2>
          <NuxtLink
            v-for="link in group.links"
            :key="link.to"
            :to="link.to"
          >
            {{ link.label }}
          </NuxtLink>
        </nav>

        <div class="footer-column footer-column--note">
          <h2>MADINA Scale</h2>
          <p>
            Product details, availability and pricing are shown from the published catalogue.
          </p>
          <NuxtLink class="footer-contact-link" to="/pages/contact">
            Get in touch <span aria-hidden="true">→</span>
          </NuxtLink>
        </div>
      </div>

      <div class="footer-bottom">
        <span>© {{ currentYear }} MADINA Scale. All rights reserved.</span>
        <span>Weighing solutions for business and everyday use.</span>
      </div>
    </div>
  </footer>
</template>

<style scoped>
.site-footer {
  background: #003d24;
  color: #f4fff8;
}

.footer-grid {
  display: grid;
  grid-template-columns: minmax(210px, 1.35fr) repeat(4, minmax(130px, 0.9fr)) minmax(175px, 1fr);
  gap: 28px;
  padding: 42px 0 32px;
}

.footer-brand,
.footer-column {
  min-width: 0;
}

.footer-brand__description,
.footer-column--note p {
  margin: 22px 0 0;
  color: rgba(244, 255, 248, 0.78);
  font-size: 13px;
  line-height: 1.8;
}

.footer-trust {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-top: 18px;
  color: rgba(244, 255, 248, 0.72);
  font-size: 11px;
  line-height: 1.5;
}

.footer-trust__dot {
  width: 7px;
  height: 7px;
  flex: 0 0 7px;
  border-radius: 50%;
  background: #8ce3ad;
  box-shadow: 0 0 0 4px rgba(140, 227, 173, 0.12);
}

.footer-column {
  display: flex;
  flex-direction: column;
  align-items: flex-start;
  gap: 11px;
}

.footer-column h2 {
  margin: 4px 0 7px;
  color: #fff;
  font-size: 14px;
  font-weight: 750;
  letter-spacing: -0.01em;
}

.footer-column a {
  color: rgba(244, 255, 248, 0.78);
  font-size: 12.5px;
  line-height: 1.55;
  text-decoration: none;
  transition: color 160ms ease, transform 160ms ease;
}

.footer-column a:hover {
  color: #a7f0c1;
  transform: translateX(2px);
}

.footer-column a:focus-visible {
  outline: 2px solid #a7f0c1;
  outline-offset: 4px;
  border-radius: 2px;
}

.footer-column--note p {
  margin-top: 0;
}

.footer-column .footer-contact-link {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-top: 5px;
  color: #a7f0c1;
  font-weight: 700;
}

.footer-bottom {
  display: flex;
  justify-content: space-between;
  gap: 18px;
  padding: 18px 0 20px;
  border-top: 1px solid rgba(244, 255, 248, 0.14);
  color: rgba(244, 255, 248, 0.72);
  font-size: 11px;
  line-height: 1.6;
}

@media (max-width: 1100px) {
  .footer-grid {
    grid-template-columns: repeat(3, minmax(0, 1fr));
    row-gap: 34px;
  }

  .footer-brand {
    grid-column: span 3;
  }
}

@media (max-width: 640px) {
  .footer-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 28px 20px;
    padding: 34px 0 26px;
  }

  .footer-brand {
    grid-column: span 2;
  }

  .footer-column--note {
    grid-column: span 2;
  }

  .footer-bottom {
    flex-direction: column;
    gap: 6px;
    padding: 16px 0 18px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .footer-column a {
    transition: none;
  }
}
</style>
