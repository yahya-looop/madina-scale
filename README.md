# Madina Scale — Architecture & Engineering Blueprint

**Project:** Madina Scale  
**Document type:** Living architecture, engineering and design reference  
**Architecture decision:** Modular Laravel backend + independent Nuxt storefront in a monorepo  
**Status:** Existing application retained; architecture is being formalized and incrementally hardened

---

## 1. Product Vision

Madina Scale is a centralized commerce and business operations platform. It combines an internal ERP/admin workspace with customer-facing digital channels.

The platform is intended to provide:

- Product, category, brand, variant and media management
- Customer, CRM, lead and B2B management
- Sales, orders, returns, payments and shipping operations
- Inventory, warehouse, stock movement and procurement workflows
- CMS, SEO and public website content management
- Marketing integrations and campaign analytics
- Business intelligence, reporting and operational dashboards
- Identity, access control, auditability and system configuration
- Future customer mobile apps, partner portals and additional websites

The platform must grow through explicit domain boundaries and stable contracts. New applications must reuse central business capabilities without duplicating business rules or transactional data.

## 2. Architecture Decision Record

### 2.1 Chosen architecture

Madina Scale will use:

**One Git monorepo + one central Laravel modular-monolith backend + independently deployable frontend applications.**

Current applications:

1. **Laravel application at repository root**
   - Staff ERP/admin interface (Laravel + Inertia + Vue)
   - Backend business logic, database access, authentication and authorization
   - Versioned APIs for the Storefront and future clients
   - CMS, SEO, media and public infrastructure endpoints

2. **Nuxt application in `storefront/`**
   - Customer-facing public website
   - SSR/SEO-capable pages and customer experience
   - Reads and mutates business data through Laravel APIs
   - Does not own authoritative product, price, stock, order or customer records

This is a monorepo, not a single frontend application. Laravel and Nuxt have separate runtime/build processes and separate dependency manifests.

### 2.2 What this architecture is not

- It is not a microservices architecture.
- It does not require Laravel to be moved into a `backend/` directory.
- It does not require separate Git repositories for the current applications.
- It does not mean that all applications share one frontend build or one deployment process.

A modular monolith is the appropriate current backend style. Microservices or separate repositories should be considered only when team ownership, deployment independence, scaling or operational requirements justify the additional complexity.

### 2.3 Repository structure — current and intended

Keep the existing Laravel-at-root structure. Do not move Laravel directories solely to make the tree look symmetrical.

```text
madina-scale/
├── app/                         # Laravel backend application
│   ├── Http/
│   │   ├── Controllers/
│   │   ├── Middleware/
│   │   └── Requests/
│   ├── Jobs/
│   ├── Models/
│   ├── Notifications/
│   ├── Policies/
│   ├── Providers/
│   └── Services/
├── bootstrap/
├── config/
├── database/
├── public/
├── resources/                   # Staff ERP Inertia/Vue UI
│   ├── css/
│   ├── js/
│   └── views/
├── routes/
│   ├── api.php
│   ├── api/
│   │   └── backend.php
│   ├── backend.php
│   ├── console.php
│   └── web.php
├── storage/
├── tests/
├── storefront/                  # Independent Nuxt customer website
│   ├── app/
│   │   ├── assets/
│   │   ├── components/
│   │   ├── composables/
│   │   ├── layouts/
│   │   ├── pages/
│   │   ├── plugins/
│   │   └── types/
│   ├── public/
│   ├── package.json
│   ├── package-lock.json
│   ├── nuxt.config.ts
│   ├── .env.example
│   └── README.md
├── docs/                         # Architecture decisions, API and operations docs
├── .github/
├── composer.json                 # Laravel/PHP dependencies
├── composer.lock
├── package.json                  # Laravel ERP/Vite frontend dependencies
├── package-lock.json
├── pnpm-workspace.yaml           # Existing workspace configuration; review its role
├── .gitignore
└── README.md
```

Generated directories such as `vendor/`, root `node_modules/`, `storefront/node_modules/`, `storefront/.nuxt/` and `storefront/.output/` are local/build artifacts and must not be committed.

### 2.4 Application boundaries

| Concern | Owner |
|---|---|
| Staff ERP pages and navigation | Laravel Inertia/Vue in `resources/` |
| Public customer website | Nuxt in `storefront/` |
| Business rules and validation | Laravel |
| Authoritative transactional database | Laravel/PostgreSQL |
| Product, price and inventory truth | Laravel |
| Cart and checkout rules | Laravel |
| Customer identity and authorization | Laravel |
| CMS content and publishing state | Laravel |
| Public page rendering and UX | Nuxt |
| API contracts | Laravel, documented for all clients |
| Future mobile/partner clients | Independent applications consuming Laravel APIs |

The Laravel Inertia application is the internal staff ERP. It must not be confused with the Nuxt Storefront. Do not remove Inertia, staff routes, ERP pages, or staff authentication as part of Storefront work.

---

## 3. High-Level System Architecture

```text
                         MADINA SCALE MONOREPO
                                  |
             +--------------------+--------------------+
             |                                         |
      LARAVEL APPLICATION                         NUxt STOREFRONT
      Repository root                             /storefront
             |                                         |
      +------+----------------+                 Public customer UI
      |                       |                 SSR / SEO / pages
 Staff ERP (Inertia/Vue)   Laravel API                  |
      |                       |                         |
      |               /api/v1/storefront                |
      |               /api/v1/backend                   |
      |               /api/v1/head-office               |
      |               /api/v1/webhooks                  |
      |                       |                         |
      +-----------------------+-------------------------+
                              |
                  Laravel application/domain logic
                              |
             +----------------+----------------+
             |                |                |
         PostgreSQL         Redis           Storage
      transactional DB   cache/queue      public/private media
                              |
                       Queue workers
                              |
                 External provider adapters
          (payment, courier, SMS, marketing, email)
```

### 3.1 Core principles

1. Laravel is the system of record and final security authority.
2. Frontends never enforce authorization by hiding UI alone.
3. Business rules stay in Laravel, not in Nuxt pages or Vue components.
4. The Storefront consumes stable APIs; it does not connect directly to PostgreSQL.
5. Each frontend has its own dependencies, environment and build.
6. Backend and frontend can be developed and deployed independently.
7. Domain boundaries are explicit; avoid unnecessary abstractions.
8. Critical writes are transactional and auditable.
9. External integrations are isolated behind contracts/adapters.
10. API changes are versioned and compatibility is considered.
11. Analytics is derived from operational data and must not overwrite it.
12. Background work is queued rather than blocking user-facing requests.

---

## 4. Technology Stack and Verified Baseline

### 4.1 Backend

- PHP: Composer constraint currently declares `^8.3`
- Laravel: lockfile resolves `v13.34.0`
- Laravel Sanctum: lockfile resolves `v4.3.3`
- Inertia Laravel: `^3.0`
- PostgreSQL: configured as the intended primary database
- Redis: intended for cache, queue and realtime infrastructure
- Laravel Queue, Events, Listeners, Notifications, Policies/Gates
- Pest/PHPUnit, Pint and Larastan

Do not describe a PHP patch version or PostgreSQL server version as verified merely from a project configuration file. Record the actual installed runtime versions from the development/production environment when updating the infrastructure baseline.

### 4.2 Staff ERP frontend

- Vue 3
- Inertia.js 3
- Vite
- Tailwind CSS 4
- TypeScript
- Laravel Wayfinder
- Root `package.json` and root `package-lock.json`

### 4.3 Public Storefront

- Nuxt 4 (lockfile resolves `4.6.4`)
- Vue 3
- Tailwind integration
- TypeScript
- GSAP
- Separate `storefront/package.json` and `storefront/package-lock.json`

The Storefront package declares supported Node.js ranges in its `engines` field. Follow that declaration and the lockfile rather than assuming every Node 22 release is supported.

### 4.4 Dependency ownership

- `composer.json` / `composer.lock`: Laravel/PHP dependencies.
- Root `package.json` / `package-lock.json`: staff ERP/Vite dependencies.
- `storefront/package.json` / `storefront/package-lock.json`: Nuxt Storefront dependencies.

Do not combine Storefront packages into the root package manifest merely for convenience. Do not delete lockfiles to reduce repository size.

---

## 5. Backend Architecture — Modular Monolith

The backend should evolve toward domain-oriented modules while retaining Laravel's conventional application structure where it is already working.

The current codebase contains conventional `app/Http/Controllers`, `app/Models`, `app/Jobs`, `app/Policies`, `app/Services` and related directories. The domain-oriented structure below is the target organization, not a claim that every domain folder already exists.

### 5.1 Target domain map

```text
Identity & Access
├── users, authentication, sessions, customer identity
├── roles, permissions, policies, API credentials
└── security and access audit

Catalog
├── products, variants, categories, brands, attributes
└── product media and publication state

Customers & CRM
├── customer profiles, groups, addresses
├── leads, activities, assignment and conversion
└── segmentation

Sales & Orders
├── orders, order items, lifecycle and status
├── discounts, taxes, returns and refunds
└── order operations

Inventory & Procurement
├── stock, warehouses/locations, movements
├── reservations, transfers and adjustments
└── procurement and goods receipt

Pricing & Promotions
├── price rules, promotions, coupons
└── flash sales

Payments & Shipping
├── payment abstraction and provider adapters
├── courier/shipping adapters and tracking
└── delivery lifecycle

CMS & SEO
├── pages, sections, banners, media
├── revisions/publication and SEO metadata
└── sitemap and robots endpoints

Marketing & Analytics
├── campaign/provider integrations
├── normalized campaign metrics and attribution
└── operational and analytical reporting

Communication & Support
├── customer support and live chat
├── internal staff chat
└── notifications, presence and attachments

System & Developer
├── module/menu registry and feature flags
├── system settings, audit and security events
└── integration configuration and health
```

### 5.2 Suggested organization as the codebase grows

Do not perform a large folder migration now. Extract domain classes incrementally when a module is actively refactored.

A possible future organization:

```text
app/
├── Actions/
├── Contracts/
├── Domains/
│   ├── Identity/
│   ├── Access/
│   ├── Catalog/
│   ├── Customers/
│   ├── CRM/
│   ├── Sales/
│   ├── Inventory/
│   ├── Pricing/
│   ├── Payments/
│   ├── Shipping/
│   ├── CMS/
│   ├── SEO/
│   ├── Marketing/
│   ├── Analytics/
│   ├── Communication/
│   └── System/
├── Events/
├── Exceptions/
├── Http/
├── Jobs/
├── Listeners/
├── Mail/
├── Models/
├── Notifications/
├── Policies/
├── Providers/
├── Rules/
├── Services/
└── Support/
```

This is a direction, not a mandatory directory checklist. Keep Laravel conventions where they remain clear. Avoid moving stable classes without a concrete benefit and tests.

### 5.3 Domain dependency rules

- A domain owns its business rules and defines its public operations.
- Cross-domain workflows should use explicit Actions, Services, Events or Contracts.
- Avoid circular dependencies.
- Avoid controllers calling unrelated models to implement business workflows.
- Keep persistence details out of frontend applications.
- Shared abstractions belong in stable contracts/support areas, not in a generic dumping ground.

---

## 6. Design Patterns and Application Flow

Use patterns only when they solve a real problem.

### Action pattern

Use a focused Action for one meaningful business operation, for example:

- `CreateOrder`
- `CancelOrder`
- `RefundOrder`
- `ConvertLead`
- `TransferInventory`
- `AssignRole`
- `SyncMarketingCampaigns`

### Service pattern

Use Services to coordinate multi-step operations or infrastructure that does not belong in a single model/controller.

### Repository pattern

Use only when persistence abstraction or query complexity materially benefits from it. Do not create wrappers that merely repeat Eloquent methods.

### Strategy and Adapter patterns

Use provider contracts for interchangeable external systems such as payment, courier, SMS and marketing providers. Provider-specific request/response details must not leak throughout business domains.

### Event and Job patterns

Use events for decoupled side effects and queue jobs for slow or retryable work, such as campaign synchronization, notification fan-out, report generation, exports and media processing.

### Preferred request flow

```text
HTTP Request
    ↓
Authentication / Middleware
    ↓
Form Request validation
    ↓
Thin Controller
    ↓
Action / Domain Service
    ↓
Domain rules + transaction
    ↓
Eloquent model / query
    ↓
Event / Job where appropriate
    ↓
API Resource / predictable response
```

---

## 7. API Architecture

Laravel exposes versioned APIs for first-party and future applications.

### 7.1 Current API surfaces

The inspected route configuration includes:

- `/api/v1/storefront/*` — public catalogue, CMS, customer session, cart and checkout endpoints
- `/api/v1/backend/*` — staff ERP API namespace
- `/api/v1/head-office/*` — existing Sanctum-protected integration contract
- `/api/v1/webhooks/*` — provider callback routes

Legacy endpoints/aliases may remain for compatibility. New first-party API development should use the appropriate versioned namespace.

### 7.2 API rules

- Use `/api/v1/...` for current contracts.
- Introduce `/api/v2/...` for breaking changes when required.
- Keep controllers thin.
- Validate with Form Requests or equivalent dedicated validators.
- Authorize on the backend for every protected operation.
- Use API Resources/DTOs for stable output.
- Use consistent pagination and validation error structures.
- Never expose SQL, stack traces, secrets or internal exception details.
- Document endpoint purpose, authentication, request/response shape and error cases.
- Add API and authorization tests for critical endpoints.

### 7.3 Response philosophy

A predictable response shape should be used consistently where appropriate:

```json
{
  "data": {},
  "meta": {},
  "message": null
}
```

Do not force this exact envelope on every response if framework conventions or an existing public contract require another shape. Consistency and backward compatibility matter more than a mechanical wrapper.

### 7.4 API ownership

Laravel owns:
- Validation and business rules
- Pricing and stock calculations
- Cart and checkout rules
- Order state transitions
- Customer identity and authorization
- CMS publication state
- Integration credentials and provider calls

Nuxt owns:
- Page rendering and navigation
- Client-side presentation state
- Form interaction and UX
- SEO rendering based on backend-provided content
- API consumption and graceful loading/error/empty states

---

## 8. Authentication, Authorization and Developer Role

### 8.1 Separate user contexts

The current Storefront documentation describes:
- Staff ERP authentication through Laravel's `web` guard.
- Customer authentication through a separate `customer` guard.
- Storefront API routes using Laravel's `web` middleware to preserve session and CSRF behavior.

Preserve this separation unless an explicit migration plan and tests justify changing it.

### 8.2 Authorization model

```text
User
  ↓
Role
  ↓
Permission
  ↓
Policy / Gate
  ↓
Resource
  ↓
Action
```

Permission naming convention:

```text
module.resource.action
```

Examples:

```text
sales.orders.view
sales.orders.create
sales.orders.update
sales.orders.cancel
inventory.transfers.create
marketing.meta.manage
analytics.sales.view
```

Use the existing permission namespace consistently; do not rename permissions casually because persisted role assignments and middleware may depend on them.

### 8.3 Developer role

The highest product role is named **Developer**. Product terminology intentionally does not use “Super Admin”.

Developer capabilities may include managing users, roles, permissions, modules, feature flags, system settings, integrations and critical controls. Sensitive Developer actions must remain auditable.

### 8.4 Module state versus authorization

Possible module states:

```text
ENABLED
DISABLED
LOCKED
HIDDEN
```

Module visibility is not authorization. Hiding a menu item never replaces backend middleware, policies or permission checks.

---

## 9. Security Architecture

Security is cross-cutting and must be enforced by Laravel and deployment infrastructure.

Required controls:

- Secure password hashing and session configuration
- Login throttling and account recovery protection
- 2FA where enabled
- CSRF protection for session-authenticated browser mutations
- Correct CORS, cookie, SameSite and Secure settings for production topology
- Backend authorization on every protected resource/action
- Request validation and output transformation
- Rate limits for sensitive/public endpoints
- File upload MIME/type/size validation and safe storage
- Secret management and environment separation
- Least-privilege database and infrastructure credentials
- Audit logs and security events
- Secure webhook verification
- Safe error messages and structured internal logs
- Dependency and vulnerability review

Production:

```dotenv
APP_ENV=production
APP_DEBUG=false
```

Never commit `.env`, credentials, API tokens, OAuth secrets, private keys, payment credentials or webhook secrets. If a secret is exposed, rotate it.

---

## 10. Database and Data Ownership

### 10.1 Transactional source of truth

PostgreSQL is the intended primary transactional database.

Use:
- Foreign keys and unique constraints
- Indexes based on actual query patterns
- Transactions for multi-step business writes
- Explicit ownership/scoping
- UTC timestamps
- Soft deletes only when business requirements justify them
- UUID/ULID only where entity requirements justify the choice

Do not introduce a second authoritative Storefront database.

### 10.2 Data ownership

| Data | Owner |
|---|---|
| Products, variants, categories, brands | Laravel Catalog |
| Prices and promotions | Laravel Pricing |
| Inventory and reservations | Laravel Inventory |
| Customers and identity | Laravel Customer/Identity |
| Cart and checkout | Laravel Commerce |
| Orders and payments | Laravel Sales/Payments |
| CMS pages, banners and SEO | Laravel CMS/SEO |
| Marketing source data | Provider adapters + Laravel |
| Analytics aggregates | Laravel analytics/reporting layer |

Nuxt may cache or temporarily hold presentation data, but Laravel remains authoritative.

### 10.3 Analytics separation

Operational records:
- Orders
- Payments
- Products
- Customers
- Inventory

Derived analytical data:
- Revenue trends
- Conversion rates
- Customer cohorts
- Campaign performance
- ROAS
- Product performance
- Traffic and attribution

Aggregations must not overwrite transactional records or compromise financial/order integrity.

---

## 11. Cache, Queue, Events and Realtime

Redis is the planned shared infrastructure for cache, queues, rate limiting where appropriate and realtime support.

Use queues for slow or retryable workloads:
- Marketing provider synchronization
- Report generation and exports
- Email/SMS/notification delivery
- Media processing
- Large imports
- Notification fan-out

Realtime chat/support should use an explicit event/broadcasting design. Do not make HTTP requests wait for non-critical external integrations.

Before production workloads depend on Redis, configure and test Redis, queue workers, retries, failed-job handling, monitoring and deployment supervision.

---

## 12. Storefront Architecture

### 12.1 Responsibility

The Nuxt Storefront is an independent customer-facing application located at `storefront/`.

It owns:
- Public page routes and layouts
- Responsive navigation and page components
- Product/category presentation
- Cart and checkout UX
- Customer login/account UX
- CMS page rendering
- SEO metadata rendering
- Loading, empty and error states

It does not own authoritative business rules or transactional records.

### 12.2 Current source structure

The inspected Storefront includes:

```text
storefront/app/
├── app.vue
├── assets/
├── components/
├── composables/
├── layouts/
├── pages/
├── plugins/
└── types/
```

Current source includes homepage, product catalogue/detail, cart, checkout, login, registration, account, order-success, order-tracking, flash-sales and CMS/static information page routes. The presence of a route file does not by itself prove that its complete workflow is production-ready; verify each flow with integration/E2E tests.

### 12.3 API communication

The Storefront uses the Laravel versioned Storefront API. Its configured public API base is intended to remain same-origin:

```dotenv
NUXT_PUBLIC_API_BASE=/api/v1/storefront
```

The current Laravel Storefront API route group uses `web` middleware for session-backed customer authentication, cart and CSRF behavior. Browser requests must include credentials and state-changing requests must send the CSRF token.

Do not expose private backend credentials through `NUXT_PUBLIC_*` variables.

### 12.4 Current proxy configuration issue to resolve

The Storefront `.env.example` and Storefront README describe:

```dotenv
NUXT_LARAVEL_PROXY_TARGET=http://127.0.0.1:8001
```

However, the inspected `storefront/nuxt.config.ts` currently hardcodes `http://127.0.0.1:8001` in its proxy route rules rather than reading `NUXT_LARAVEL_PROXY_TARGET`.

Therefore the documentation and actual configuration are not fully synchronized. Update the Nuxt config to read the private runtime/environment variable, or update the documented configuration to match the chosen implementation. Prefer a private runtime config/environment variable so development and production upstreams can differ without source edits.

### 12.5 CMS content safety

CMS content is authored in Laravel and rendered by Nuxt. Before enabling untrusted or broadly delegated HTML publishing, enforce an allow-list HTML sanitizer and test unsafe markup handling. Content authorization and publication status must be enforced by Laravel.

---

## 13. Development, Runtime and Deployment Architecture

### 13.1 Local development

The inspected Storefront documentation currently describes:

Terminal 1 — Laravel root:

```powershell
php artisan serve --host=127.0.0.1 --port=8001
```

Terminal 2 — Storefront:

```powershell
Set-Location storefront
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
npm ci
npm run dev
```

Nuxt development server is configured for `127.0.0.1:8000`.

Run the Laravel ERP Vite process separately when working on staff UI. Run queue workers when testing queued workflows. Database, Redis and other services must be running according to the local environment configuration.

### 13.2 Independent build/deploy

The two applications must have independent build and release steps:

**Laravel**
- Composer install from `composer.lock`
- Laravel environment and key configuration
- Database migrations under controlled release procedure
- Laravel asset build from root package scripts
- Queue worker and scheduler deployment as needed

**Nuxt Storefront**
- `npm ci` inside `storefront/`
- Build with `npm run build`
- Run Nuxt Nitro as a Node service
- Configure private Laravel upstream and public API base
- Deploy without requiring a Laravel frontend build

A Storefront-only change should not force an unnecessary Laravel release. A Laravel-only backend change should not force a Nuxt rebuild unless the frontend contract or its build inputs changed.

### 13.3 Example production topology

The current Nginx example describes:
- Laravel upstream on internal port `8001`
- Nuxt Nitro upstream on internal port `3000`
- Public page requests proxied to Nuxt
- `/api/*`, `/backend/*`, `/build/*`, `/storage/*`, `/robots.txt` and `/sitemap.xml` proxied to Laravel

This is an example topology, not a verified live deployment. Hostnames, TLS, trusted proxy settings, cookie domain, CSRF, CORS and upstream ports must be reviewed for the actual hosting environment.

Recommended logical hostnames (illustrative only):

```text
www.example.com       → Nuxt Storefront
admin.example.com     → Laravel ERP
api.example.com       → Laravel API (if a separate API host is adopted)
```

A same-origin reverse-proxy arrangement can simplify browser session and CSRF behavior for the current Storefront. If API and frontend are placed on different subdomains, explicitly configure and test cookie domain, SameSite, Secure, CORS, CSRF and trusted proxy behavior.

### 13.4 Deployment independence is not repository separation

Keeping Laravel and Nuxt in one Git repository does not prevent independent deployment. CI/CD should detect changes and run the relevant application checks/builds. Use separate release jobs or pipelines while retaining one source repository.

---

## 14. Future Applications and Scaling Strategy

Future clients should consume Laravel APIs rather than duplicate backend business logic.

```text
                   CENTRAL LARAVEL PLATFORM
                Identity / Commerce / CMS / ERP
                              |
                         Versioned APIs
                              |
          +-------------------+--------------------+
          |                   |                    |
    Nuxt Storefront      Customer Mobile       Partner Portal
       Existing             Future                Future
          |
    Additional Websites / Channels
```

Possible future applications:
- Customer mobile app (Flutter/React Native/native)
- B2B customer portal
- Vendor/partner portal
- Additional branded websites
- Internal operational apps

Each client may have its own UI, release cycle, domain and frontend technology while consuming central backend capabilities.

### 14.1 Multi-store/multi-channel readiness

When multiple public websites or apps are actually planned, design explicit concepts for:
- Store/channel identity
- Domain-to-store mapping
- Brand/theme configuration
- Channel-specific CMS content
- Catalog availability and visibility
- Pricing rules and customer groups
- SEO metadata per channel
- Analytics attribution by channel
- Permission and content publishing scopes

Do not implement a broad multi-tenant system prematurely. First document concrete business requirements and data ownership. Avoid assuming that every future app needs a separate database.

### 14.2 When to split repositories

Remain in the monorepo while:
- One team or closely coordinated contributors own the applications
- Backend and frontend changes are often related
- Shared API contract changes need coordinated review
- Centralized issue tracking and CI are useful

Consider separate repositories when:
- Independent teams need separate access controls
- Release and version lifecycles are genuinely independent
- Repository CI/build costs become material
- Different products need independent governance or ownership
- Shared API contracts have a clear versioning/release process

Do not split repositories solely because the repository contains multiple applications or because local dependency folders make a ZIP large.

### 14.3 When to consider microservices

Do not introduce microservices as a default growth step. Reassess only when a domain has a strong need for independent scaling, deployment, fault isolation, ownership or technology choices, and the team can operate distributed infrastructure. Until then, keep a modular monolith with clear domain contracts.

---

## 15. Identity, Roles, Module Registry and Feature Flags

The platform uses backend-enforced permissions and a Developer role.

Module state and user authorization are separate dimensions. Feature flags control rollout, not access.

Example feature flags:

```text
marketing.meta.enabled
marketing.google.enabled
support.live_chat.enabled
analytics.attribution.enabled
new_dashboard.enabled
storefront.checkout.enabled
```

Every sensitive backend operation must still pass authorization and policy checks even when its UI is hidden or its module is visible.

---

## 16. Marketing Integrations and Analytics

Marketing integrations must be provider-agnostic.

```text
Marketing Domain
      |
Provider Contract
      |
+-----+------+------+
|            |      |
Meta        Google  TikTok
Adapter     Adapter Adapter
      |
Normalized campaign data
      |
Analytics / Attribution / Reports
```

Potential normalized metrics include spend, impressions, reach, clicks, CTR, CPC, CPM, conversions, conversion value, ROAS and cost per conversion. Final metrics must follow provider API availability, attribution windows and explicit definitions.

Provider-specific tokens and payloads remain in integration infrastructure. Normalize data before it reaches shared analytics and reporting.

---

## 17. Communication, Chat and Support

The backend dashboard uses one combined floating Chats/Support entry point.

Planned capabilities:
- Customer live chat
- Internal staff chat
- Conversation assignment
- Unread counts
- Presence/status
- Attachments
- Search and history
- Notifications
- Realtime message delivery

Conceptual flow:

```text
Customer / Staff
      ↓
Conversation API
      ↓
Conversation and Message domain
      ↓
Event / Queue
      ↓
Realtime broadcast
      ↓
Nuxt or ERP chat UI
```

Authorization must verify that the current user can access the specific conversation and attachments.

---

## 18. Backend Dashboard Design System

The user-provided backend dashboard reference is the visual source of truth. Preserve its premium enterprise direction rather than drifting into a generic admin template.

Design goals:
- Premium, modern, clean and data-rich without clutter
- Clear information hierarchy and consistent spacing
- Rounded cards and subtle depth
- Professional charts and tables
- Responsive navigation and layouts
- Light, Dark and System themes
- Strong keyboard, focus and screen-reader support

### 18.1 Main dashboard areas

- Overview and KPIs
- Sales analytics
- Order analytics and status
- Inventory status
- Top-selling products
- Recent orders
- Traffic sources
- Marketing performance
- Customer growth
- Quick actions and today's tasks
- Combined Chats/Support entry point

### 18.2 Theme system

```text
Light
Dark
System
```

Use semantic design tokens, not hardcoded component-specific colors:

```text
--background
--surface
--surface-elevated
--surface-muted
--text-primary
--text-secondary
--text-muted
--text-inverse
--border
--border-strong
--primary
--primary-hover
--success
--warning
--danger
--info
```

### 18.3 Typography

Preferred primary typeface: Inter.  
Fallback: `system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif`.

Suggested desktop scale:

```text
Display XL     36px / 44px / 700
Display        32px / 40px / 700
H1             28px / 36px / 700
H2             24px / 32px / 600
H3             20px / 28px / 600
H4             18px / 24px / 600
Body Large     16px / 24px / 400
Body           14px / 20px / 400
Body Medium    14px / 20px / 500
Small          13px / 18px / 400
Caption         12px / 16px / 400
```

Use tabular numerals for financial and analytical values where supported.

### 18.4 Spacing and radius

Use a 4px-based spacing scale:

```text
4, 8, 12, 16, 20, 24, 32, 40, 48, 64px
```

Suggested card padding: 20–24px.  
Section gap: 24–32px.  
Grid gap: 16–24px.  
Input/button height: 40–44px.

Radius scale:

```text
XS 6px | SM 8px | MD 10px | LG 12px
XL 16px | 2XL 20px | Pill 999px
```

### 18.5 Motion and accessibility

- Fast feedback: 120–160ms
- Standard: 160–220ms
- Complex transitions: 220–300ms
- Respect `prefers-reduced-motion`
- Target WCAG 2.2 AA
- Provide keyboard navigation, visible focus, semantic HTML, accessible labels, contrast, accessible dialogs/tables/charts

### 18.6 Responsive strategy

```text
Mobile:       < 640px
Tablet:       640–1023px
Desktop:      1024–1439px
Large:        1440px+
```

The desktop dashboard is the primary operational workspace, but critical workflows must remain usable on smaller screens.

### 18.7 Component and state architecture

Staff ERP components are currently organized under `resources/js/` with components, composables, layouts, pages, config and icons. Keep components responsibility-focused.

Preferred flow:

```text
UI Component
    ↓
Composable / Store (when shared state is justified)
    ↓
API Client
    ↓
Laravel API
```

Centralize only genuinely shared state such as auth, user, permissions, theme, notifications, chat and dashboard filters. Do not put every API response into global state.

---

## 19. Testing and Quality Strategy

Testing layers:
- Unit tests
- Laravel feature tests
- API tests
- Domain/business-rule tests
- Authorization tests
- Integration tests
- Browser/E2E tests for critical user journeys
- Concurrency tests for race-sensitive operations

The current project includes `tests/Unit`, `tests/Feature` and a separate `tests/Concurrency` suite. Preserve the special PostgreSQL configuration and requirements for concurrency tests.

Critical scenarios:
- Cross-user and cross-customer authorization
- Role/permission boundaries and Developer-only actions
- Financial calculations
- Order state transitions
- Inventory reservations/transfers and concurrent stock changes
- Checkout and payment state
- CMS publishing and unsafe HTML handling
- Marketing synchronization and provider failures
- Customer session and CSRF behavior
- Storefront API integration and error/empty states

Do not mark a feature complete based only on the presence of a route or page file. Verify its complete behavior and authorization.

---

## 20. Observability and Operations

Production readiness should include:
- Structured logs
- Request/correlation IDs
- API latency and error monitoring
- Queue and failed-job monitoring
- Integration failure tracking
- Security event monitoring
- Health checks
- Database backup and restore testing
- Storage backup/retention
- Deployment rollback procedure
- Load/performance testing for critical flows

Operational and security events should capture actor, action, resource, timestamp, outcome and request context where appropriate. Avoid logging secrets or unnecessary personal data.

---

## 21. Git, Repository Hygiene and CI/CD

### 21.1 Never commit

```text
.env
.env.production
credentials and private keys
vendor/
node_modules/
storefront/node_modules/
storefront/.nuxt/
storefront/.output/
generated local data and temporary backups
```

Keep:
- `composer.lock`
- Root `package-lock.json`
- `storefront/package-lock.json`
- `.env.example` files containing placeholders only
- Source code, tests, migrations and documentation

The repository `.gitignore` should be reviewed whenever a new generated directory or local secret file is introduced.

### 21.2 Commit convention

```text
feat: add role permission management
fix: prevent unauthorized order access
refactor: extract campaign sync action
test: cover inventory reservation concurrency
docs: update storefront API contract
chore: update dependencies
```

### 21.3 CI/CD

Use application-aware checks:
- Laravel changes: Composer validation, Pint, static analysis and Laravel tests
- Root ERP UI changes: type/lint checks and Vite build
- Storefront changes: Nuxt typecheck and Nuxt production build
- API contract changes: relevant Laravel API tests and Storefront integration/E2E checks

The repository currently contains a GitHub Actions workflow that sets up PHP and Node and runs the root Composer CI script. Review that workflow as the Storefront grows so Nuxt typecheck/build are also run when appropriate.

Do not assume one root CI command automatically validates every application.

---

## 22. Current Repository Audit — Snapshot of Supplied Project

This section records what was observed in the supplied project archive. It is not a claim that all roadmap capabilities are already complete.

### 22.1 Confirmed structure and configuration

- Laravel application is at repository root.
- Nuxt customer website is in `storefront/`.
- Laravel staff UI uses Inertia/Vue under `resources/`.
- Separate root and Storefront Node package manifests/lockfiles exist.
- Laravel Composer manifest and lockfile exist.
- Storefront has its own Nuxt config, README and environment example.
- Laravel route configuration contains versioned Storefront, backend, head-office and webhook API groups.
- Storefront API uses Laravel `web` middleware for session/CSRF-dependent endpoints.
- Storefront deployment Nginx example routes public pages to Nuxt and selected Laravel paths to Laravel.
- GitHub Actions currently runs a root Laravel-oriented CI command.

### 22.2 Differences between the original architecture document and current implementation

1. **Domain folders:** The original README describes `app/Domains/*` as a conceptual target. The supplied code currently uses conventional `app/Http`, `app/Models`, `app/Jobs`, `app/Policies`, `app/Services` and related folders. Treat domain extraction as incremental future refactoring, not an existing completed structure.
2. **Nuxt proxy target:** `storefront/.env.example` and `storefront/README.md` document `NUXT_LARAVEL_PROXY_TARGET`, but `storefront/nuxt.config.ts` currently hardcodes the Laravel upstream URL. Synchronize implementation and documentation.
3. **Storefront route readiness:** Nuxt page files exist for login, registration and account routes. Their existence does not prove full workflows are implemented. Replace blanket “not implemented” statements with route-by-route verification.
4. **Storefront README freshness:** Its feature/readiness statements should be reviewed against the current page and API implementation before being treated as authoritative.
5. **PHP baseline:** Composer currently declares PHP `^8.3`; the lockfile resolves Laravel `v13.34.0`. Record the exact PHP runtime from the target environment separately.
6. **Database/Redis baseline:** Configuration and tests indicate PostgreSQL is intended and the test suite targets PostgreSQL. Redis is described as planned/core infrastructure; verify actual environment and production services before calling it deployed.
7. **Workspace configuration:** A root `pnpm-workspace.yaml` currently lists only `.`. The project also uses npm lockfiles and a separate Storefront package manifest. Decide whether this pnpm workspace file is actively required; do not assume it manages the Nuxt app.
8. **Generated files and archive size:** `vendor`, `node_modules`, Nuxt cache/output and local runtime data make project archives heavy. They are not source architecture and should be excluded from normal source handoff archives. Do not delete required source lockfiles.
9. **Secrets in archives:** The supplied archive includes a root `.env` and Git metadata. Exclude secrets from future handoff archives; verify repository tracking and rotate any credentials that may have been exposed.

### 22.3 Immediate architecture cleanup backlog

Priority order:

1. Synchronize Nuxt proxy configuration with its environment example and documentation.
2. Verify `.gitignore` and ensure generated dependency/build folders and secrets are not tracked.
3. Create a safe project archive/export procedure that excludes generated folders, `.env`, local backups and secrets while retaining lockfiles and source.
4. Bring root README and Storefront README into agreement about actual routes, features, runtime and deployment.
5. Add Storefront typecheck/build and relevant integration checks to CI.
6. Document API contracts and authentication/CSRF requirements.
7. Add route-by-route E2E coverage for Storefront journeys.
8. Extract domain classes incrementally when meaningful; do not undertake a large namespace/folder migration now.

---

## 23. Implementation Roadmap

### Phase 0 — Architecture and Repository Hygiene
- Keep the current monorepo and Laravel-at-root layout.
- Confirm environment configuration and secret handling.
- Review generated files and archive exclusions.
- Synchronize root and Storefront documentation.
- Fix proxy target configuration.
- Establish app-specific local run/build/test commands.

### Phase 1 — Identity and Security
- Staff and customer identity separation
- Developer role, roles and permissions
- Policies and access audit
- Session, CSRF and API security
- 2FA and account recovery where required

### Phase 2 — System and Developer Controls
- Module registry and module states
- Feature flags
- Menu registry
- System settings
- Security and audit events

### Phase 3 — Catalog and CMS
- Products, variants, categories, brands and media
- CMS pages, banners, sections and revisions
- SEO metadata and publication workflow
- Public API contracts and Storefront rendering

### Phase 4 — Customers and CRM
- Customer profiles, groups and addresses
- Leads, activities, assignment and conversion
- Customer segmentation

### Phase 5 — Sales, Orders and Inventory
- Orders and lifecycle
- Pricing, promotions and discounts
- Inventory, reservations, transfers and adjustments
- Returns and refunds

### Phase 6 — Payments, Shipping and Procurement
- Payment provider contracts/adapters
- Courier/shipping provider contracts/adapters
- Tracking and delivery lifecycle
- Procurement and goods receipt

### Phase 7 — Storefront Completion
- Product and category journeys
- Cart and checkout
- Customer authentication and account
- Order tracking and order history
- CMS pages and SEO
- Accessibility, responsive UX and E2E verification

### Phase 8 — Marketing and Analytics
- Meta, Google, TikTok provider adapters
- Campaign synchronization
- Normalized metrics
- Attribution and business reporting

### Phase 9 — Communication
- Customer support/live chat
- Internal chat
- Notifications and realtime delivery

### Phase 10 — Production Hardening
- Security and dependency audit
- Performance and load tests
- Observability
- Queue/Redis operations
- Backups and disaster recovery
- Independent application deployment
- Rollback and release verification

### Phase 11 — Future Channels
- Customer mobile application
- B2B/vendor/partner portals
- Multi-store/channel CMS and catalog capabilities
- Reassess monorepo/polyrepo only when operational needs justify it

---

## 24. Definition of Done for Major Features

A major feature is not complete until:

1. Domain ownership and business rules are clear.
2. Database changes are migrated and constrained appropriately.
3. API contract and validation are documented.
4. Backend authorization is implemented and tested.
5. Failure, empty and boundary cases are handled.
6. Audit/event/queue behavior is included where required.
7. Frontend behavior is responsive and accessible.
8. Integration/E2E tests cover the critical user journey.
9. Logs do not expose secrets or sensitive information.
10. Documentation and deployment requirements are updated.

---

## 25. Non-Negotiable Architecture Rules

1. Laravel is the source of truth for business data and rules.
2. Backend authorization is mandatory; UI visibility is not security.
3. Developer is the highest product role.
4. Secrets never enter Git.
5. Storefront and ERP remain separate frontend applications.
6. Storefront consumes Laravel APIs; it does not access the database directly.
7. External integrations use contracts/adapters.
8. Business rules do not live in controllers or frontend components.
9. Critical workflows are tested.
10. Financial, order and inventory operations are transactional and auditable.
11. Analytics must not corrupt operational data.
12. Background work uses queues when appropriate.
13. Realtime features use explicit event/broadcasting infrastructure.
14. New modules should be independently maintainable.
15. Avoid premature abstractions and unnecessary repositories.
16. Preserve API compatibility where possible.
17. Accessibility is part of the definition of done.
18. Light, Dark and System themes remain first-class modes.
19. The locked dashboard visual direction remains the UI source of truth.
20. Keep the monorepo until there is a concrete operational reason to split it.
21. Do not introduce microservices merely because more frontend clients are added.
22. Do not move Laravel into a new directory unless a specific migration benefit justifies the risk.

---

## 26. Architecture Documentation Rule

This README is the living architecture reference.

When architecture changes:

1. Update the relevant architecture decision and diagram.
2. Explain why the change was made.
3. Update affected API, data ownership and deployment guidance.
4. Update environment examples and application READMEs.
5. Add or adjust tests.
6. Record migration and rollback considerations.

Keep verified current implementation separate from target architecture and roadmap items. Never document a planned capability as already implemented.

---

## Final Principle

**One central Laravel platform. Multiple independent frontend applications. One authoritative business data layer. Stable versioned APIs. Independent builds and deployments.**

Architecture first. Contracts second. Implementation and UI on top of stable boundaries.
