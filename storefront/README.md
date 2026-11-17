# MADINA Scale — Nuxt Storefront

The customer-facing website is served by Nuxt 4. Laravel remains the system of record and API/backend for the catalogue, pricing, inventory, carts, checkout, orders, customer identity/authentication, CMS content, SEO data and ERP workflows.

The Laravel Inertia application is retained for the staff ERP only. Do not remove `resources/js/app.ts`, ERP pages, `resources/views/app.blade.php`, Inertia middleware, the Inertia Composer dependency, or the staff authentication routes.

## Runtime requirements

- Node.js **22.22.3 or newer in the 22.x line**, **24.15.0 or newer in the 24.x line**, or **26.0.0 or newer**.
- npm installed with Node.js.
- PHP and the Laravel dependencies installed at the repository root.

The lockfile currently resolves Nuxt 4.6.x. Older Node 22 releases (including 22.16) are not supported by that Nuxt release. The supported range is also declared in `package.json` under `engines`.

## Local development (Windows PowerShell)

Terminal 1 — from the Laravel project root:

```powershell
php artisan serve --host=127.0.0.1 --port=8001
```

Terminal 2 — from the `storefront` directory:

```powershell
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
npm ci
npm run dev
```

Open `http://127.0.0.1:8000`.

If `storefront/node_modules` was copied from a different operating system or Node.js version, `npm ci` replaces it using the lockfile. Keep `package-lock.json`; do not delete it.

## Proxy and API configuration

`NUXT_PUBLIC_API_BASE` must remain the same-origin path `/api/v1/storefront`. The Nuxt development server proxies `/api/**`, `/backend/**`, `/build/**`, `/storage/**`, `/robots.txt` and `/sitemap.xml` to Laravel.

`NUXT_LARAVEL_PROXY_TARGET` controls the private Laravel origin and defaults to `http://127.0.0.1:8001`. Do not expose Laravel credentials, payment credentials, SMS credentials, Meta CAPI tokens or TikTok Events API tokens through `NUXT_PUBLIC_*`.

## Storefront API contract

- `GET /api/v1/storefront/csrf-token`
- `GET /api/v1/storefront/categories`
- `GET /api/v1/storefront/products?q=&category=&page=`
- `GET /api/v1/storefront/products/{slug}`
- `GET /api/v1/storefront/flash-sales`
- `GET /api/v1/storefront/cms/banners`
- `GET /api/v1/storefront/cms/pages/{slug}`
- `GET /api/v1/storefront/cart`
- `POST /api/v1/storefront/cart/items`
- `PATCH /api/v1/storefront/cart/items/{variant}`
- `DELETE /api/v1/storefront/cart/items/{variant}`
- `POST /api/v1/storefront/checkout`
- `GET /api/v1/storefront/auth/me`
- `POST /api/v1/storefront/auth/login/password`
- `POST /api/v1/storefront/auth/login/otp`
- `POST /api/v1/storefront/auth/login/otp/verify`
- `POST /api/v1/storefront/auth/register/otp`
- `POST /api/v1/storefront/auth/register`
- `POST /api/v1/storefront/auth/logout`
- `GET /api/v1/storefront/account`

The storefront API group intentionally uses Laravel's `web` middleware so session-backed cart/customer authentication and CSRF protection continue to work. Public read-only catalogue endpoints (categories, products, product details, CMS banners and flash sales) remain available independently of the checkout feature flag. Only cart mutations and checkout are gated by the `storefront.checkout.enabled` flag. Browser requests must include credentials and state-changing requests must send a CSRF token.

## Current Nuxt foundation

The Nuxt storefront now has a shared responsive header/navigation/footer, CMS-driven homepage hero banners, catalogue-driven category navigation, active flash-sale panels, product cards, branded image placeholders and the `/products` catalogue route. Product/category/banner/flash-sale data is read from the Laravel storefront API; Laravel remains the source of truth.

The existing product detail, cart, checkout, order confirmation and CMS page routes use the shared shell. Customer login, registration and account screens are not yet implemented as Nuxt pages.

## Production topology

Run Nuxt Nitro as a Node service on internal port 3000 and Laravel on internal port 8001. The supplied Nginx example sends public page requests to Nuxt and `/api/*`, `/backend/*`, `/build/*`, `/storage/*`, `/robots.txt` and `/sitemap.xml` to Laravel. Adjust hostnames, TLS and upstream ports for the deployment environment.

## Migration boundaries

- Keep staff ERP authentication on Laravel's `web` guard.
- Keep customer authentication on Laravel's separate `customer` guard.
- Keep OTP generation, hashing, expiry, throttling and SMS delivery inside Laravel.
- The old Laravel customer-facing Inertia pages and page routes are retired.
- Laravel commerce services, session-backed JSON APIs, CMS/SEO endpoints and the entire staff ERP remain active.

## Current readiness notes

- The Laravel storefront JSON API and staff ERP remain separate: Laravel owns business rules, sessions, customer identity, checkout and CMS data; Nuxt owns public pages.
- The shared shell and data-driven homepage sections are in place. The homepage keeps category, flash-deal and product section geometry visible with truthful empty/error states when the API has no published records. Category landing routes, advanced catalogue filters/sort, product gallery/variant selector and customer account pages remain future work.
- Customer login, registration and account pages are not implemented yet, although the Laravel customer-authentication API is available.
- The public CMS HTML is currently rendered by the Nuxt CMS page component. Add an allow-list HTML sanitizer before untrusted or broadly delegated CMS publishing is enabled.
- The current storefront checkout presents Cash on Delivery only. Payment-provider integration and final shipping calculation remain future work.
- The latest user-reported Nuxt build completed successfully without the Tailwind PostCSS nesting warning after explicitly disabling the unused `tailwindcss/nesting` plugin. The supplied `npm audit` output reported 10 dependency advisories (6 low, 4 moderate). Do not use `npm audit fix --force`; resolve these through compatible upstream dependency updates and retest.
