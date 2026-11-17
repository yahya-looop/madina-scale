# MADINA Scale — storefront development readiness

**Audit basis:** `madina-scale.zip` supplied on 16 November 2026, plus the Laravel test and Nuxt install/build output supplied in this conversation.

## 1. Target architecture confirmed

- **Laravel** remains the system of record and staff ERP: catalogue, pricing, inventory, customer identity, session cart, checkout/order creation, CMS, SEO endpoints and staff workflows.
- **Nuxt 4** owns public customer-facing website routes and server rendering.
- Laravel Inertia/Vue/Vite remains in place for the staff ERP. Do not remove the root Inertia entrypoint, ERP pages, Blade app shell, middleware, Composer packages or staff authentication routes.
- The versioned storefront API is grouped under `/api/v1/storefront` and intentionally uses Laravel's `web` middleware for session cookies and CSRF protection.

## 2. Existing storefront foundation

Nuxt source currently includes:

- Shared responsive default layout, site header, category navigation/mega-menu, search form, cart count and footer.
- Data-driven `/` homepage using active CMS homepage banners, category hierarchy, active flash sales and catalogue products.
- `/products` catalogue listing using reusable product cards.
- `/products/:slug` product detail.
- `/cart` and `/checkout`.
- `/order-success`.
- CMS pages at `/:slug` and reserved-slug pages at `/pages/:slug`.
- Shared cart composable, CMS page renderer, image component and dimension-labelled image placeholder.

Laravel exposes the supporting storefront endpoints for categories, products, flash sales, CMS banners/pages, cart, checkout, customer login/OTP/registration/logout and account data. Robots and sitemap endpoints remain in Laravel.

## 3. Legacy Laravel storefront cleanup status

The supplied project no longer contains the old Laravel customer-facing Inertia page controller/routes/page tree. The following are correctly absent from the application source:

- `app/Http/Controllers/StorefrontController.php`
- `routes/customer-auth.php`
- `resources/js/pages/Storefront/`
- `resources/js/components/storefront/`
- `resources/js/frontend/`

The following must remain because they serve the Nuxt API or staff ERP:

- `app/Http/Controllers/Api/V1/Storefront/`
- `app/Http/Controllers/StorefrontCartController.php` (checkout endpoint)
- `app/Http/Controllers/Auth/CustomerAuthController.php`
- `app/Http/Controllers/PublicCmsPageController.php` and `PublicSeoController.php`
- `routes/api.php`, `routes/web.php`, `routes/backend.php` and `routes/api/backend.php`
- ERP Inertia pages, `resources/views/app.blade.php`, root Vite configuration and Composer dependencies
- Laravel database, storage and migration files

The untracked `madina-nuxt-cutover-cleanup/` folder in the supplied ZIP is a previous patch payload/installer, not application source. This cleanup patch archives it under `storage/app/` and removes it from the project root.

## 4. Validation evidence

From the user's supplied command output:

- `PublicStorefrontTest`: **9 passed, 48 assertions**.
- `CustomerAuthenticationTest`: **5 passed, 27 assertions**.
- `PublicCmsSeoIntegrationTest`: **5 passed, 21 assertions**.
- Storefront `npm ci`: completed.
- Storefront `npm run build`: completed on the user's Windows environment after the Tailwind warning was resolved; Nuxt client, SSR server and Nitro output were generated.
- Phase 1 source review: all 17 Vue SFC files passed parser/template compilation; stylesheet parsing passed; `vue-tsc` passed against generated declarations for the added components.
- A fresh Nuxt production build was not re-run in the Linux review container because the supplied archive contains Windows-specific optional native packages and the container Node runtime (22.16.0) is below the project's declared supported range. Re-run Nuxt prepare/typecheck/build on the supported Windows runtime after applying the patch.
- Node runtime reported by the user: `v26.13.0`, which is within the declared engine range.

These results validate the tested API/build paths; they do not yet constitute end-to-end browser acceptance testing.

## 5. Known issues and development gaps

### Build/dependency hygiene

- The `NUXT_B7027` warning for `tailwindcss/nesting` was resolved by disabling that unused nesting plugin in `storefront/nuxt.config.ts`; the reviewed `main.css` does not use CSS nesting. Tailwind itself remains enabled.
- `npm audit` reported 10 advisories (6 low, 4 moderate) in the supplied lockfile. They are transitive through Nitro/Archiver/Rollup dependencies. `npm audit fix --force` proposes downgrading Nuxt and must not be used.
- `deepmerge` has no compatible fix demonstrated by the supplied dependency tree. Keep the lockfile stable until upstream dependency compatibility is confirmed.

### Website implementation still required

1. Create category landing routes and complete catalogue filtering/sort/search UX. The current API searches product name and short description, not SKU/model.
2. Complete product detail UX: image gallery, variant selection, stock/availability messaging, technical specifications and verified delivery information.
3. Build customer login, OTP verification, registration and account/order-history pages against the existing Laravel endpoints.
4. Define a secure public order-tracking API before building the Track Order page. Staff-side tracking fields exist, but the reviewed public storefront routes do not expose a secure customer lookup.
5. Complete cart/checkout UX: delivery-area validation, shipping calculation, order confirmation and payment-provider integration when selected. Current checkout exposes Cash on Delivery only.
6. Add full English/Bangla localization and a dedicated mobile hero image field/art direction.
7. Harden CMS rendering: `CmsPageContent.vue` uses `v-html`; add a maintained allow-list HTML sanitizer before treating CMS content as untrusted or enabling broad editor access. Apply the CMS canonical URL and complete Open Graph/structured metadata.
8. Add browser-level tests for same-origin proxying, session persistence, CSRF, cart across navigation, checkout idempotency and customer authentication.

## 6. Recommended implementation order

| Order | Workstream | Exit condition |
|---:|---|---|
| 1 | Shared storefront shell and data-driven homepage | **Implemented in Phase 1 patch**; responsive shell, CMS hero, category navigation, active flash sales and catalogue product cards |
| 2 | Catalogue and category pages | Category landing pages, advanced filters/sort and API-backed search expansion |
| 3 | Product detail | Gallery, variant selection, price/stock state and add-to-cart variant correctness |
| 4 | Customer identity | Login, OTP, registration, logout and account pages work with Laravel customer guard |
| 5 | Cart and checkout | Session survives navigation; delivery validation, shipping and order confirmation are verified |
| 6 | Tracking, SEO, security and QA | Secure public tracking contract, CMS sanitization, localization, canonical/meta tags and browser smoke tests |

## 7. Files and folders that must not be deleted

Do not delete `.env` files, database files, `vendor/`, root or storefront `node_modules/`, `storefront/.nuxt/`, `storefront/.output/`, `public/build/`, `storage/`, `.git/`, or existing `storage/app/nuxt-cutover-backup-*` backups as part of this cleanup. These are environment, dependency, generated or recovery data—not obsolete application source.

The repository `.gitignore` already excludes the main generated dependency/build directories. Keep the lockfiles (`composer.lock`, root `package-lock.json`, `storefront/package-lock.json`) committed.

---

**Readiness conclusion:** the Laravel/Nuxt boundary and API foundation are in place, the reported backend feature tests pass, and the user's Windows Nuxt production build succeeds without the earlier Tailwind warning. Phase 1 adds the shared storefront shell and first data-driven homepage. The project is not yet a complete production storefront; the remaining security, localization, tracking, account, catalogue and checkout work must be completed before public launch.


## Homepage data-flow correction — 2026-11-17

- Public read-only catalogue endpoints no longer depend on `storefront.checkout.enabled`. Browsing categories and products must remain available when checkout is paused; cart and checkout remain feature-flag controlled.
- Public flash-sale items now include the current approved primary product image URL and variant ID so the homepage does not need to guess or borrow imagery from an unrelated product.
- Homepage category, digital-scale subcategory, flash-deal and product sections retain their layout and display explicit empty/error states instead of disappearing when API data is empty or unavailable.
- Added compact homepage order-tracking and newsletter information panels without presenting non-functional lookup/subscription forms. Secure tracking and newsletter submission still require backend contracts.
- Nuxt continues to use the project's existing development port configuration; Laravel remains the private API upstream on port 8001 by default. The browser port should match the Nuxt process actually started.
- Added a regression test proving public category/product browsing remains available while checkout is disabled.
