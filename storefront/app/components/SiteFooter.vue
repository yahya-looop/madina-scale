<template>
  <footer class="site-footer">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-brand">
          <NuxtLink to="/" class="brand-lockup brand-lockup--footer" aria-label="MADINA Scale home">
            <img src="/brand-mark.png" alt="" width="48" height="48">
            <span class="brand-lockup__text">MADINA<span>SCALE</span></span>
          </NuxtLink>
          <p>Your catalogue for weighing solutions and related equipment.</p>
        </div>

        <nav class="footer-column" aria-label="Shop links">
          <h2>Shop</h2>
          <NuxtLink to="/products">All Products</NuxtLink>
          <NuxtLink to="/pages/special-offers">Special Offers</NuxtLink>
          <NuxtLink to="/#flash-deals">Flash Sales</NuxtLink>
          <NuxtLink to="/cart">Shopping Cart</NuxtLink>
        </nav>

        <nav class="footer-column" aria-label="Customer support links">
          <h2>Customer Support</h2>
          <NuxtLink to="/track-order">Track Order</NuxtLink>
          <NuxtLink to="/pages/warranty">Warranty</NuxtLink>
          <NuxtLink to="/pages/contact">Contact Us</NuxtLink>
          <NuxtLink to="/checkout">Checkout</NuxtLink>
        </nav>

        <nav class="footer-column" aria-label="Explore links">
          <h2>Explore</h2>
          <NuxtLink to="/">Home</NuxtLink>
          <NuxtLink to="/products">Browse Catalogue</NuxtLink>
        </nav>

        <div class="footer-column footer-column--note">
          <h2>MADINA Scale</h2>
          <p>Product details, availability and pricing are shown from the published catalogue.</p>
        </div>
      </div>

      <div class="footer-bottom">
        <span>© {{ new Date().getFullYear() }} MADINA Scale. All rights reserved.</span>
        <span>Weighing solutions for business and everyday use.</span>
      </div>
    </div>
  </footer>
</template>
