# MADINA Storefront Homepage — Source Audit and Implementation Notes

**Audit input:** the latest `madina-scale.zip` supplied in this conversation.
**Design authority:** `MADINA-Storefront-Design-System-Source-of-Truth-v1.1.0.md` and the owner-approved Marketplace homepage reference.

## 1. What was found in the supplied project

- The Nuxt 4 storefront already contains the first homepage foundation: a shared layout, header/footer, CMS banner request, category tree, catalogue product request, active flash-sale request, cart composable and reusable image placeholders.
- Laravel exposes the data required for the current homepage: homepage CMS banners, active visible categories with children and image URLs, paginated public products with primary image/price/default variant, and currently active storefront flash sales.
- Product catalogue responses do not include customer ratings/review counts. The homepage therefore does not invent or display them.
- The reviewed public API does not expose secure public order tracking, newsletter subscription, public brand listing, or customer review endpoints. Those controls are not represented as working features.
- The archive contains the official square brand mark and favicon, but no approved product/category/hero image assets outside generated output. Image slots must therefore use the design-system placeholder until the corresponding approved media is uploaded.
- The current development script in `storefront/package.json` binds Nuxt to `127.0.0.1:8000`; the screenshot supplied earlier was opened at `localhost:3000`. That port mismatch can cause the browser to show another/stale server. Confirm the actual process/port when checking the page.

## 2. Homepage implementation changes in this patch

- Reworked the hero to a shorter marketplace-style composition with CMS-driven title/image, clear catalogue actions and manual carousel controls.
- Reworked the main category cards into horizontal image-and-copy tiles.
- Made the Digital Scale subcategory strip horizontally scrollable on small screens and compact on desktop.
- Replaced the oversized Flash Sale information panels with a five-column product-card layout based on actual active sale items and server-supplied prices/end times.
- Kept catalogue products in a five-column desktop grid and reused the shared ProductCard component.
- Added category-driven Power Tools and Ladder collection panels only when those real categories exist.
- Reworked the trust/value section to a white-first icon-and-copy row without unsupported certification, delivery, warranty or rating claims.
- Refined shared navigation/footer and mobile menu presentation.
- Added the icon glyphs required by the new homepage.
- Kept image placeholders dimension-stable and hid technical dimension labels from public production views; development previews continue to show the dimensions.

## 3. Deliberately deferred (requires backend/media/business input)

1. **Track Order form and results:** no secure public customer-safe tracking lookup route is present in the reviewed API. Do not expose order data using order number alone.
2. **Newsletter subscription:** no public subscription endpoint or consent workflow is present.
3. **Sign in / Register / Wishlist header actions:** Laravel auth APIs exist, but dedicated Nuxt auth/account/wishlist pages are not part of this homepage patch.
4. **Brands navigation/filter:** product responses contain a brand label, but the reviewed public catalogue contract does not provide a brand listing/filter contract.
5. **Customer ratings/reviews:** no verified public review contract/data was found.
6. **Actual campaign/product/category photography:** upload approved artwork to the correct CMS/category/product media slot. Do not replace missing product photography with AI-generated or unrelated product images.
7. **Bengali localization and self-hosted Inter/Hind Siliguri fonts:** remain a separate cross-storefront implementation.

## 4. Data integrity rules retained

- Prices, sale prices, product names, categories, variants, sale end times and images are rendered from Laravel responses.
- Flash sale discount badges are calculated only when the server provides a lower sale price than the regular price.
- No fake countdown, popularity ranking, rating, stock claim, delivery promise or warranty claim is rendered.
- Product add-to-cart continues to use the server-provided default variant ID and existing session-cart composable.
- A flash-sale item without a matching first-page catalogue product still links to its real product detail page and uses the neutral image placeholder; it does not get an invented add-to-cart variant.

## 5. Validation status

The patch is source-only. Static Vue SFC parsing, TypeScript template compilation and CSS parsing are to be performed against the patched source. A full Nuxt production build must be re-run on the user's supported Windows Node runtime (the project declares Node 22.22.3+, 24.15.0+ or 26+). The archive's Windows `node_modules` and the review container's Node 22.16.0 are not a valid basis for claiming a fresh production build.

## 6. Browser acceptance checklist

- Compare the homepage at 1440px, 1024px, 768px, 390px and 360px.
- Confirm the browser is showing the Nuxt server from the current `storefront` folder, not a stale server on another port.
- Verify category cards navigate to `/products?category=<slug>`.
- Verify the search submits to `/products?q=<term>` and cart count updates after add-to-cart.
- Verify CMS banner arrows/dots change the active banner only when multiple published homepage banners exist.
- Verify only active public flash sale items appear and the displayed prices/end times match the API.
- Verify all image failures return to the branded placeholder without layout shift.
- Verify no horizontal overflow, clipped Bangla/English text, broken links or browser-console errors.
