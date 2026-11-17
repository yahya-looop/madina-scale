# MADINA Scale — Storefront Phase 1: Shared Foundation & Homepage

**Implementation scope:** Nuxt 4 customer-facing storefront only  
**Backend boundary:** Laravel API and staff ERP remain unchanged  
**Design authority:** `MADINA-Storefront-Design-System-Source-of-Truth-v1.1.0.md`

## Delivered in this phase

- Added the Nuxt default layout and a shared responsive site header, category navigation/mega-menu, search form, cart count, and footer.
- Added the approved MADINA mark as a storefront static asset without altering the original artwork.
- Replaced the old `/` catalogue placeholder with a data-driven homepage.
- Homepage hero reads active CMS banners using `placement=homepage`; manual controls appear only when multiple banners exist.
- Category cards and navigation read the Laravel category hierarchy.
- Digital Scale subcategories, Power Tools and Ladder collection panels appear only when matching catalogue categories exist.
- Flash Deals read only active sale records from the existing Laravel endpoint. Prices and offer end times are sourced from Laravel. Deal cards link to product details; they do not add an arbitrary default variant because the public flash-sale response does not expose the sale item's variant ID.
- Homepage product cards read actual catalogue prices, promotion flags, image URLs and default variant IDs.
- Replaced `/products` duplicate homepage markup with a catalogue listing using the shared product card.
- Added reusable `StorefrontImage` and dimension-labelled `ImagePlaceholder` components. Missing or failed images do not become broken-image icons or unrelated product images.
- Applied the shared shell to the existing product detail, cart, checkout, order-success and CMS page experiences.
- Added skip navigation, semantic navigation labels, visible focus states and reduced-motion handling.

## Existing API contracts used (read-only in this phase)

- `GET /api/v1/storefront/categories`
- `GET /api/v1/storefront/products?q=&category=&page=`
- `GET /api/v1/storefront/cms/banners?placement=homepage`
- `GET /api/v1/storefront/flash-sales`
- Existing cart endpoints are used by the shared cart composable and product card actions.

No Laravel controller, route, migration, model, database, ERP or business-rule change is included in this phase.

## Deliberately not fabricated / deferred

- **Track Order:** staff-side shipment tracking data exists, but the reviewed API routes do not expose a secure public order-tracking lookup. Do not build a public lookup that trusts an order number alone. Define and implement a verified customer-safe backend contract first.
- **Customer account/login:** Laravel customer-auth endpoints exist, but Nuxt login, registration, OTP and account pages remain to be implemented.
- **Language switch and self-hosted fonts:** the approved design requires English and Bangla plus Inter/Hind Siliguri coverage. This phase does not expose a cosmetic switch without full translated interface strings/catalog localization, and no self-hosted font binaries were present in the reviewed project. CSS uses the approved font-family order with system fallbacks until licensed WOFF2 assets are supplied.
- **Search by SKU/model:** the current public catalogue endpoint searches product name and short description. Header copy therefore does not promise SKU/model search. Extend the API before advertising those search dimensions.
- **Mobile hero artwork:** CMS banner API currently exposes one image URL, not a dedicated mobile art direction. The homepage preserves the source image rather than fabricating a mobile derivative.
- **Newsletter, customer reviews, warranty/returns and delivery promises:** omitted until verified data or supporting APIs/policies are available.
- **CMS HTML sanitization:** `CmsPageContent.vue` still renders CMS HTML with `v-html`. Add a maintained allow-list sanitizer before enabling untrusted or broadly delegated CMS publishing.
- **Image observability:** broken/missing images fall back to the correct branded slot, but centralized image-failure logging/monitoring is not wired because no storefront observability endpoint was present in the reviewed project.
- **Homepage section editor:** the reviewed public API exposes banners and pages, but no public homepage section payload. This phase uses existing CMS banners plus catalogue data; it does not invent a section API.

## Validation

- Vue SFC parser/compiler check: 17 `.vue` files, zero parse/template compile errors.
- `vue-tsc --noEmit -p tsconfig.json`: passed against the generated Nuxt declarations updated for the new auto-imported components.
- A fresh Nuxt prepare/build could not be executed in the Linux review container: the archive contains Windows-specific optional native packages, and the container Node runtime is 22.16.0 while the project's declared supported runtime is Node 22.22.3+, 24.15.0+, or 26+. Run `npx nuxt prepare`, `npm run typecheck` and `npm run build` on the project's supported Windows Node runtime after applying this patch.

## Next implementation order

1. Category landing pages and advanced catalogue filtering/sorting.
2. Product detail gallery, variant selection, stock and price state.
3. Customer login, OTP, registration and account/order history.
4. Secure public order tracking contract and UI.
5. Checkout delivery calculation, address validation and payment-provider integration when selected.
6. CMS HTML sanitization, localization, SEO metadata and browser-level end-to-end QA.
