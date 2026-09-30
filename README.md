# Madina Scale --- Architecture & Design System

**Project:** Madina Scale\
**Project Type:** Modern, advanced, analytics-driven commerce / business
management platform\
**Status:** Architecture & Design System Locked --- Foundation Phase\
**Author:** Yahya Al Mahmood

------------------------------------------------------------------------

## 1. Product Vision

Madina Scale is designed as a modern, secure, extensible business
platform with:

-   World-class enterprise architecture
-   Premium, modern and futuristic UI/UX
-   Strong security and granular authorization
-   Deep sales, order and customer management
-   Full analytics and business intelligence
-   Marketing analytics and attribution
-   Meta, Google, TikTok and future marketing integrations
-   Real-time live chat and support
-   Modular architecture for easy feature addition/removal/modification
-   API-first backend architecture
-   Responsive web experiences
-   Bangladesh-first operational considerations with future
    international scalability

The system must remain maintainable as the product grows. New modules
should be added through defined contracts and conventions rather than by
changing unrelated modules.

------------------------------------------------------------------------

# 2. Core Technology Stack

## Backend

-   PHP 8.5+
-   Laravel 13
-   PostgreSQL 18+
-   Redis
-   Laravel Queue
-   Laravel Events / Listeners
-   Laravel Notifications
-   Laravel Policies / Gates
-   REST API
-   WebSocket/realtime layer for chat and realtime events

## Backend Dashboard

-   Vue.js
-   Vite
-   Component-driven UI
-   API-driven data loading
-   Permission-aware navigation
-   Light / Dark / System themes

## Customer / Public Frontend

-   Nuxt.js
-   Vue.js
-   SSR/SEO support
-   API-driven commerce functionality

## Development

-   Composer
-   npm
-   Pest / PHPUnit
-   Git
-   Laravel Boost / AI-assisted development tooling

------------------------------------------------------------------------

# 3. High-Level Architecture

``` text
                         MADINA SCALE
                              |
                    +---------+---------+
                    |                   |
              Backend Platform      Public Frontend
                    |                   |
                 Laravel              Nuxt.js
                    |                   |
              REST / Realtime          Vue.js
                    |
        +-----------+-----------+
        |           |           |
   PostgreSQL     Redis      Storage
        |
   Domain Data
```

### Architectural principles

1.  Backend is the source of truth.
2.  Frontend never replaces backend authorization.
3.  Modules must be independently maintainable.
4.  Domain/business rules stay out of controllers.
5.  External integrations must be isolated behind adapters/services.
6.  Database writes must be transactional where business consistency
    requires it.
7.  Critical actions must be auditable.
8.  API contracts must be versionable.
9.  UI visibility is not security.
10. New features should minimize changes to existing modules.

------------------------------------------------------------------------

# 4. Backend Architectural Style

Madina Scale will use a **modular domain-oriented Laravel
architecture**.

Recommended conceptual structure:

``` text
app/
├── Actions/
├── Console/
├── Contracts/
├── Domains/
│   ├── Identity/
│   ├── Access/
│   ├── Catalog/
│   ├── Customer/
│   ├── Sales/
│   ├── Order/
│   ├── Inventory/
│   ├── Pricing/
│   ├── Payment/
│   ├── Shipping/
│   ├── Marketing/
│   ├── Analytics/
│   ├── Communication/
│   ├── Support/
│   ├── CMS/
│   ├── SEO/
│   ├── Reporting/
│   ├── Notification/
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

The exact physical folder structure may evolve as implementation begins.
The domain boundaries and responsibilities are the important
architectural constraints.

------------------------------------------------------------------------

# 5. Domain Boundaries

## Identity

Responsible for:

-   Users
-   Authentication
-   Sessions
-   Password security
-   2FA
-   Login security
-   Account recovery
-   API credentials/tokens

## Access

Responsible for:

-   Developer role
-   Roles
-   Permissions
-   Policies
-   Module access
-   Feature access
-   Menu visibility
-   User access restrictions
-   Access audit

## Catalog

Responsible for:

-   Products
-   Product variants
-   Categories
-   Brands
-   Attributes
-   Product media
-   Product status

## Customers

Responsible for:

-   Customer profiles
-   Customer groups
-   Customer addresses
-   Customer activity
-   Customer segmentation

## Sales / Orders

Responsible for:

-   Orders
-   Order lifecycle
-   Order items
-   Order status
-   Discounts
-   Taxes
-   Order notes
-   Returns/refunds where applicable

## Inventory

Responsible for:

-   Stock
-   Stock movements
-   Warehouses
-   Inventory adjustments
-   Low-stock rules
-   Inventory analytics

## Marketing

Responsible for:

-   Campaigns
-   Marketing channels
-   Meta Ads
-   Google Ads
-   TikTok Ads
-   Future advertising providers
-   Campaign metrics
-   Spend
-   Attribution inputs

## Analytics

Responsible for:

-   Business KPIs
-   Sales analytics
-   Customer analytics
-   Product analytics
-   Marketing analytics
-   Traffic analytics
-   Attribution
-   Reporting
-   Aggregations

## Communication

Responsible for:

-   Internal chat
-   Customer live chat
-   Support conversations
-   Attachments
-   Presence/status
-   Notifications

## System

Responsible for:

-   Settings
-   Feature flags
-   Module configuration
-   Audit logs
-   Security events
-   System health
-   Developer controls

------------------------------------------------------------------------

# 6. Design Patterns

The project should use patterns deliberately, not mechanically.

## Repository Pattern

Use only where persistence abstraction materially improves the domain.

Avoid creating meaningless repositories that simply duplicate Eloquent
methods.

## Service Pattern

Services coordinate domain operations that span multiple models or
infrastructure components.

## Action Pattern

Use Actions for focused business operations.

Examples:

``` text
CreateOrder
CancelOrder
RefundOrder
CreateUser
AssignRole
SyncMetaCampaigns
SyncGoogleCampaigns
SendSupportMessage
```

Actions should generally represent one meaningful business operation.

## Strategy Pattern

Use for interchangeable providers/algorithms.

Examples:

``` text
Marketing Provider
├── Meta
├── Google
├── TikTok
└── Future Providers
```

## Adapter Pattern

External APIs must not leak provider-specific implementation details
throughout the application.

Example:

``` text
MarketingProviderInterface
        |
   +----+----+------+
   |         |      |
 Meta     Google   TikTok
Adapter   Adapter  Adapter
```

## Factory Pattern

Use for creating provider-specific services, notifications, reports,
etc.

## Observer / Event Pattern

Use events for side effects that should not be tightly coupled to the
main transaction.

Examples:

``` text
OrderCreated
OrderCancelled
PaymentCompleted
UserLoggedIn
CampaignSynced
SupportMessageCreated
```

## Job Pattern

Heavy/background work should use queues.

Examples:

-   Campaign synchronization
-   Analytics aggregation
-   Report generation
-   Email sending
-   Notification fan-out
-   Large exports
-   Media processing

## Policy Pattern

Every protected business resource must have backend authorization rules.

------------------------------------------------------------------------

# 7. API Architecture

API-first approach.

Recommended structure:

``` text
/api/v1/...
```

Future breaking API changes:

``` text
/api/v2/...
```

Controllers should remain thin.

Preferred flow:

``` text
Request
  ↓
Form Request / Validation
  ↓
Controller
  ↓
Action / Domain Service
  ↓
Domain Rules
  ↓
Model / Repository where justified
  ↓
Event / Job where appropriate
  ↓
Resource / Response
```

------------------------------------------------------------------------

# 8. Authentication & Authorization

Authorization must be backend-enforced.

Core model:

``` text
User
  ↓
Role
  ↓
Permission
  ↓
Policy
  ↓
Resource
  ↓
Action
```

Permission naming convention:

``` text
module.resource.action
```

Examples:

``` text
sales.orders.view
sales.orders.create
sales.orders.update
sales.orders.cancel
sales.orders.refund
sales.orders.export

marketing.meta.view
marketing.meta.manage

marketing.google.view
marketing.google.manage

analytics.sales.view
analytics.marketing.view
```

------------------------------------------------------------------------

# 9. Developer Role

The highest-privilege role is named:

**Developer**

There is intentionally no `Super Admin` role in the product terminology.

Developer has the highest system privileges.

Developer capabilities include:

-   Manage users
-   Manage roles
-   Manage permissions
-   Manage modules
-   Lock modules
-   Hide modules
-   Configure feature flags
-   Manage system settings
-   Access security logs
-   Access audit logs
-   Configure integrations
-   Manage critical system controls

Even Developer actions on sensitive resources should be auditable.

------------------------------------------------------------------------

# 10. Module Visibility & Locking

Module state is separate from authorization.

Possible module states:

``` text
ENABLED
DISABLED
LOCKED
HIDDEN
```

Important rule:

> Hidden UI does not mean unauthorized access.

The backend remains the final authority.

Conceptually:

``` text
Module Configuration
        +
User Permission
        +
Policy
        ↓
Final Access Decision
```

This prevents security from depending on Vue navigation.

------------------------------------------------------------------------

# 11. Security Architecture

Security is a cross-cutting concern.

Required areas:

-   Password hashing
-   Secure authentication
-   Session security
-   2FA
-   Login throttling
-   Rate limiting
-   CSRF protection where applicable
-   API authentication
-   Authorization policies
-   Input validation
-   Output/resource transformation
-   SQL injection protection through framework/database abstractions
-   File upload validation
-   MIME/type/size validation
-   Secure storage
-   Audit logs
-   Security event logs
-   Secret management
-   Environment separation
-   Least privilege database access
-   Sensitive-action confirmation where appropriate

Production rules:

``` text
APP_ENV=production
APP_DEBUG=false
```

Secrets must never be committed to Git.

------------------------------------------------------------------------

# 12. Audit & Security Events

Important operations must be auditable.

Audit data should capture, where appropriate:

-   Actor
-   Action
-   Resource
-   Resource identifier
-   Timestamp
-   IP address
-   User agent/device context
-   Before state where appropriate
-   After state where appropriate
-   Result/status
-   Correlation/request identifier

Examples:

``` text
User role changed
Permission changed
Developer locked module
Order cancelled
Refund approved
Marketing integration connected
API credential rotated
Security setting changed
```

------------------------------------------------------------------------

# 13. Database Architecture

Primary database:

**PostgreSQL**

Principles:

-   UUID/ULID strategy to be decided per entity based on requirements
-   Foreign keys
-   Proper indexes
-   Unique constraints
-   Check constraints where valuable
-   Transactions for multi-step business operations
-   Soft deletes only where business requirements justify them
-   UTC timestamps
-   Explicit data ownership/scoping
-   No unnecessary denormalization
-   Reporting/analytics optimizations should not compromise
    transactional integrity

Database migrations are version-controlled.

------------------------------------------------------------------------

# 14. Caching / Queue Architecture

Redis will be used for:

-   Cache
-   Queue
-   Rate limiting where appropriate
-   Temporary state
-   Realtime infrastructure support

Heavy work must not block HTTP requests.

Example:

``` text
HTTP Request
   ↓
Validate
   ↓
Create Job
   ↓
Redis Queue
   ↓
Worker
   ↓
External API / Processing
```

------------------------------------------------------------------------

# 15. Marketing Analytics Architecture

Marketing integrations must be provider-agnostic.

``` text
                Marketing Engine
                       |
          +------------+------------+
          |            |            |
         Meta        Google       TikTok
          |            |            |
       Adapter       Adapter      Adapter
          +------------+------------+
                       |
                 Normalized Data
                       |
                  Analytics
                       |
             Dashboard / Reports
```

## Meta metrics

Potential metrics include:

-   Campaigns
-   Ad sets
-   Ads
-   Spend
-   Impressions
-   Reach
-   Clicks
-   CTR
-   CPC
-   CPM
-   Conversions
-   Conversion value
-   ROAS
-   Cost per conversion

## Google metrics

Potential metrics include:

-   Campaigns
-   Campaign type
-   Spend
-   Impressions
-   Clicks
-   CTR
-   CPC
-   CPM
-   Conversions
-   Conversion value
-   ROAS
-   Cost per conversion

The final metric list should follow the provider APIs and the platform's
normalized analytics model.

------------------------------------------------------------------------

# 16. Analytics Architecture

Analytics should distinguish:

### Operational data

Transactional source of truth:

``` text
Orders
Payments
Products
Customers
Inventory
```

### Analytical data

Derived/aggregated data:

``` text
Revenue trends
Conversion rates
Customer cohorts
Campaign performance
ROAS
Product performance
Traffic
```

Do not overwrite transactional source-of-truth data with analytics
aggregates.

------------------------------------------------------------------------

# 17. Live Chat & Support

The dashboard uses a **single combined floating Chats/Support entry
point**.

It can provide:

-   Customer live chat
-   Internal staff chat
-   Support conversations
-   Conversation assignment
-   Unread counts
-   Presence/status
-   Attachments
-   Search
-   Conversation history
-   Notifications
-   Realtime message delivery

Conceptual flow:

``` text
Customer / Staff
       ↓
Chat API
       ↓
Conversation Domain
       ↓
Message
       ↓
Event
       ↓
Realtime Broadcast
       ↓
Vue Chat UI
```

------------------------------------------------------------------------

# 18. Backend Dashboard --- Locked UI Direction

The user-provided dashboard reference is the visual source of truth.

The design direction is:

-   Premium
-   Modern
-   Smooth
-   Futuristic
-   Enterprise
-   Clean
-   Data-rich without clutter
-   High information hierarchy
-   Strong spacing system
-   Rounded cards
-   Subtle depth
-   Professional charts
-   Responsive layout

The dashboard must not drift into a generic admin-template appearance.

------------------------------------------------------------------------

# 19. Dashboard Main Sections

The locked dashboard concept includes:

-   Overview / KPI section
-   Sales analytics
-   Order analytics
-   Order status
-   Inventory status
-   Top selling products
-   Recent orders
-   Traffic sources
-   Marketing performance
-   Customer growth
-   Quick actions
-   Today's tasks
-   Combined Chats / Support bubble

Marketing performance must support:

``` text
Meta Campaign Insights
Google Campaign Insights
Future Marketing Channels
```

------------------------------------------------------------------------

# 20. Theme System

Three theme modes:

``` text
Light
Dark
System
```

System mode follows the operating-system preference.

Theme preference should persist per user/device according to the final
settings architecture.

Do not hardcode component-specific colors. Use semantic design tokens.

------------------------------------------------------------------------

# 21. Typography System

Primary UI typography should use a modern sans-serif stack.

Recommended:

``` text
Inter
```

Fallback:

``` text
system-ui, -apple-system, BlinkMacSystemFont,
"Segoe UI", sans-serif
```

## Font weights

``` text
400 — Regular
500 — Medium
600 — Semibold
700 — Bold
```

Avoid excessive use of 700.

## Desktop type scale

``` text
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
Caption        12px / 16px / 400
```

## KPI numbers

Recommended:

``` text
KPI value: 28–36px
KPI label: 13–14px
KPI change: 12–13px
```

Use tabular/lining numerals for financial and analytical values where
supported.

------------------------------------------------------------------------

# 22. Spacing System

Use a consistent 4px-based spacing scale.

``` text
4px
8px
12px
16px
20px
24px
32px
40px
48px
64px
```

Common component spacing:

``` text
Card padding: 20–24px
Section gap: 24–32px
Grid gap: 16–24px
Input height: 40–44px
Button height: 40–44px
```

Exact values may be tuned during implementation while preserving the
design system.

------------------------------------------------------------------------

# 23. Border Radius

Recommended scale:

``` text
XS      6px
SM      8px
MD      10px
LG      12px
XL      16px
2XL     20px
Pill    999px
```

Cards should generally use:

``` text
12–16px
```

Avoid excessive rounded UI that makes the enterprise interface look
playful.

------------------------------------------------------------------------

# 24. Color System

Use semantic tokens rather than direct colors.

Conceptual tokens:

``` text
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

Dark and light themes must map the same semantic tokens to different
values.

------------------------------------------------------------------------

# 25. Motion & Interaction

The UI should feel smooth but not animated for the sake of animation.

Recommended:

``` text
Fast feedback:    120–160ms
Standard:         160–220ms
Complex transition: 220–300ms
```

Use motion for:

-   Navigation transitions
-   Dropdowns
-   Dialogs
-   Hover/focus feedback
-   Chart transitions
-   Chat notifications
-   Loading states

Respect:

``` text
prefers-reduced-motion
```

------------------------------------------------------------------------

# 26. Accessibility

Target:

**WCAG 2.2 AA**

Requirements include:

-   Keyboard navigation
-   Visible focus states
-   Semantic HTML
-   Accessible labels
-   Screen-reader support
-   Sufficient contrast
-   Reduced motion support
-   Accessible dialogs
-   Accessible tables
-   Accessible charts with supporting text/data

------------------------------------------------------------------------

# 27. Responsive Strategy

Dashboard breakpoints should be designed around content, not device
names.

Conceptual ranges:

``` text
Mobile:       < 640px
Tablet:       640–1023px
Desktop:      1024–1439px
Large:        1440px+
```

The desktop dashboard is the primary operational workspace, but critical
workflows must remain usable on smaller screens.

------------------------------------------------------------------------

# 28. Component Architecture

Vue components should be organized by responsibility.

``` text
components/
├── ui/
├── layout/
├── navigation/
├── dashboard/
├── charts/
├── tables/
├── forms/
├── dialogs/
├── chat/
├── support/
└── marketing/
```

Do not place business logic inside generic UI components.

Preferred:

``` text
UI Component
    ↓
Composable / Store
    ↓
API Client
    ↓
Laravel API
```

------------------------------------------------------------------------

# 29. State Management

Use centralized state only when state is genuinely shared.

Potential categories:

``` text
Auth State
User State
Permission State
Theme State
Notification State
Chat State
Dashboard Filters
```

Do not put every API response into global state.

------------------------------------------------------------------------

# 30. API Response Philosophy

Responses should be predictable and consistent.

Conceptually:

``` json
{
  "data": {},
  "meta": {},
  "message": null
}
```

Validation errors should have a consistent structure.

Pagination must expose predictable metadata.

Error responses should not leak internal exceptions, SQL, stack traces
or secrets.

------------------------------------------------------------------------

# 31. Error Handling

Application errors should be categorized.

Examples:

``` text
ValidationError
AuthenticationError
AuthorizationError
NotFoundError
ConflictError
BusinessRuleError
IntegrationError
RateLimitError
SystemError
```

Users receive useful messages.

Developers receive structured logs.

Production users never receive stack traces.

------------------------------------------------------------------------

# 32. Observability

The platform should eventually include:

-   Structured logging
-   Request IDs
-   Correlation IDs
-   Queue monitoring
-   Failed job monitoring
-   API latency monitoring
-   Integration failure tracking
-   Security event monitoring
-   Application health checks

------------------------------------------------------------------------

# 33. Testing Strategy

Testing layers:

``` text
Unit Tests
Feature Tests
Integration Tests
API Tests
Authorization Tests
Domain Tests
Browser/E2E Tests
```

Critical business rules must have automated tests.

Especially:

-   Cross-user authorization
-   Cross-account/business access
-   Role/permission boundaries
-   Developer-only actions
-   Financial calculations
-   Order state transitions
-   Marketing synchronization
-   Chat authorization

------------------------------------------------------------------------

# 34. Git & Code Quality

Commit style should be clear and focused.

Examples:

``` text
feat: add role permission management
fix: prevent unauthorized order access
refactor: extract campaign sync action
test: cover developer module locking
chore: update dependencies
```

Do not commit:

``` text
.env
credentials
API secrets
private keys
generated local data
```

------------------------------------------------------------------------

# 35. Configuration & Environment

Environment-specific configuration must stay outside source code.

Examples:

``` text
.env
.env.example
```

Production must use secure secret management.

Never hardcode:

-   Database passwords
-   API keys
-   OAuth secrets
-   Webhook secrets
-   Encryption keys

------------------------------------------------------------------------

# 36. Feature Flags

Feature flags should be used for controlled rollout of major features.

Examples:

``` text
marketing.meta.enabled
marketing.google.enabled
support.live_chat.enabled
analytics.attribution.enabled
new_dashboard.enabled
```

Feature flags are not a replacement for authorization.

------------------------------------------------------------------------

# 37. Integration Architecture

External integrations should follow:

``` text
Provider Contract
       ↓
Adapter
       ↓
Integration Service
       ↓
Domain Action
       ↓
Normalized Domain Data
```

External provider names should not leak through every domain class.

This makes future integrations easier to add/remove/replace.

------------------------------------------------------------------------

# 38. Data Ownership

Each domain owns its business rules and data responsibilities.

Avoid circular dependencies.

Preferred dependency direction:

``` text
Infrastructure
      ↓
Application
      ↓
Domain
```

Where practical, shared abstractions should live in stable
contracts/support areas rather than creating domain-to-domain coupling.

------------------------------------------------------------------------

# 39. Development Workflow

For every major feature:

``` text
Requirement
   ↓
Architecture
   ↓
Domain Design
   ↓
Database Design
   ↓
API Contract
   ↓
Authorization Rules
   ↓
Tests
   ↓
Implementation
   ↓
Integration
   ↓
UI
   ↓
E2E Verification
   ↓
Documentation
```

The backend must be ready and stable before the corresponding frontend
workflow is finalized.

------------------------------------------------------------------------

# 40. Implementation Roadmap

## Phase 0 --- Foundation

-   Laravel
-   PostgreSQL
-   Git
-   Environment configuration
-   Storage
-   Redis
-   Queue
-   Base testing

## Phase 1 --- Identity & Security

-   Users
-   Authentication
-   Developer
-   Roles
-   Permissions
-   Policies
-   Sessions
-   2FA
-   Audit
-   Security events
-   Module access

## Phase 2 --- System / Developer Console

-   Module registry
-   Module states
-   Feature flags
-   Menu registry
-   Developer controls
-   System settings

## Phase 3 --- Catalog

-   Products
-   Variants
-   Categories
-   Brands
-   Media

## Phase 4 --- Customers

-   Customers
-   Groups
-   Addresses
-   Segmentation

## Phase 5 --- Sales / Orders

-   Orders
-   Order items
-   Status lifecycle
-   Discounts
-   Taxes
-   Returns/refunds

## Phase 6 --- Inventory

-   Stock
-   Warehouses
-   Movements
-   Adjustments

## Phase 7 --- Payments & Shipping

-   Payment abstraction
-   Payment providers
-   Shipping providers
-   Tracking
-   Delivery lifecycle

## Phase 8 --- Marketing

-   Meta
-   Google
-   TikTok
-   Campaign sync
-   Normalized metrics

## Phase 9 --- Analytics

-   Business analytics
-   Marketing analytics
-   Attribution
-   Dashboards
-   Reports

## Phase 10 --- Communication

-   Customer chat
-   Internal chat
-   Support
-   Notifications
-   Realtime infrastructure

## Phase 11 --- Frontend

-   Backend dashboard
-   Nuxt public frontend
-   Theme system
-   Responsive UX
-   Accessibility

## Phase 12 --- Production Hardening

-   Security audit
-   Performance
-   Observability
-   Backup
-   Disaster recovery
-   Deployment
-   Load testing
-   E2E verification

------------------------------------------------------------------------

# 41. Non-Negotiable Architecture Rules

1.  Backend is the final security authority.
2.  UI hiding is never authorization.
3.  Developer is the highest product role.
4.  Secrets never enter Git.
5.  External integrations use adapters/contracts.
6.  Business rules do not live in controllers.
7.  Critical workflows are tested.
8.  Financial and order operations are auditable.
9.  PostgreSQL is the transactional source of truth.
10. Analytics must not corrupt operational data.
11. Background work uses queues.
12. Realtime features use dedicated event/broadcasting architecture.
13. New modules should be independently extensible.
14. Avoid premature abstraction.
15. Avoid unnecessary repository/service layers.
16. Prefer explicit domain boundaries.
17. Maintain backward-compatible API contracts where possible.
18. Accessibility is part of the definition of done.
19. Light, Dark and System themes are first-class modes.
20. The locked dashboard visual direction must remain the UI source of
    truth.

------------------------------------------------------------------------

# 42. Current Infrastructure Baseline

At the beginning of implementation:

``` text
Laravel:       13.34.0
PHP:           8.5.9
Composer:      2.10.2
Node:          24.19.0
npm:           11.17.0
PostgreSQL:    18.6
Database:      madina_scale
Frontend:      Vue
Public Frontend: Nuxt.js
```

Development database configuration:

``` text
Database engine: PostgreSQL
Database name:   madina_scale
Application DB user: madina_scale
```

Redis is planned as a core infrastructure component and should be
configured before production workloads that require cache/queue/realtime
scaling.

------------------------------------------------------------------------

# 43. Design System Source of Truth

The user-provided backend dashboard reference image is the visual source
of truth for the backend dashboard.

Implementation should preserve its:

-   Overall layout
-   Navigation hierarchy
-   Premium enterprise visual language
-   Dashboard information hierarchy
-   KPI presentation
-   Analytics presentation
-   Quick Actions / Tasks area
-   Combined Chats / Support bubble
-   Light / Dark / System theme concept

Additional Meta and Google campaign analytics must be integrated without
breaking the locked visual language.

------------------------------------------------------------------------

# 44. Documentation Rule

Every major architectural decision should be documented here.

When architecture changes:

1.  Update this README.
2.  Explain why the change was made.
3.  Update affected diagrams/conventions.
4.  Update implementation guidance.
5.  Add/adjust tests where necessary.

This file is intended to remain a living architectural reference
throughout the project.

------------------------------------------------------------------------

## Final Principle

Madina Scale should be built as a **secure, modular, analytics-driven
platform**, not as a collection of unrelated CRUD screens.

The goal is:

``` text
Simple to extend
        +
Hard to break
        +
Easy to audit
        +
Secure by default
        +
Consistent UX
        +
Provider-independent integrations
        +
Strong analytics foundation
```

**Architecture first. Implementation second. UI on top of stable
contracts.**
