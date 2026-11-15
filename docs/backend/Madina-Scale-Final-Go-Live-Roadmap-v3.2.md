# Madina Scale --- Final Go-Live & Post-Launch Roadmap

**Version:** 3.0 --- Go-Live First\
**Prepared:** 15 November 2026\
**Basis:** Madina Scale Final Master Development Roadmap v2.0 and latest
supplied `madina-scale.zip`\
**Product objective:** Launch a reliable, revenue-generating e-commerce
website connected to the ERP, payments, delivery partners, finance, CRM,
and marketing analytics.

------------------------------------------------------------------------

## 1. Executive decision

The project should no longer follow the old roadmap as a sequence of
broad ERP phases. The immediate objective is to launch the minimum
complete commerce operation, not to finish every advanced ERP capability
before launch.

The new execution model is:

1.  Complete the customer purchase journey.
2.  Make every order, payment, shipment, customer and accounting
    movement traceable in the ERP.
3.  Connect delivery partners and reconcile delivery/COD settlements.
4.  Make Google and Meta marketing measurement reliable.
5.  Complete production safety checks and launch.
6.  Move non-critical advanced ERP, communication, automation and AI
    work to post-launch.

**Important:** "Minimum" means the smallest complete and safe business
workflow. It does not mean bypassing payment verification, accounting
controls, security, reconciliation, or recovery.

## 2. Latest source snapshot --- observed implementation

The supplied ZIP contains approximately:

-   87 database migration files
-   66 Vue page files
-   51 HTTP controller files
-   32 service files
-   51 Feature test files

The source contains substantial existing work. Do not rebuild these
modules from scratch.

### 2.1 Existing implementation evidence

  ---------------------------------------------------------------------------------------
  Area                    Evidence in source             Assessment
  ----------------------- ------------------------------ --------------------------------
  Product/catalog         Products, variants,            Strong foundation; verify
                          categories, brands, units,     publish-to-purchase workflow
                          attributes, product media,     
                          public product routes          

  Inventory               Stock, immutable movements,    Strong foundation; verify
                          reservations, transfers,       complete storefront and courier
                          valuation ledger,              lifecycle
                          reconciliation commands        

  Orders                  Sales order service, status    Strong foundation; customer
                          history, fulfillment, risk     self-service and end-to-end
                          assessment, shipment relation  payment/delivery journey remain

  Customers               Customer model, identity       Foundation exists; guest
                          service, customer management   identity/account/order-history
                          and order linkage              journey needs completion

  CRM/leads               Lead management, assignment,   Foundation exists; verify
                          activities, conversion         consistent customer/order/source
                          workflow                       synchronization

  Procurement             Suppliers, requisitions,       Broad implementation; end-to-end
                          purchase orders, GRNs,         purchase-to-pay acceptance still
                          invoices, returns, payments    required
                          and revisions                  

  Finance                 Financial accounts,            Partial accounting platform;
                          transactions, journal entries, full reconciliation and
                          controlled accounting, payment financial close still need
                          allocations                    validation

  Storefront              Home, product listing/detail,  Partial commerce journey
                          cart, checkout POST and        
                          order-success pages            

  Payments                Internal customer receipt and  External online payment gateway
                          allocation workflows           and verified customer checkout
                                                         payment not evidenced

  Delivery                Shipment model/controller,     Adapter foundation exists;
                          courier service,               provider-by-provider lifecycle
                          Steadfast/RedX/Pathao/custom   and settlement must be verified
                          provider branches              

  Marketing               Campaigns, coupons,            Foundation exists; Google/Meta
                          impression/click tracking      pixels and server-side
                                                         conversion delivery not
                                                         evidenced

  Analytics               Internal business analytics    Internal reporting exists; ad
                          and reports                    attribution and event-to-order
                                                         reconciliation remain

  Production              CI, build, tests and           Hosted CI, production
                          operational files exist        backup/restore, monitoring and
                                                         UAT require explicit evidence
  ---------------------------------------------------------------------------------------

### 2.2 Important source-level findings

-   Public `/products`, `/cart` and `/checkout` routes exist.
-   The current checkout is described in source as phone-first order
    placement; orders remain pending until staff confirmation.
-   Backend, storefront and webhook API namespaces have a foundation.
-   Courier integration branches exist for Steadfast, RedX, Pathao and
    custom/Courier providers.
-   Financial ledger, journals, customer receipts, payment allocation
    and controlled accounting are present.
-   Campaign/coupon management and client-originated impression/click
    tracking exist.
-   Client-originated tracking is explicitly prevented from
    manufacturing revenue/conversions.
-   The inspected source did not evidence a working external payment
    gateway integration, customer self-service account journey, Google
    Analytics/Google Ads purchase measurement, Meta Pixel, Meta
    Conversions API, or end-to-end COD settlement reconciliation.

These are source-inspection findings, not a claim that every route or
service has passed a live end-to-end test.

## 3. Completion estimate --- launch scope, not total ERP scope

Percentages below are planning estimates based on implementation
footprint visible in the supplied ZIP. They are not certified acceptance
percentages. A feature counts as complete only after its end-to-end
workflow, permissions, failure handling, reconciliation and tests pass
on the current local snapshot.

  -------------------------------------------------------------------------------------------
  Workstream                Estimated implementation Remaining launch-critical work
                                            progress 
  --------------------- ---------------------------- ----------------------------------------
  ERP foundation,                            80--90% Release-specific security/UAT and
  roles, locations,                                  operational checks
  audit                                              

  Product catalog and                        75--85% Public merchandising,
  inventory                                          availability/pricing edge cases, full
                                                     checkout integration

  Orders and                                 60--70% Customer checkout, payment states,
  fulfillment                                        exceptions, end-to-end lifecycle

  Customer and CRM                           50--65% Canonical identity, guest/account
                                                     journey, lead/customer/order/source
                                                     synchronization

  Procurement                                65--80% Final purchase-to-pay and AP/GL
                                                     reconciliation

  Finance and                                45--60% Advance receipts, gateway/courier
  accounting                                         clearing, refunds, bank reconciliation,
                                                     statements and close

  Storefront and                             35--45% Complete
  customer commerce                                  account/address/checkout/payment/order
                                                     tracking experience

  Online payment                              5--15% Provider integration, callbacks, partial
  integration                                        advance, refunds and reconciliation

  Courier/delivery                           40--55% Provider certification, tracking sync,
                                                     exceptions, COD settlement and fraud
                                                     controls

  Marketing and ad                           10--20% GA4, GTM, Meta Pixel, CAPI, Google Ads,
  measurement                                        attribution and deduplication

  Production readiness                       30--45% Hosted CI, backup/restore drill,
                                                     monitoring, security review, UAT and
                                                     rollback rehearsal
  -------------------------------------------------------------------------------------------

**Overall launch readiness estimate: approximately 35--45%.** This is a
weighted planning estimate for the complete
customer-to-cash-to-delivery-to-attribution journey, not a measurement
of how much code exists. The ERP foundation is materially further along
than the public commerce and growth stack.

## 4. New execution strategy

### Programme A --- Go-Live Critical Path

Only the work required to accept real customer orders, collect money,
deliver goods, record every financial movement and measure marketing
outcomes belongs in this programme.

### Programme B --- Post-Launch Development

Advanced ERP capabilities that do not block safe order-to-cash
operations are deferred. They remain on the roadmap, but they must not
delay launch unless a real business dependency is discovered.

------------------------------------------------------------------------

# PROGRAMME A --- GO-LIVE CRITICAL PATH

## Phase G0 --- Snapshot freeze, audit and launch definition

**Priority:** P0\
**Purpose:** Establish one verified release baseline before modifying
more modules.

### Required work

-   Freeze and identify the exact source snapshot and current database
    migration state.
-   Run the current full test suite, PHPStan, Pint, TypeScript and
    production build.
-   Record route inventory, current payment methods, courier provider
    configuration and feature flags.
-   Map the real order lifecycle from product page through payment,
    fulfillment, shipment, delivery, return/refund and ledger posting.
-   Identify which payment and courier providers the business has active
    merchant contracts and credentials for.
-   Confirm launch policies: COD, full online payment, advance
    percentage/fixed amount, cancellation, delivery charge,
    return/refund and tax/VAT.
-   Create a staging environment with production-like configuration but
    non-production credentials/data.

### Acceptance gate

-   Current snapshot has a reproducible green verification baseline.
-   No destructive database reset is used.
-   All required provider choices and business policies are confirmed.
-   Every remaining task has an owner, dependency and testable
    acceptance criterion.

## Phase G1 --- Complete the customer storefront and checkout

**Priority:** P0 --- launch blocker

### Required customer journey

1.  Customer can browse homepage, categories, product listing and
    product detail.
2.  Product pages show accurate variants, price, sale price,
    stock/availability, images and delivery information.
3.  Search, filters, sorting, pagination and mobile layout work.
4.  Customer can add/update/remove cart items; cart is revalidated
    against current price, status and stock at checkout.
5.  Customer can checkout as a guest using the minimum required identity
    and contact fields.
6.  Customer can manage delivery address and select a supported delivery
    area/method.
7.  Delivery charge and order total are calculated server-side.
8.  Coupon/promotion eligibility and limits are revalidated server-side.
9.  Customer can choose COD, full online payment or an explicitly
    configured advance-payment option.
10. Order confirmation page and customer notifications show a reliable
    order reference and next steps.
11. Customer can retrieve order status and tracking through a secure
    lookup or customer account.

### Backend requirements

-   Never trust client-supplied prices, discounts, shipping fees, tax,
    payable amount or stock.
-   Recalculate all totals on the server using decimal-safe arithmetic.
-   Lock/revalidate stock and create reservations safely during
    checkout.
-   Make checkout idempotent to prevent duplicate orders on
    refresh/retry.
-   Store immutable order-item, address, price, discount, tax and
    delivery snapshots.
-   Define expiry/release behavior for unpaid orders and abandoned
    reservations.
-   Prevent invalid state transitions and duplicate fulfillment.
-   Add rate limits and abuse controls to checkout, OTP and order
    lookup.
-   Preserve location/warehouse ownership and audit history.

### Acceptance gate

A real test customer can complete the full journey on mobile and
desktop; duplicate submissions do not create duplicate orders;
price/stock tampering is rejected; order, customer, inventory and audit
records remain consistent.

## Phase G2 --- Online payments, advance payments and refunds

**Priority:** P0 --- launch blocker

### Provider strategy

Build a provider-independent payment adapter and integrate the first
contracted gateway selected by the business. Add further providers
through the same contract instead of embedding provider logic in
checkout controllers.

Potential Bangladesh payment options to evaluate with the business
include SSLCommerz, bKash, Nagad, cards through a contracted acquirer,
or another licensed provider. Selection must be based on active merchant
approval, settlement terms, supported payment methods, refund capability
and official integration documentation. Do not assume credentials or
merchant approval exist.

### Required payment capabilities

-   COD payment method.
-   Full online payment.
-   Partial/advance payment using a configured fixed amount or
    percentage.
-   Remaining balance due calculation and collection.
-   Payment attempt/session creation and expiry.
-   Server-side verification of provider transaction status.
-   Signed/verified webhook callbacks, replay protection and
    idempotency.
-   Pending, authorized, paid, failed, cancelled, expired, partially
    paid, refunded and partially refunded states as appropriate.
-   Duplicate callback and browser-return protection.
-   Refund initiation, provider reference, refund status and
    reconciliation.
-   Gateway fees, settlement references and net settlement recording.
-   Safe manual payment recording with permissions, evidence/reference
    and audit trail.

### Accounting rules

-   An order is not paid merely because the browser returns to a success
    URL.
-   A verified provider event or trusted server-side status check is
    required before marking a payment successful.
-   Customer advances must be recorded as a liability/customer advance
    until the applicable revenue-recognition event; do not double-count
    them as revenue.
-   Payment allocation to an order/invoice must be explicit and
    idempotent.
-   Refunds must reverse the appropriate payment/receivable/revenue/tax
    effects through controlled entries.
-   Gateway clearing and bank settlement must reconcile gross
    collection, fees, refunds and net deposits.

### Acceptance gate

Test successful, failed, cancelled, delayed, duplicate, replayed and
out-of-order callbacks; partial advance plus remaining balance; refund;
and settlement reconciliation. No double charge, double receipt or false
paid status is permitted.

## Phase G3 --- Delivery partner integration and delivery operations

**Priority:** P0 --- launch blocker

### Provider architecture

-   Keep one canonical shipment lifecycle in Madina Scale.
-   Implement provider adapters for each contracted courier.
-   Reuse the existing courier service where safe; do not rewrite
    working provider code without evidence.
-   Store provider credentials encrypted/server-side and expose only
    masked status to staff.
-   Maintain provider capability/configuration per location and delivery
    service.

### Required operations

-   Delivery area/serviceability check.
-   Delivery charge/weight/COD amount calculation.
-   Shipment/consignment booking directly from an eligible order.
-   Label/consignment ID and tracking number storage.
-   Cancel/rebook rules and duplicate booking protection.
-   Tracking status synchronization by webhook and scheduled polling
    fallback where supported.
-   Verified webhook signatures/challenges, replay protection and
    idempotent event storage.
-   Status mapping into canonical states: pending, booked, picked up, in
    transit, delivered, delivery failed, returned to origin, cancelled,
    exception.
-   Exception queue for address issue, recipient unavailable, damaged
    parcel, delayed parcel and provider failure.
-   Retry with safe attempt history; never blindly create a second
    consignment.
-   Customer-facing tracking/status updates.
-   Courier invoice/charge, COD collection and settlement records.
-   Courier COD settlement reconciliation against order-level expected
    collection, deductions, fees, returns and actual bank/cash receipt.
-   Manual fallback booking with reason, actor, reference and audit
    trail.

### Fraud and delivery-risk controls

-   Duplicate order/phone/address velocity checks.
-   Phone verification or confirmation for risk-based cases.
-   Repeat failed-delivery/RTO indicators.
-   High-value COD and suspicious order review queue.
-   Address completeness and delivery-area validation.
-   Configurable allowlist/blocklist with audit and expiry.
-   Manual review before blocking legitimate customers solely on a risk
    score.
-   Risk reason, score, reviewer and final decision recorded.

### Acceptance gate

At least one contracted courier completes booking → tracking →
delivered/failed/RTO → COD settlement end-to-end in staging. Other
contracted providers must pass provider-specific certification before
being enabled in production.

## Phase G4 --- Finance and accounting: minimum complete order-to-cash

**Priority:** P0 --- launch blocker

The objective is not to build every advanced finance feature before
launch. The objective is that no sale, receipt, advance, refund,
supplier cost, courier collection or bank settlement is financially
invisible.

### Required finance capabilities

-   Approved chart of accounts and account mappings.
-   Balanced journal posting, immutable posted journals and controlled
    reversal.
-   Sales/order subledger and customer receivable/advance balances.
-   Payment receipt and allocation to the correct order/invoice.
-   Partial payment and outstanding balance.
-   COD receivable/clearing account.
-   Gateway clearing account and fee expense.
-   Courier COD clearing and delivery expense accounts.
-   Refund and sales-return accounting.
-   Revenue recognition policy agreed by the business (for example,
    dispatch, delivery or invoicing); do not recognize revenue merely
    because a draft/pending order exists.
-   COGS and inventory asset posting tied to the actual stock
    dispatch/fulfillment event.
-   Return disposition-aware reversal of revenue, tax, receivable and
    COGS/inventory.
-   Supplier AP, supplier invoice, supplier credit/return and supplier
    payment reconciliation sufficient for existing purchasing
    operations.
-   Cash/bank accounts, deposit records and bank statement
    reconciliation.
-   Daily cash, gateway and courier settlement reconciliation.
-   Trial balance, Profit & Loss, Balance Sheet and cash movement report
    for the launch period.
-   Period lock/close and controlled correction workflow.
-   Audit trail for manual financial adjustments.

### Non-negotiable rules

-   Every financial event has one source reference and an idempotency
    boundary.
-   Customer advance is not recognized as revenue prematurely.
-   Gateway gross receipts, fees, refunds and net bank settlements
    reconcile.
-   Courier COD expected, collected, deducted and remitted amounts
    reconcile.
-   Supplier returns/credits reduce the correct AP balance and payment
    cap.
-   Posted journals are immutable; corrections use
    reversals/adjustments.
-   Finance reports reconcile to source order, payment, inventory and
    supplier records.

### Acceptance gate

A sample sale is traceable from order through payment/advance,
fulfillment, COGS, delivery/COD or gateway settlement, refund/return and
financial statements. Trial balance balances and subledgers reconcile.

## Phase G5 --- Customer, Lead and Order synchronization

**Priority:** P0 --- launch blocker

### Canonical identity

-   Normalize phone numbers into one canonical format.
-   Match guest checkout to an existing customer safely.
-   Prevent duplicate customer creation under concurrent checkout.
-   Keep customer ownership, privacy and location scope intact.
-   Allow customer account registration/verification without creating a
    second identity for the same person.

### Required synchronization

-   Lead capture records source, campaign, landing page and consent.
-   Lead conversion to customer is atomic and traceable.
-   Order checkout links to the canonical customer where identified.
-   Customer timeline shows leads, orders, payments, shipment updates,
    returns and support interactions.
-   Order status changes update the customer timeline.
-   Customer metrics derive from trusted order/payment states, not
    unverified client events.
-   Repeat purchases and customer segments use normalized identity.
-   Abandoned checkout may create a follow-up lead only under defined
    consent and deduplication rules.
-   Staff assignment, notes and follow-up history remain auditable.

### Acceptance gate

One person cannot accidentally become multiple customers through phone
formatting or concurrent requests. Lead conversion, guest checkout,
account registration and repeat order all resolve to the same customer
record when identity is verified.

## Phase G6 --- Google, Meta and marketing measurement

**Priority:** P0 --- launch blocker for paid-ad launch; basic analytics
must be active before public launch

### Tracking architecture

-   One governed browser event layer through Google Tag Manager.
-   GA4 configuration and ecommerce events.
-   Google Ads conversion tracking and enhanced conversions where
    consent and provider policy permit.
-   Meta Pixel browser events.
-   Meta Conversions API server events.
-   Shared event IDs for browser/server deduplication.
-   Transactional outbox or equivalent durable queue for server-side
    conversion delivery.
-   Provider response, retry, failure and delivery logs.
-   UTM, gclid, fbclid and campaign/source capture on first touch and
    last touch where policy permits.
-   Consent management and privacy notice appropriate to the business's
    operating markets.
-   PII minimization and approved hashing for eligible provider matching
    fields; never send raw payment credentials or unnecessary sensitive
    data.
-   Test/staging traffic separated from production reporting.

### Minimum event taxonomy

  -----------------------------------------------------------------------
  Funnel stage                        Event
  ----------------------------------- -----------------------------------
  Product discovery                   `view_item_list`, `view_item`

  Intent                              `search`, `select_item`,
                                      `add_to_cart`, `remove_from_cart`

  Checkout                            `view_cart`, `begin_checkout`,
                                      `add_shipping_info`,
                                      `add_payment_info`

  Order                               `purchase` with stable order/event
                                      ID and correct value/currency

  Post-purchase                       `refund` where supported and
                                      correctly reconciled
  -----------------------------------------------------------------------

Use platform-recommended event schemas and parameters. The backend is
the authority for order ID, currency, final value and verified payment
state. Browser events must not be trusted to create revenue.

### Conversion semantics

-   Define what `purchase` means for prepaid orders and COD orders.
-   Do not count checkout submission, pending order creation and
    verified payment as three purchases.
-   Use a stable event ID to deduplicate browser and server copies.
-   Track COD order placement separately from collected/settled cash
    where needed for business reporting.
-   Reconcile ad platform conversions to internal orders and finance
    records; document expected attribution differences.

### Acceptance gate

Test events in GA4 DebugView, Google Ads diagnostics and Meta Test
Events. Verify browser/server deduplication, currency/value/order ID,
consent behavior, retries and no duplicate purchase counting. Compare a
controlled set of test orders against ERP records.

## Phase G7 --- Launch hardening, UAT and production operations

**Priority:** P0 --- launch blocker

### Security and operational checks

-   HTTPS, secure cookies, CSRF, HSTS after HTTPS verification and
    security headers.
-   Production debug disabled; secrets outside source control and
    frontend bundles.
-   Provider credentials encrypted and rotated safely.
-   Staff/customer/API/webhook authorization and IDOR/BOLA tests.
-   Checkout/payment/courier/webhook rate limits.
-   Dependency and secret scanning.
-   Error monitoring, structured logs, correlation IDs and alerts.
-   Queue worker health, retries and dead-letter monitoring.
-   Scheduled task health and provider polling monitoring.
-   Database backups with retention and tested restore.
-   Migration rehearsal and rollback plan.
-   Staging-to-production configuration parity.
-   CDN/storage/media availability and upload limits.
-   SEO title/description, canonical URLs, sitemap, robots, structured
    data and social sharing metadata.
-   Mobile/tablet/desktop browser testing and accessibility basics.
-   Load and concurrency test for checkout, stock reservation and
    payment callbacks.
-   Staff training and operational SOP for orders, payments, delivery
    exceptions, refunds and daily reconciliation.

### UAT scenarios

1.  Guest COD order.
2.  Full online payment.
3.  Partial advance then balance payment.
4.  Payment failure, timeout and retry.
5.  Duplicate checkout submission.
6.  Out-of-stock and price-changed cart.
7.  Coupon expired/overused.
8.  Courier booking, webhook duplicate and tracking update.
9.  Delivery failed/RTO and rebooking.
10. COD settlement with courier deduction.
11. Partial fulfillment and cancellation.
12. Return and partial/full refund.
13. Customer duplicate identity prevention.
14. Lead conversion followed by order.
15. Google/Meta purchase event deduplication.
16. Staff permission/location denial.
17. Database backup restore and application recovery.

### Acceptance gate

Business owner signs off UAT; no unresolved critical/high security or
financial-integrity defect; restore drill succeeds; monitoring and
rollback are ready; all launch providers are verified in production.

------------------------------------------------------------------------

## 5. Launch scope --- what must be ready on day one

  -----------------------------------------------------------------------
  Capability                          Day-one requirement
  ----------------------------------- -----------------------------------
  Product catalog                     Public products, variants, price,
                                      availability, images and SEO basics

  Cart/checkout                       Guest checkout, customer contact,
                                      address, shipping charge,
                                      server-side totals

  Orders                              Unique order, clear lifecycle,
                                      stock reservation,
                                      cancellation/expiry

  Payment                             COD plus at least one contracted
                                      online gateway; advance payment if
                                      the business intends to collect
                                      advances at launch

  Customer                            Canonical customer identity, order
                                      history/lookup and notifications

  Delivery                            At least one contracted courier
                                      fully integrated; manual fallback;
                                      additional contracted partners
                                      certified before activation

  Finance                             Payment allocation, customer
                                      advance, refund, COGS/revenue
                                      policy, gateway/COD clearing and
                                      daily reconciliation

  CRM                                 Lead-to-customer conversion and
                                      customer/order timeline

  Marketing                           GA4 + GTM + Meta Pixel/CAPI +
                                      Google Ads conversions as
                                      applicable to active channels;
                                      consent and deduplication

  Operations                          Monitoring, backups, restore proof,
                                      HTTPS, security checks, UAT and
                                      rollback
  -----------------------------------------------------------------------

If an online payment merchant account is not approved before launch, the
site may launch with COD and a clearly controlled manual advance
workflow only if the business explicitly accepts that limitation. Do not
present online payment as available until the provider has passed live
verification.

## 6. Programme B --- Post-Launch roadmap

These capabilities are valuable, but should not block the first stable
release unless a concrete business dependency makes them mandatory.

### P1 --- First 30 days after launch

-   Add remaining contracted courier partners after certification.
-   Improve delivery prediction, RTO analytics and risk scoring.
-   Expand bank reconciliation automation and finance dashboards.
-   Add customer account self-service, saved addresses and richer order
    tracking if not included in initial release.
-   Improve customer segmentation, repeat-purchase and cohort reporting.
-   Add product feed automation for Google Merchant Center and Meta
    Catalog.
-   Add abandoned-cart recovery subject to consent and messaging
    provider approval.
-   Improve returns portal and warranty/service self-service.
-   Add scheduled management reports and export controls.

### P2 --- 30--90 days after launch

-   Unified inbox and website live chat.
-   WhatsApp Business, Messenger, Instagram and SMS integrations.
-   Advanced marketing campaign orchestration and customer journeys.
-   More complete procurement analytics and supplier performance.
-   Advanced finance: budgeting, cash forecasting,
    multi-entity/multi-currency if required, deeper period-close
    controls.
-   Advanced inventory forecasting, replenishment and stock planning.
-   Workflow automation/rules engine and SLA escalation.

### P3 --- 90+ days after launch

-   Internal AI copilot for sales, CRM, finance, procurement and
    inventory.
-   Public AI product advisor/support assistant with human handoff.
-   Advanced demand forecasting and recommendation engine.
-   Advanced BI, cohort, attribution and profitability analysis.
-   More sophisticated approval workflows and process automation.

------------------------------------------------------------------------

## 7. Explicitly deferred from the launch critical path

Unless a verified business/legal dependency requires them, do not delay
launch for:

-   AI infrastructure, internal AI copilot or public AI assistant.
-   Full unified communication inbox and every messaging channel.
-   Advanced automation/rule builder.
-   Advanced forecasting and recommendation engines.
-   Every possible courier integration; launch with contracted,
    certified partners.
-   Every possible payment gateway; launch with the contracted primary
    gateway plus COD.
-   Advanced multi-company/multi-currency accounting.
-   Deep warehouse optimization beyond the inventory controls needed for
    accurate orders and fulfillment.
-   Cosmetic redesign of already usable ERP pages.
-   Rebuilding modules that already exist and pass acceptance.

## 8. Recommended implementation order and dependencies

  ------------------------------------------------------------------------
                         Order Workstream            Depends on
  ---------------------------- --------------------- ---------------------
                             1 G0 --- Freeze and     Latest source and
                               audit                 staging baseline

                             2 G1 ---                Catalog, customer,
                               Storefront/checkout   pricing, inventory

                             3 G2 ---                Checkout, orders,
                               Payments/advances     finance mappings

                             4 G4 --- Order-to-cash  Payment states,
                               finance               fulfillment and
                                                     return events

                             5 G3 ---                Valid orders,
                               Courier/delivery      addresses, COD and
                                                     shipment lifecycle

                             6 G5 --- Customer/CRM   Customer identity,
                               sync                  order and lead events

                             7 G6 ---                Stable commerce
                               Marketing/analytics   events and
                                                     payment/order
                                                     semantics

                             8 G7 --- UAT/production All launch workflows
                               readiness             
  ------------------------------------------------------------------------

Finance design starts during G1/G2, even though full reconciliation is
finalized in G4. Marketing event contracts are designed early, but final
Purchase measurement must wait until order/payment semantics are stable.

## 9. Engineering and release rules

These rules carry forward from Roadmap v2.0:

-   Latest supplied source ZIP and verified local database state are the
    source of truth.
-   Inspect existing code before editing; reuse existing services and
    provider adapters where correct.
-   Preserve existing business data, IDs, relationships, permissions,
    menu configuration and working features.
-   Never run `migrate:fresh` or destructive reset commands against the
    user's working database.
-   Use additive, backward-compatible migrations wherever possible.
-   Never put `.env`, credentials, API keys, private keys, database
    dumps, `vendor/`, `node_modules/`, `.git/`, logs or runtime uploads
    in patch ZIPs.
-   Keep business logic in services/actions/domain components, not
    duplicated in controllers.
-   Use decimal-safe money arithmetic, transactions, row locks, unique
    constraints and idempotency.
-   Keep stock and posted financial ledgers append-only; correct using
    reversal/adjustment records.
-   Verify provider webhooks with provider-defined signatures and replay
    protection.
-   Do not claim a milestone complete until the user applies the patch
    and supplies verification from that exact source snapshot.
-   Every patch must include exact install steps, migration notes and
    verification commands.

## 10. Definition of successful launch

Madina Scale is ready for public launch when all of the following are
true:

1.  A customer can discover a product and place an order without staff
    manually creating it.
2.  The customer can choose COD or a verified online payment method;
    advance payment works if enabled.
3.  The system creates one canonical customer/order and reserves stock
    correctly.
4.  The payment status is verified server-side and cannot be forged
    through a browser redirect.
5.  Staff can book a courier, track the shipment and handle delivery
    exceptions.
6.  COD and gateway settlements reconcile to the finance ledger and
    bank/cash records.
7.  Revenue, advances, COGS, refunds, supplier liabilities and taxes
    follow approved accounting policies.
8.  Lead, customer and order records stay linked and auditable.
9.  Google and Meta receive deduplicated, consent-aware events that
    reconcile to internal orders.
10. The business can monitor failures, restore backups and roll back a
    release safely.
11. Critical UAT scenarios pass and the business owner signs off.

**Launch principle:** One complete and reconciled
order-to-cash-to-delivery workflow is more valuable than ten partially
implemented advanced modules.

------------------------------------------------------------------------

## 11. First execution milestone

Start with **G0 --- Snapshot freeze and Go-Live gap audit**, then
implement **G1 --- Storefront and checkout** as the first development
patch.

Before G1 implementation, confirm the following business decisions:

-   Primary online payment gateway and merchant approval status.
-   Whether launch supports full payment only, or full plus partial
    advance; if partial, the fixed amount/percentage rule.
-   COD availability and any COD confirmation policy.
-   Delivery partners to activate at launch and their service areas.
-   Shipping fee rules and free-delivery thresholds, if any.
-   Revenue-recognition and return/refund policy.
-   Launch currency, tax/VAT treatment and invoice requirements.
-   Customer communication channels and consent policy.

Do not pause the engineering audit for optional future features. Where a
business decision is not yet available, isolate it behind configuration
and do not invent a financial or legal policy.

**Final direction:** Complete the shortest safe path to a functioning
e-commerce business first. Keep the existing ERP as the operational
backbone, connect every customer-facing event to it, launch with a small
verified provider set, and expand the platform using real post-launch
business data.


## 12. Payment strategy decision record — 15 November 2026

The project owner confirmed this direction:

- Use **bKash first** if SSLCOMMERZ or the selected aggregator is not ready for launch.
- Keep the architecture provider-independent so a broader gateway can be added later.
- Future shortlist: **SSLCOMMERZ**, **aamarPay**, and **DGPay** (the exact provider/legal entity and official integration documentation must be confirmed).
- Do not block the launch solely while waiting for the long-term gateway, provided the available bKash merchant gateway is approved, tested and operational.
- COD remains a separate payment method, not a gateway.
- Partial advance payment is supported only after the business confirms the amount/percentage and finance treatment.

### Technical implications

- Define a common payment-provider contract for initiate, verify/query, callback handling, refund and capability discovery.
- Store provider, merchant reference, internal transaction reference, amount, currency, state, callback/event IDs and settlement references per payment attempt.
- Keep gateway credentials encrypted and server-side.
- Never mark an order paid from the browser redirect alone.
- Use provider callbacks plus server-side verification; enforce idempotency and amount/currency/order matching.
- Keep gateway-specific status mapping inside adapters.
- Reconcile provider gross transactions, fees, refunds and net settlements to bank deposits.
- Do not assume every provider supports the same refund, partial-capture, webhook-signature or settlement APIs; verify capabilities against the selected merchant contract and current official docs.

### Preliminary public documentation references

- bKash online business and payment gateway offerings: https://www.bkash.com/en/business
- SSLCOMMERZ developer documentation: https://developer.sslcommerz.com/
- aamarPay API overview: https://aamarpay.readme.io/reference/overview
- aamarPay webhook/IPN documentation: https://aamarpay.readme.io/reference/instant-payment-notification

These public references establish that the providers publish online payment/integration materials; they do not establish merchant approval, fees, settlement timelines, refund eligibility or availability of credentials for Madina Scale. Those must be confirmed directly during onboarding.


## 13. Checkout and default payment decision record — 15 November 2026

The project owner confirmed:

- **COD is the default selected payment method** for new orders placed by customers and for orders created through staff/system-assisted workflows.
- Customers can explicitly switch to bKash or another enabled gateway.
- COD must be visible and easy to keep selected; it must not be hidden behind extra steps.
- The storefront should make ordering as easy as reasonably possible: guest checkout, minimal required fields, mobile-first layout, autofill-friendly inputs, address/phone reuse when safely available, clear totals and minimal unnecessary steps.
- Account registration should not be mandatory before placing an order. Offer account creation or order-history access after checkout where appropriate.
- Staff/system-created orders inherit COD by default but authorized staff may select another method; overrides must be auditable.
- A temporary gateway outage must never silently mark an order paid or silently alter the customer's selected method. Offer retry or COD as an explicit alternative.

### Implementation and accounting implications

- Persist the selected payment method and its source/default decision on the order/payment record so later changes are traceable.
- Keep payment method (`COD`, `bKash`, future gateway) separate from payment status (`unpaid`, `pending`, `paid`, `partially_paid`, `failed`, `refunded`, etc.).
- A COD order is an order with amount due on delivery; it is **not** a paid order at checkout.
- On delivery/COD collection, record courier cash collection and subsequent courier settlement separately.
- If customer pays an advance online and the remaining balance is COD, track the advance receipt and COD due independently; never count the same amount twice.
- Staff/system order entry must use the same order pricing, stock, customer identity and audit rules as storefront checkout.
