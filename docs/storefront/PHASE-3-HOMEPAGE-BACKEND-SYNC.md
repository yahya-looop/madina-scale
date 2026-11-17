# MADINA Storefront — Homepage Backend Synchronization (Phase 3)

## Scope

This patch aligns the Nuxt homepage with the existing Laravel storefront contracts. Laravel remains authoritative for catalogue visibility, product data, prices, active flash sales, CMS banners, and cart operations.

## Homepage data sources

| Homepage content | Laravel source | Rendering rule |
|---|---|---|
| Hero carousel | `GET /api/v1/storefront/cms/banners?placement=homepage` | Active/scheduled-live CMS banners only; branded placeholder when none |
| Main categories | `GET /api/v1/storefront/categories` | Active, visible root categories only |
| Digital Scale subcategories | Category `children` in the same endpoint | Render only children belonging to the matching published category |
| Flash Deals | `GET /api/v1/storefront/flash-sales` | Active, visible, in-window sales and remaining eligible items only |
| Flash Deal image | New `items[].image_url` field on the same endpoint | Exact primary media belonging to the sale item's product; never borrow another product image |
| Homepage products | `GET /api/v1/storefront/products?page=1` | First page from Laravel pagination; no invented best-seller/rating claims |
| Product add-to-cart | Existing session cart API | Uses Laravel `default_variant_id`; server remains authoritative for price and availability |

## API change

`PricingPromotionController::publicFlashSales()` now eager-loads each sale item's product primary media and adds `image_url` to the item payload. The field is either the correct primary image URL or `null`. Existing fields and behavior remain unchanged.

A feature test covers the primary-media URL mapping for a public flash-sale item.

## Empty and failure states

- Homepage sections with a defined catalogue role remain visible when their endpoint succeeds but returns no records.
- Empty states explain that published data will appear automatically and provide a route to the catalogue where appropriate.
- If one or more requests fail, the homepage shows a retry action that refreshes only the four homepage data keys.
- Missing images use the shared branded placeholder; the frontend never substitutes unrelated product media.
- Prices, discount percentages, and sale end times are derived from API payloads. No mockup price, review, stock, countdown, delivery promise, or rating is hardcoded.

## Intentionally not implemented as fake functionality

The current public API does not provide a secure guest order-tracking lookup, newsletter subscription, or customer wishlist endpoints. The homepage therefore does not submit fabricated tracking/newsletter/wishlist actions. These sections require explicit backend contracts and security/business rules before activation.

The reference's warranty, authenticity, delivery, and support claims are also not shown as factual service promises unless the relevant business data is available.

## Local runtime reminder

The current `storefront/package.json` dev script uses port `8000`; `storefront/README.md` documents opening `http://127.0.0.1:8000`. The Nuxt proxy defaults to Laravel at `http://127.0.0.1:8001`. A browser tab on `localhost:3000` may be a different service and must not be used to judge this local Nuxt development build unless the local reverse proxy is intentionally configured for it.

## Validation performed for this patch

- PHP syntax lint for the modified promotion controller.
- Vue SFC parse and template compilation for the homepage.
- Source-level contract review against the checked-in Laravel routes/controllers and feature tests.

A full Nuxt production build and Laravel test suite must still be run in the user's supported local environment (the project requires a supported Node.js version declared in `storefront/package.json` and Laravel's configured database/test environment).
