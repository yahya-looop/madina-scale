# MADINA Scale — Laravel + Storefront Integration Audit

Audit basis: uploaded `madina-scale.zip` (latest user-provided project snapshot). This report describes code inspected in that archive; it does not claim live provider credentials or production connectivity.

## Executive summary

The project already has a substantial Laravel ERP foundation and a Nuxt 4 storefront. The storefront is configured to use the Laravel session-backed API through Nuxt route proxies. The correct strategy is to extend existing modules and contracts rather than replace the architecture.

## Existing architecture

- Laravel backend with Inertia/Vue ERP and versioned API namespaces.
- Nuxt 4 storefront under `storefront/`.
- Nuxt runtime public API base defaults to `/api/v1/storefront`; `/api/**`, `/backend/**`, `/storage/**`, `/robots.txt` and `/sitemap.xml` are proxied to Laravel on port 8001.
- Storefront uses Laravel web sessions and CSRF for cart, customer authentication and checkout.
- Catalog, cart, customer auth, CMS pages/banners, public order tracking and support endpoints already exist.

## Confirmed capabilities

### Commerce and operations
- Product catalogue and variant models, inventory, stock movements, reservations and transfer workflows exist.
- Pricing engine and promotion/flash-sale functionality exist.
- Sales order service supports idempotency, order pricing snapshots and inventory reservation workflows.
- Storefront checkout creates Laravel sales orders and customer records.
- Payment fields exist on sales orders (`payment_method`, `payment_status`, `payment_details`).

### Delivery
- Shipment model, shipment events, manual status transition controls and shipment history exist.
- Courier booking service has named branches for Steadfast, REDX and Pathao.
- CarryBee currently routes through a generic/custom adapter branch; this is not yet a verified dedicated CarryBee contract.
- No Bangladesh Post Office adapter was found in the inspected courier service.
- Current REDX and Pathao booking payloads use hardcoded parcel weights (500 grams / 0.5 kg).
- Storefront checkout sends demo courier preference and demo quote fields, but Laravel checkout validation/order creation currently ignores those fields. Customer preference and final assignment are therefore not yet persisted.

### Payment
- Storefront checkout currently supports cash on delivery and bank transfer only.
- Bank transfer verification workflows exist in the ERP.
- No SSLCommerz payment-session, callback/IPN validation or refund integration was found in the inspected code.
- SSLCommerz must be implemented server-side with verified transaction validation and idempotent payment ledger posting.

### CMS and SEO
- CMS pages include title, slug, excerpt, content, JSON sections, featured image, publishing status/time, meta title/description/keywords, canonical URL and robots index/follow.
- CMS media, banners, content sections and page revision tables/controllers exist.
- Public CMS page API and public banner API exist.
- `robots.txt` and XML sitemap routes exist; sitemap includes eligible CMS pages and active visible products.
- Additional global SEO settings, product/variant SEO controls, redirects, canonical policy, JSON-LD/schema and storefront-wide metadata synchronization should be audited and completed.

### SMS
- Generic configurable SMS sender and order SMS logs exist.
- Current implementation uses a generic endpoint/header/body configuration; provider-specific adapter and DLR webhook workflows are not established in the inspected service.

## Critical integration gaps

1. Checkout must validate and persist customer courier preference, selection mode, quote reference and final assigned provider separately.
2. Server-side delivery quote must be authoritative. Storefront-provided demo charges must never be trusted for order totals.
3. Product default shipping weight and optional variant override are not present in the inspected product/variant schema; effective weight and immutable order-item weight snapshots are needed.
4. Courier adapters need normalized capabilities: serviceability, quote, create shipment, cancel, track, webhook and COD support. Unsupported capabilities must be explicit rather than simulated.
5. Admin override must be allowed before booking; after booking it must use cancel/rebook with reason, permissions and audit trail.
6. SSLCommerz needs a complete server-side payment lifecycle and ledger-safe callback handling.
7. CMS/SEO needs a defined content contract so storefront pages use backend-managed content without breaking designed static layouts.
8. SMS provider integration needs encrypted credentials, templates, queueing, delivery reports and provider health monitoring.

## Recommended implementation sequence

### Phase 0 — Contract and integrity foundation
- Freeze API response/request contracts.
- Add integration settings and provider capability registry.
- Add tests for checkout idempotency, session/CSRF, order pricing and API error shapes.
- Keep all secrets out of Nuxt public runtime config.

### Phase 1 — Commerce and delivery data integrity
- Product shipping weight + variant override.
- Immutable order item weight and delivery pricing snapshots.
- Server-side quote endpoint and zone/rate rules.
- Persist customer preference, system recommendation and assigned courier independently.
- Preserve existing inventory and pricing engine behavior.

### Phase 2 — Courier orchestration
- Dedicated adapters for Steadfast, CarryBee, Bangladesh Post (subject to official API/contract), Pathao and REDX.
- Provider connection test, serviceability, quotes, booking, tracking and webhook support according to each provider's actual API.
- Shipment state machine, idempotency, duplicate webhook protection, retry/reconciliation and admin override audit.

### Phase 3 — SSLCommerz
- Sandbox initiate, redirect, success/fail/cancel, IPN, transaction validation, reconciliation, refund and payment ledger posting.
- Never mark paid based only on browser redirect.

### Phase 4 — CMS and SEO synchronization
- Global SEO settings, per-page/product metadata, canonical/robots controls, redirects, sitemap and JSON-LD.
- Storefront-managed navigation, banners, home sections, policies and content blocks with preview/publish workflow.
- Retain revision history and safe fallback content.

### Phase 5 — SMS and notifications
- Provider adapter interface; shortlist SSL Wireless ISMS Plus, Alpha Net and REVE SMS.
- OTP/transactional templates, queueing, retry policy, delivery receipts and campaign consent controls.

### Phase 6 — QA and production readiness
- Feature tests, API contract tests, Nuxt build/typecheck, database migration rehearsal, sandbox payment/courier tests, security review, operational runbook and deployment checklist.

## External material required before live integrations

- SSLCommerz merchant Store ID/Store Password for sandbox and production, official integration guide, IPN requirements and merchant callback allow-list details.
- Steadfast, CarryBee, Pathao and REDX merchant API docs/credentials and test-account access.
- Bangladesh Post Office e-commerce/parcel integration agreement, API or approved operational interface. Do not assume a public merchant API exists.
- SMS provider quotation, API documentation, approved sender ID/masking status, IP allow-listing requirements and DLR webhook documentation.

## SMS vendor recommendation

Shortlist SSL Wireless ISMS Plus first (particularly because the business already has a relationship with SSL Wireless, subject to a separate SMS agreement), then compare Alpha Net and REVE SMS. Evaluate BTRC/A2P compliance, branded sender ID approval, Bangla Unicode, OTP latency, operator coverage, delivery receipts, API reliability, SLA, support and total cost. Publicly advertised prices/features are indicative and must be confirmed in a written quotation.

## Security and implementation constraints

- Never expose payment, courier or SMS secrets in Nuxt public config or browser requests.
- Encrypt provider credentials at rest; mask them in admin responses and audit logs.
- Validate all prices, weights, serviceability and final charges in Laravel.
- Use idempotency keys and unique provider references for payment and shipment creation.
- Verify webhook signatures/tokens and deduplicate event IDs/hashes.
- Use queues for external API calls where appropriate; reconcile ambiguous booking/payment outcomes before retrying.
- Do not report provider integration as live until sandbox/production calls are verified with authorized credentials.
