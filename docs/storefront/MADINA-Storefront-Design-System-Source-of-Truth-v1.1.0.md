# MADINA™ Storefront Design System

## Source of Truth --- Version 1.0.0

**Status:** Approved foundation\
**Product:** MADINA™ Weighing Solutions Marketplace\
**Primary reference:** User-approved homepage design,
`MADINA™ Weighing Solutions Marketplace.png`\
**Website:** `www.madinascale.online`\
**Implementation context:** Laravel 13 · Vue 3 · Inertia · TypeScript\
**Document type:** Living design-system specification

------------------------------------------------------------------------

## 1. Purpose and authority

This document is the canonical design and UX specification for the
MADINA™ public storefront. All storefront pages, reusable components,
responsive layouts, interaction states, alerts, dialogs, and future
visual updates must follow it.

### 1.1 Source-of-truth hierarchy

When sources conflict, use this order:

1.  This document's latest approved version.
2.  The latest explicitly approved MADINA™ design reference from the
    project owner.
3.  The live ERP/catalog data and approved business rules for product
    names, categories, prices, stock, variants, promotions, delivery and
    warranty.
4.  Existing application architecture and API contracts.
5.  General design conventions.

A mockup is a visual reference, not a source of product truth. Never
hardcode mockup prices, stock, reviews, delivery promises, countdown
values, or payment availability into production UI.

### 1.2 Core design intent

MADINA should feel:

-   **Modern:** current, purposeful, uncluttered.
-   **Clean:** strong hierarchy, generous whitespace, limited
    decoration.
-   **Premium:** considered typography, quality product photography,
    consistent spacing and restrained motion.
-   **Modest:** trustworthy, practical, not flashy or excessively
    promotional.
-   **Easy:** customers can find products, understand prices, order, and
    track deliveries with minimal effort.

The visual reference uses a white-first canvas, MADINA green and deep
green, clear category navigation, real product-led content, Flash Deals,
and prominent order tracking.

### 1.3 What this document governs

-   Brand colors and design tokens
-   Typography and bilingual content
-   Spacing, grid, layout, breakpoints
-   Semantic markup and accessibility
-   Iconography
-   Motion and interaction
-   Product cards, navigation, forms
-   Alerts, toasts, inline validation, dialogs and popovers
-   Page templates and page-specific patterns
-   Responsive behavior
-   Content, localization, and data integrity
-   Design QA and change management

It does not replace backend business logic, payment/courier provider
specifications, legal policy, or ERP catalog data.

------------------------------------------------------------------------

## 2. Brand and visual language

### 2.1 Brand colors

Use the official MADINA logo artwork supplied by the project owner. Do
not redraw, distort, recolor, crop, or alter its proportions. Use a
transparent/light-background logo variant on white surfaces and an
approved light variant on dark surfaces. If a suitable variant does not
exist, place the original logo on a white logo plate rather than
inventing a new logo.

The following are initial implementation tokens inferred from the
approved green/white visual direction. They must be checked against the
official logo artwork and then frozen as design tokens.

  Token           Initial value Use
  ------------- --------------- ------------------------------------------
  `brand-700`         `#087A32` Primary buttons, links, active states
  `brand-800`         `#056329` Hover/pressed primary actions
  `brand-900`         `#034B25` Deep brand areas and footer
  `brand-950`         `#02391D` Dark utility bar / deepest green surface
  `brand-100`         `#E8F5EC` Soft green section backgrounds
  `brand-50`          `#F4FAF6` Subtle green surface
  `white`             `#FFFFFF` Main page and card surfaces
  `ink-900`           `#101828` Primary text
  `ink-700`           `#344054` Secondary text
  `ink-500`           `#667085` Muted text and metadata
  `line`              `#E4E7EC` Borders and dividers
  `surface`           `#F8FAF9` Neutral alternate surface
  `success`           `#16803C` Confirmed success
  `warning`           `#B54708` Caution / pending attention
  `danger`            `#B42318` Error / destructive state
  `info`              `#175CD3` Informational status

**Color rules**

-   White is the default page background.
-   Green is the primary action and brand cue.
-   Deep green is reserved for high-contrast brand regions: utility bar,
    tracking banner, selected promotional panels, and footer.
-   Pale green is used sparingly for section distinction, not behind
    every component.
-   Do not use green text on green backgrounds unless contrast is
    verified.
-   Avoid gradients except subtle, low-contrast decorative treatment in
    approved hero/promotional artwork.
-   Do not use red for ordinary promotions unless it is the deliberate
    discount badge style; reserve semantic red for errors and
    destructive actions.
-   Never communicate status by color alone; add text and/or an icon.

### 2.2 Section background rhythm

The approved homepage is white-first. Use background changes to group
content, not to decorate every section.

Recommended homepage rhythm:

1.  Utility bar --- deep green
2.  Main header and navigation --- white
3.  Hero --- white / subtle pale green
4.  Service highlights --- white
5.  Main categories --- white
6.  Digital Scale subcategories --- white
7.  Flash Deals --- white or very pale green panel
8.  Product listings --- white
9.  Power Tools / Ladder feature panels --- controlled green/white pair
10. Track Your Order --- deep green
11. Trust/value propositions --- white
12. Newsletter --- pale green
13. Footer --- deep green

Other pages should remain predominantly white with clear neutral
surfaces. Avoid alternating strong green backgrounds on every section.

### 2.3 Logo usage

-   Preserve aspect ratio and clear space.
-   Minimum clear space: at least the height of the logo's symbol mark
    on all sides where practical.
-   Never place the logo over busy imagery.
-   Header logo should be visually prominent but must not overpower
    search/navigation.
-   Footer logo can use the approved light-background or white variant.
-   Use the ™ mark only as supplied in the approved artwork.

------------------------------------------------------------------------

## 3. Typography and bilingual content

### 3.1 Font system

Use a modern, highly legible sans-serif family with broad Latin and
Bengali glyph coverage.

**Recommended stack:**

-   English / Latin: `Inter`
-   Bengali: `Hind Siliguri`
-   System fallbacks:
    `ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif`

CSS example:

``` css
:root {
  --font-sans: "Inter", "Hind Siliguri", ui-sans-serif, system-ui, sans-serif;
  --font-bengali: "Hind Siliguri", "Noto Sans Bengali", sans-serif;
}
```

Use self-hosted, optimized WOFF2 variable font files where licensing
permits. Subset fonts by script (Latin and Bengali) to reduce payload.
Use `font-display: swap`. Avoid loading several weights when a variable
font can cover them.

**Important:** Verify Bengali shaping, conjuncts, punctuation, numerals,
line-height, and fallback behavior in real browsers. Do not rely on a
Latin-only font for Bangla content.

### 3.2 Weight scale

  Token          Weight Use
  ------------ -------- --------------------------------------------
  `regular`         400 Body copy, descriptions
  `medium`          500 Navigation, labels, metadata
  `semibold`        600 Product titles, controls, section headings
  `bold`            700 Page titles, hero headlines, emphasis

Avoid ultra-light weights and excessive all-caps. Use uppercase only for
short English eyebrow labels, not long content.

### 3.3 Type scale

Desktop starting scale:

  Token       Size / line height   Use
  ----------- -------------------- ------------------------------
  `display`   44 / 52 px           Hero headline
  `h1`        36 / 44 px           Page title
  `h2`        28 / 36 px           Major section heading
  `h3`        22 / 30 px           Subsection heading
  `h4`        18 / 26 px           Card/compact section heading
  `body-lg`   18 / 28 px           Hero and lead copy
  `body`      16 / 24 px           Main body
  `body-sm`   14 / 20 px           Supporting copy
  `label`     13 / 18 px           Form labels and metadata
  `caption`   12 / 16 px           Secondary metadata only

Mobile adjustments:

-   Hero headline: 30--34 px
-   H1: 28--32 px
-   H2: 22--26 px
-   Body: 14--16 px
-   Never shrink essential body text below 14 px.
-   Product metadata may use 12--13 px if still comfortably readable.

Use `clamp()` for fluid headings where appropriate. Avoid excessive line
lengths: body copy should generally remain around 45--75 characters per
line.

### 3.4 English + বাংলা language support

The storefront must support English and Bangla as first-class languages.

-   Provide a clear language switcher in the header or utility/account
    area.
-   Keep page structure and component geometry consistent across
    languages.
-   Translate all customer-facing labels, validation messages, alerts,
    dialogs, navigation, statuses, checkout instructions, and emails/SMS
    where applicable.
-   Do not translate brand names, product model identifiers,
    serial/model codes, or official technical units.
-   Product titles and descriptions should use catalog-provided
    localized content when available. Do not machine-translate technical
    specifications silently.
-   Use locale-aware number and currency formatting. Bangladesh currency
    must display as `৳` with the correct amount; choose Bangla or Latin
    numerals consistently per locale.
-   Dates and times must use the selected locale and Bangladesh timezone
    where applicable.
-   Allow text containers to grow naturally; never clip Bengali glyphs
    or force fixed heights around text.
-   For Bangla, use a line-height around 1.55--1.7 depending on font and
    size.

Suggested language labels: `EN` and `বাংলা`. Do not use flags as the
only language indicator.

### 3.5 Copy tone

-   Helpful, direct, confident, respectful.
-   Avoid exaggerated claims such as "best in Bangladesh" unless
    substantiated.
-   Use clear action labels: "Add to Cart", "Buy Now", "Track Order",
    "Continue Shopping".
-   Explain uncertainty honestly: "Estimated delivery" rather than a
    guaranteed date unless guaranteed by the system.
-   Keep technical specifications precise and unit-aware.

------------------------------------------------------------------------

## 4. Layout, spacing and responsive grid

### 4.1 Spacing scale

Use a 4 px base spacing system. Do not introduce arbitrary one-off
spacing values without reason.

  Token          Value
  ------------ -------
  `space-1`       4 px
  `space-2`       8 px
  `space-3`      12 px
  `space-4`      16 px
  `space-5`      20 px
  `space-6`      24 px
  `space-8`      32 px
  `space-10`     40 px
  `space-12`     48 px
  `space-16`     64 px
  `space-20`     80 px
  `space-24`     96 px

### 4.2 Page container

-   Desktop maximum content width: 1440 px.
-   Wide desktop content width: 92--94% with a maximum width.
-   Standard desktop side padding: 32--48 px depending on viewport.
-   Tablet side padding: 24 px.
-   Mobile side padding: 16 px.
-   Keep section edges aligned to a shared page container.
-   Avoid horizontal page scrolling at every breakpoint.

### 4.3 Grid

-   Desktop product grid: 5 columns at wide desktop, 4 columns at
    standard desktop.
-   Tablet: 3 columns, sometimes 2 for narrow tablet.
-   Mobile: 2 columns for product cards; 1 column for forms and tracking
    panels.
-   Category tiles may become horizontally scrollable on mobile with
    visible scroll affordance.
-   Use CSS grid/flexbox; avoid hard-coded pixel widths that break with
    Bangla text.

### 4.4 Breakpoints

Use shared project breakpoints consistently:

  Name        Width
  ------- ---------
  `sm`       640 px
  `md`       768 px
  `lg`      1024 px
  `xl`      1280 px
  `2xl`     1536 px

Treat these as layout thresholds, not device names. Test at intermediate
widths, including 360, 390, 768, 1024, 1280, and 1440 px.

### 4.5 Header behavior

Desktop: - Utility bar at top, compact and dark green. - Main header:
logo, large search, account, wishlist, cart. - Category navigation row
beneath. - Track Order appears in utility bar, navigation where space
allows, and footer. - Header may become sticky after scroll, but must
not cover anchor targets or content.

Mobile: - Compact top strip only if it remains useful. - Main row: menu,
logo, search/cart controls. - Search can expand into a full-width row. -
Use a mobile navigation drawer with grouped categories. - Avoid crowding
all desktop nav links into a tiny row. - Track Order must remain
discoverable in mobile navigation and footer.

### 4.6 Section spacing

-   Desktop section vertical gap: 32--48 px.
-   Mobile section vertical gap: 24--32 px.
-   Heading-to-content gap: 16--20 px.
-   Card internal padding: 16--20 px desktop, 12--16 px mobile.
-   Keep a consistent rhythm across every page.

------------------------------------------------------------------------

## 5. Shape, borders, elevation and imagery

### 5.1 Radius

  Component                     Radius
  ------------------------ -----------
  Small inputs / buttons      8--10 px
  Product cards              12--16 px
  Category cards             12--16 px
  Promotional panels         16--20 px
  Dialogs / drawers          16--20 px
  Pills / badges                999 px

Avoid mixing many unrelated corner radii.

### 5.2 Borders and shadows

-   Default border: 1 px solid `#E4E7EC`.
-   Card shadow should be subtle and used only to separate a surface
    from its background.
-   Suggested shadow: `0 2px 10px rgba(16, 24, 40, 0.06)`.
-   Hover shadow may increase slightly; never use heavy glow.
-   Prefer whitespace and border hierarchy over shadows.

### 5.3 Product imagery

-   Use real catalog images from the ERP/storefront source.
-   Maintain consistent image ratio and object-fit behavior.
-   Product image area should have a neutral white or very pale green
    background.
-   Do not invent product imagery, specifications, accessories, included
    items, or packaging.
-   Use a neutral fallback image when no image is available.
-   Keep images sharp and optimized; use responsive image sizes and
    modern formats where supported.
-   Never crop important parts of scales, ladders, tools, displays,
    cables, or accessories.

### 5.4 Product card pattern

Every product card should have: 1. Product image. 2. Optional verified
promotion badge. 3. Wishlist action. 4. Product title (clamped to a
consistent number of lines without hiding essential model text). 5.
Relevant short specification (capacity, readability, dimensions, model)
when available. 6. Current price. 7. Original price and savings only
when a valid promotion exists. 8. Stock/availability status when
relevant. 9. Add-to-cart action.

Rules: - Price is prominent; original price is muted and struck through
only for a real discount. - Do not fabricate ratings or review counts.
Hide rating area if no verified rating exists. - Do not display a fake
discount percentage. - On mobile, ensure add-to-cart remains easy to
tap. - Card actions must have accessible names and visible focus states.

------------------------------------------------------------------------

## 6. Iconography

### 6.1 Icon library

Use one consistent outline icon family: **Lucide** (or the project's
already-installed compatible Iconify/Lucide set). Do not mix filled,
outlined, emoji, and unrelated icon styles.

Recommended icon size: - 16 px: compact metadata - 18 px: inline
controls - 20 px: buttons and standard navigation - 24 px: feature
highlights - 28--32 px: prominent service/value icon

Default stroke width: 1.75--2 px. Keep icon geometry consistent.

### 6.2 Semantic icon map

  -----------------------------------------------------------------------
  Meaning                             Icon concept
  ----------------------------------- -----------------------------------
  Search                              `search`

  Account                             `user-round`

  Wishlist                            `heart`

  Cart                                `shopping-cart`

  Menu                                `menu`

  Close                               `x`

  Categories                          `layout-grid`

  Digital scale / weighing            `scale`

  Power tools                         `drill` or a suitable tool icon

  Ladder                              `ladder` where available; otherwise
                                      a consistent category illustration

  Flash deals                         `zap`

  Track order                         `package-search` or `truck`

  Delivery                            `truck`

  Authenticity / protection           `shield-check`

  Warranty                            `badge-check`

  Support                             `headset`

  Returns                             `refresh-cw`

  Location                            `map-pin`

  Calendar                            `calendar-days`

  Time                                `clock`

  Add                                 `plus`

  Remove                              `minus`

  Quantity                            `plus` / `minus`

  Success                             `check-circle-2`

  Warning                             `alert-triangle`

  Error                               `x-circle`

  Information                         `info`

  Loading                             `loader-circle`

  Eye / show password                 `eye`

  Eye off                             `eye-off`

  Filter                              `sliders-horizontal`

  Sort                                `arrow-up-down`

  Chevron                             `chevron-down`

  External link                       `external-link`
  -----------------------------------------------------------------------

Use actual available icon names in the implementation library. If a
named icon is unavailable, choose a visually consistent substitute; do
not create emoji icons. Decorative icons should be hidden from assistive
technology. Icon-only buttons require `aria-label`.

------------------------------------------------------------------------

## 7. Motion and animation

Motion should clarify interaction, not decorate the page.

### 7.1 Motion principles

-   Short, subtle, predictable.
-   No continuous movement except a necessary carousel or loading
    indicator.
-   Respect `prefers-reduced-motion`.
-   Never animate layout in a way that causes content jumps.
-   Avoid parallax, excessive bounce, flashing, auto-playing video, or
    aggressive entrance animation.

### 7.2 Timing tokens

  Token                 Duration Use
  ------------------- ---------- --------------------------
  `motion-fast`           120 ms Icon/button state
  `motion-standard`       180 ms Hover, focus, dropdown
  `motion-panel`          220 ms Drawer/modal
  `motion-emphasis`       280 ms Card/section transitions

Use a restrained ease-out curve such as `cubic-bezier(0.2, 0, 0, 1)`.

### 7.3 Approved animations

-   Button hover: slight shade change; optional 1--2 px lift.
-   Product card hover: subtle border/shadow change; do not enlarge the
    whole card excessively.
-   Image hover: optional very subtle zoom (max 1.02) on desktop only.
-   Dropdown: fade + small vertical movement.
-   Mobile drawer: slide from edge with backdrop fade.
-   Modal: fade + small scale from 0.98 to 1.
-   Toast: short slide/fade.
-   Skeleton loading: subtle shimmer; disable or simplify for reduced
    motion.
-   Carousel: user-controlled with arrows/dots; auto-advance only if
    justified, pause on hover/focus, and never make important text
    disappear too quickly.
-   Add-to-cart: update cart count and show confirmation; avoid bouncing
    cart icons.

### 7.4 Reduced motion

When `prefers-reduced-motion: reduce`: - Disable non-essential
transitions and transforms. - Stop auto-advancing carousels. - Keep
loading status understandable without motion.

------------------------------------------------------------------------

## 8. Alerts, notifications and validation

Use the correct feedback pattern for the context. Do not use browser
`alert()` or `confirm()`.

### 8.1 Feedback pattern selection

  -----------------------------------------------------------------------
  Situation               Pattern                 Placement
  ----------------------- ----------------------- -----------------------
  Added to cart           Success toast + cart    Bottom-right desktop;
                          count update            bottom above mobile nav

  Wishlist updated        Small toast             Same toast region

  Form field invalid      Inline validation       Directly below field

  Checkout submission     Error summary + inline  Above form and at
  failed                  field errors            affected fields

  Payment pending/failed  Persistent status panel Payment/order result
                                                  area

  Order placed            Dedicated confirmation  Page-level
                          page + optional toast   

  Coupon invalid          Inline message near     Coupon panel
                          coupon field            

  Network/server issue    Toast for transient     Contextual
                          issue; inline retry     
                          panel for persistent    
                          issue                   

  Destructive action      Confirmation dialog     Centered dialog

  Stock changed           Persistent inline       Near affected item
                          notice in cart/checkout 

  Session expired         Dialog or full-page     Contextual
                          sign-in state depending 
                          on unsaved work         

  Cookie/consent choice   Consent banner/dialog   Bottom or centered,
                                                  based on consent design
  -----------------------------------------------------------------------

### 8.2 Toast design

-   Width: 320--380 px desktop; near full width on mobile with 16 px
    side margins.
-   White surface, 1 px border, subtle shadow, 12 px radius.
-   Left semantic icon; concise title and optional one-line description.
-   Optional action link (e.g. "View cart").
-   Close button.
-   Success: green icon/accent; warning: amber; error: red; info: blue.
-   Do not use full solid red/green backgrounds for ordinary toast
    messages.
-   Auto-dismiss after about 4--6 seconds; errors with important
    recovery steps should remain until dismissed or replaced.
-   Announce via `aria-live="polite"`; urgent errors use `assertive`
    only when necessary.

Examples: - "Added to cart" --- "ZamZam 10kg/1g Digital Scale is in your
cart." - "Wishlist updated" --- "Saved to your wishlist." - "Couldn't
update cart" --- "Please try again."

### 8.3 Inline validation

-   Validate on blur and on submit; avoid showing errors before the user
    interacts.
-   Clear a field error when the value becomes valid.
-   Error text should state how to fix the issue.
-   Associate messages with controls using `aria-describedby`.
-   Use `aria-invalid="true"` for invalid fields.
-   Keep values after server validation failure.
-   Never rely only on a red border.

### 8.4 Alert wording

Use plain language and a next step. Avoid technical error codes in
customer-facing messages. Provide a reference code only when useful for
support.

------------------------------------------------------------------------

## 9. Dialogs, popovers, drawers and overlays

### 9.1 Dialog visual pattern

-   White surface, 16--20 px radius, subtle shadow.
-   Desktop width: 440--560 px for ordinary dialogs; wider only when
    content requires.
-   Mobile: near full-width with 16 px outer margin, or bottom sheet
    when appropriate.
-   Clear title, concise explanation, primary action, secondary action.
-   Close icon at top-right with accessible label.
-   Focus moves into dialog and returns to the trigger on close.
-   Escape closes non-critical dialogs; do not allow accidental
    dismissal of a destructive workflow without clear state handling.
-   Backdrop should be dark enough to separate content but not opaque.

### 9.2 Confirmation dialog

Use for destructive or irreversible actions only.

Example: - Title: "Remove this item?" - Body: "This item will be removed
from your cart." - Primary destructive action: "Remove item" -
Secondary: "Keep item"

Do not ask for confirmation for ordinary navigation, opening a product,
adding to cart, or changing a non-destructive filter.

### 9.3 Popovers and dropdowns

-   Use for compact menus and contextual controls.
-   White surface, subtle border/shadow, 8--12 px radius.
-   Align to trigger and avoid clipping at viewport edges.
-   Close on outside click and Escape.
-   Keyboard navigation must work.
-   Do not place long forms in tiny popovers.

### 9.4 Drawers

Use for: - Mobile category navigation - Mobile cart preview (if
adopted) - Filters on mobile - Account navigation where useful

Drawer must have a clear heading, close control, focus management,
scrollable content, and visible primary action where applicable.

### 9.5 Modal use cases

Appropriate: - Sign-in/register when a modal is genuinely easier than a
dedicated page - Quick product variant selection - Confirm
removal/cancellation - Coupon details or shipping information - Consent
choices

Prefer dedicated pages for checkout, payment, order details, and full
tracking results. Do not overload the storefront with modal workflows.

------------------------------------------------------------------------

## 10. Navigation and search patterns

### 10.1 Main navigation

Primary categories from the current catalog should be driven by the
authoritative catalog/category structure, not hardcoded visual
assumptions.

Known primary navigation direction: - Digital Scale - Power Tools -
Ladder - Accessories - Brands - Flash Deals - Track Order

"All Categories" opens a structured mega-menu on desktop and a grouped
drawer on mobile.

### 10.2 Mega-menu

-   White panel with clear category columns.
-   Parent category title and subcategory links.
-   Optional category image only where it improves discovery.
-   No excessive promotional clutter.
-   Keep keyboard focus and hover behavior reliable.
-   On touch devices, tap to open; do not rely on hover.

### 10.3 Search

Search should support: - Product name - Model/sku - Category - Brand,
where available - Relevant product attributes when indexed

Search suggestions: - Show matching products and categories. - Highlight
the matching text. - Display small image, name, and current price only
when available. - Include "View all results". - Provide a clear empty
state and recovery suggestion. - Debounce network requests and cancel
stale requests. - Keyboard support: arrows, Enter, Escape.

Never display invented search suggestions or stale prices.

------------------------------------------------------------------------

## 11. Page design patterns

All pages use the same container, typography, colors, spacing, controls,
product cards, alerts, and footer. Page-specific layouts may differ, but
visual language must not.

### 11.1 Home page

Order: 1. Utility bar 2. Main header 3. Category navigation 4. Hero
carousel 5. Service highlights 6. Main category cards 7. Digital Scale
subcategory browser 8. Flash Deals 9. Best sellers / popular products
10. Power Tools feature collection 11. Ladder feature collection 12.
Track Your Order full-width section 13. Trust/value propositions 14.
Customer reviews only if verified 15. Newsletter 16. Footer

Keep home page scannable. Avoid duplicating the same product row
multiple times without a clear purpose.

### 11.2 All Products / Product Listing

Must include: - Breadcrumbs - Page title and optional short
description - Result count - Sort control - Filter sidebar on desktop -
Filter drawer on mobile - Active filter chips - Product grid -
Pagination or accessible "Load more" - Empty state with "Clear filters"

Filters should be based on actual catalog attributes: category, brand,
price range, capacity, readability, platform/size, stock/availability,
and other relevant attributes. Do not show irrelevant filters for a
category.

Sort options may include relevance, newest, price low-high, price
high-low, and popularity only if supported by data.

### 11.3 Category Listing

-   Breadcrumbs and category intro.
-   Subcategory tiles when applicable.
-   Category-specific filters.
-   Product listing.
-   SEO-friendly heading and content.
-   Keep category hierarchy consistent with ERP/catalog taxonomy.

### 11.4 Product Details

Desktop: - Image gallery on left. - Product title, model, availability,
price, variant selection, quantity and actions on right. - Clear
shipping/warranty information when verified. - Technical specifications
in structured table. - Description, features, included items, documents
and related products in separate sections.

Mobile: - Image gallery first. - Title, model, price and availability. -
Variant and quantity controls. - Sticky bottom purchase bar only if it
does not obscure content or accessibility controls.

Rules: - Variant selection must update SKU, price, stock, image, and
other dependent data. - Disable unavailable variants with an
explanation. - Show unit/capacity/readability accurately. - Never invent
warranty duration, delivery promise, certification, stock, or
compatibility. - Add-to-cart feedback uses the toast pattern.

### 11.5 Cart

-   Clear item list, image, title, model/variant, unit price, quantity,
    line total.
-   Remove and save-for-later only if supported.
-   Show subtotal, discount, shipping estimate (if known), and total.
-   Explain that final shipping cost may depend on
    address/serviceability if not yet calculated.
-   Handle stock/price changes transparently.
-   Primary CTA: "Proceed to Checkout".
-   Empty cart has a useful "Continue Shopping" action.

### 11.6 Checkout

-   Mobile-first, low-friction guest checkout.
-   Do not force account creation unless a real security/business
    requirement exists.
-   Keep only essential fields; progressive disclosure for optional
    fields.
-   Clearly show delivery address, contact, order summary, delivery
    charge, and total.
-   COD is the default selected payment method as specified in project
    business decisions.
-   Other online methods appear only when enabled and operational.
-   Never silently switch payment method if a gateway fails.
-   Explain COD amount due and any advance payment separately.
-   Show privacy and order communication information in plain language.
-   Prevent duplicate submissions and show clear processing state.
-   On failure, preserve entered values.

### 11.7 Track Order

Tracking is a first-class storefront feature and must appear in three
locations: 1. Top utility bar: `Track Order` replaces
`Easy Return & Exchange`. 2. Dedicated full-width homepage section
immediately after product listings/collections. 3. Footer under Customer
Support.

Tracking landing page: - Title: "Track Your Order" - Short explanation -
Order number input - Secondary verification factor if required by secure
lookup policy (for example phone number/OTP; decide based on backend
security design) - Primary CTA: "Track Order" - Example format only if
it matches actual order-number format. - Help link for customers who
cannot find the order number.

Tracking result: - Order status and latest update time - Clear progress
timeline - Shipment/courier name and tracking reference when available -
Estimated delivery only when supported - Items summary and delivery
status - Support contact - No sensitive customer data exposed to
unauthenticated users

Suggested status presentation: - Order received - Confirmed -
Processing - Ready for shipment - Shipped - Out for delivery - Delivered

Also handle: - On hold / awaiting confirmation - Cancelled - Delivery
failed / return-to-origin - Returned / refunded - Tracking unavailable
or courier not yet assigned

The displayed status must map to real order/shipment state. Do not imply
that a courier scan exists when it does not.

### 11.8 Flash Deals

-   Show only active, approved promotions.
-   Countdown must use a real server-defined end time.
-   Expired deals disappear or clearly show ended state.
-   Current price, original price, discount and eligibility must be
    accurate.
-   Do not use fake urgency or reset countdown timers.
-   Show terms when required.

### 11.9 Power Tools / Ladder collections

-   Use real category products and catalog attributes.
-   Collection banner must not imply safety certification or load
    capacity unless verified.
-   For ladders, display step count, material, height and load rating
    only when sourced.
-   For tools, display voltage, wattage, accessories and compatibility
    only when sourced.
-   Product-specific filters should match actual attributes.

### 11.10 Account, Wishlist and Order History

-   Consistent header and account navigation.
-   Clear sign-in/register states.
-   Wishlist empty state.
-   Order list with status and "View details" / "Track order".
-   Customer data must be private and access-controlled.
-   Guest orders should be securely claimable or discoverable according
    to backend policy.

### 11.11 Informational pages

About Us, Contact Us, FAQs, Warranty Policy, Shipping/Delivery, Return &
Refund, Privacy Policy and Terms & Conditions use: - Standard page title
and breadcrumb - Readable content width - Clear heading hierarchy -
Last-updated date where relevant - Contact CTA where appropriate

Policy content must be approved by the business; do not invent legal
terms.

------------------------------------------------------------------------

## 12. Forms and controls

### 12.1 Inputs

-   Label always visible; placeholder is not a label.
-   Input height: 44--48 px desktop and mobile.
-   Clear focus ring using brand color with sufficient contrast.
-   Required fields marked clearly.
-   Helper text and validation message have stable spacing.
-   Input types and autocomplete attributes must be correct.
-   Numeric fields must accept the appropriate locale digits where
    feasible.

### 12.2 Buttons

Variants: - Primary: brand green fill, white text. - Secondary: white
fill, brand border/text. - Tertiary: text-only, brand text. -
Destructive: danger color, only for destructive actions. - Icon button:
square hit target with accessible label.

Sizes: - Small: 36 px height - Medium: 44 px height - Large: 48--52 px
height

All interactive targets should be at least 44 × 44 px where practical.
Include hover, active, focus-visible, disabled and loading states.

### 12.3 Quantity control

-   Explicit decrement and increment buttons.
-   Minimum and maximum enforced by inventory/business rules.
-   Disabled states at limits.
-   Announce changes accessibly.
-   Avoid free-form quantity values unless needed for bulk orders.

------------------------------------------------------------------------

## 13. Accessibility and semantic HTML

Target WCAG 2.2 AA as the accessibility baseline.

### 13.1 Semantic page structure

Use landmarks: - `<header>` for site header -
`<nav aria-label="Primary navigation">` - `<main id="main-content">` -
`<section aria-labelledby="...">` for meaningful content groups -
`<article>` for independently meaningful product/review cards where
appropriate - `<aside>` for complementary filters or supporting
content - `<footer>` for site footer

Use one clear page-level `<h1>`. Maintain heading order; do not choose
heading tags for visual size alone.

### 13.2 Accessibility requirements

-   Full keyboard navigation.
-   Visible focus state.
-   Correct labels and accessible names.
-   Descriptive alt text for meaningful product images; empty alt for
    decorative imagery.
-   Contrast at least WCAG AA.
-   Dialog focus management.
-   Error identification and correction guidance.
-   Status announcements for cart, search and form updates.
-   Reduced-motion support.
-   Do not use color as the only status indicator.
-   Avoid keyboard traps.
-   Provide skip link to main content.

### 13.3 Links vs buttons

-   Use `<a>` for navigation and destinations.
-   Use `<button>` for actions, toggles, opening dialogs, adding to
    cart, and submitting forms.
-   Do not use clickable `<div>` or `<span>` as controls.
-   Product cards may link to details, but nested action buttons must
    remain independently operable.

------------------------------------------------------------------------

## 14. Loading, empty, error and offline states

Every data-driven component must define: - Loading state - Success/data
state - Empty state - Error state - Retry/recovery state where
appropriate

Examples: - Product list skeleton cards - Search "No matching
products" - Empty cart - Empty wishlist - No orders yet - No tracking
update yet - Out-of-stock product - Failed image fallback - Network
disconnected / retry

Do not leave blank sections when data is missing. Do not show fake
product cards as a fallback.

------------------------------------------------------------------------

## 15. Security, privacy and trust UX

-   Never expose private customer data through order tracking.
-   Do not rely on an order number alone if it is guessable; define
    secure verification with backend.
-   Do not expose payment credentials, gateway references, internal risk
    flags, or staff-only notes.
-   Show only customer-safe payment and shipment statuses.
-   Consent and marketing tracking must follow the project's approved
    privacy/consent policy.
-   Never pre-check optional marketing consent.
-   Do not claim "secure", "certified", "guaranteed", or "authentic"
    beyond verified business evidence.
-   Payment success must be based on verified server-side payment state,
    not a browser redirect alone.
-   Error messages must not leak stack traces, internal IDs, or
    sensitive data.

------------------------------------------------------------------------

## 16. Performance and implementation guidance

-   Reuse design tokens; avoid scattered hardcoded colors, spacing and
    radii.
-   Build shared components for header, navigation, breadcrumbs, product
    cards, price display, filters, forms, toast, modal, tracking
    timeline and footer.
-   Keep page templates data-driven.
-   Avoid duplicating markup across English and Bangla.
-   Use route-aware navigation and preserve query parameters for filters
    where appropriate.
-   Lazy-load below-the-fold images.
-   Prioritize hero/LCP image.
-   Set image dimensions/aspect ratio to prevent layout shift.
-   Use responsive `srcset`/sizes and optimized formats.
-   Avoid shipping large icon/font bundles unnecessarily.
-   Do not introduce animation libraries for simple CSS transitions.
-   Ensure SSR/Inertia hydration remains stable.
-   Use TypeScript types for product, price, variant, stock, promotion,
    order and shipment view models.
-   UI must handle null/missing optional catalog fields gracefully.
-   Keep server-authoritative prices and totals; never trust
    client-calculated totals.

### 16.1 Design tokens

Centralize tokens in the existing project styling architecture.
Suggested naming:

``` css
:root {
  --color-brand-700: #087A32;
  --color-brand-800: #056329;
  --color-brand-900: #034B25;
  --color-brand-950: #02391D;
  --color-brand-100: #E8F5EC;
  --color-brand-50: #F4FAF6;

  --color-text-primary: #101828;
  --color-text-secondary: #344054;
  --color-text-muted: #667085;
  --color-border: #E4E7EC;
  --color-surface: #F8FAF9;
  --color-white: #FFFFFF;

  --font-sans: "Inter", "Hind Siliguri", ui-sans-serif, system-ui, sans-serif;

  --radius-control: 10px;
  --radius-card: 14px;
  --radius-panel: 18px;
  --radius-pill: 999px;

  --shadow-card: 0 2px 10px rgba(16, 24, 40, 0.06);

  --motion-fast: 120ms;
  --motion-standard: 180ms;
  --motion-panel: 220ms;

  --container-max: 1440px;
}
```

This is a starting token set. Keep a single authoritative implementation
source; do not duplicate conflicting tokens in several files.

------------------------------------------------------------------------

## 17. Page inventory and consistency checklist

The storefront design system should cover these routes/pages as
applicable:

  -----------------------------------------------------------------------
  Page                                Shared patterns required
  ----------------------------------- -----------------------------------
  Home                                Header, categories, promotions,
                                      products, tracking, footer

  All Products                        Breadcrumb, filters, sorting,
                                      product grid

  Main Category                       Category intro, subcategories,
                                      filters, products

  Subcategory                         Breadcrumb, relevant filters,
                                      products

  Search Results                      Search query, filters,
                                      results/empty state

  Flash Deals                         Active deal list, truthful
                                      countdown, promotion terms

  Product Details                     Gallery, variants, price, stock,
                                      specs, cart actions

  Cart                                Item list, totals, stock/price
                                      changes

  Checkout                            Guest-friendly form, COD default,
                                      payment choice, summary

  Order Confirmation                  Order reference, next steps,
                                      payment/order state

  Track Order                         Secure lookup, progress timeline,
                                      shipment updates

  Sign In / Register                  Accessible forms and clear errors

  Account Dashboard                   Account navigation and order
                                      summary

  Order History                       Orders, statuses, details, tracking

  Wishlist                            Product cards and empty state

  About Us                            Editorial content template

  Contact Us                          Contact information and form

  FAQ                                 Searchable/grouped questions if
                                      needed

  Warranty / Shipping / Returns       Policy content template

  Privacy / Terms                     Legal content template

  404 / 500 / Maintenance             Branded recovery and support path
  -----------------------------------------------------------------------

### 17.1 Cross-page consistency rules

-   Same header, footer, typography, color, spacing and control
    patterns.
-   Same product card and price treatment everywhere.
-   Same button hierarchy.
-   Same form labels and validation behavior.
-   Same alert/dialog patterns.
-   Same breadcrumbs.
-   Same loading/empty/error conventions.
-   Same language-switching behavior.
-   Same mobile navigation model.
-   Same accessibility baseline.

------------------------------------------------------------------------

## 18. Design QA and acceptance criteria

A page is not considered design-complete until it passes:

### Visual QA

-   Correct logo and brand colors.
-   Consistent spacing and typography.
-   No unexpected horizontal overflow.
-   Images use correct aspect ratios and do not distort.
-   No clipped English or Bangla text.
-   Consistent card/button radii and shadows.
-   Responsive layout checked at required widths.

### Functional UX QA

-   All links and buttons work.
-   Search suggestions and empty states work.
-   Filters and sorting work.
-   Variant changes update relevant product data.
-   Cart and wishlist feedback appears correctly.
-   Forms show inline validation.
-   Dialogs and drawers support keyboard and focus.
-   Tracking lookup handles valid, invalid, pending, delivered and
    unavailable states.
-   No fake countdown, ratings, prices, stock or delivery promises.

### Accessibility QA

-   Keyboard-only completion of core journeys.
-   Visible focus.
-   Semantic headings and landmarks.
-   Accessible names for icon-only controls.
-   Contrast checks.
-   Screen-reader announcements for dynamic feedback.
-   Reduced-motion behavior.

### Core customer journeys

1.  Find a category → choose product → view details.
2.  Search by product/model → open result.
3.  Add product → update quantity → checkout.
4.  Place COD order as guest.
5.  Open Track Order → securely verify → view current status.
6.  Switch English/Bangla without losing context.
7.  Recover from invalid input, unavailable product, failed request, or
    empty result.

------------------------------------------------------------------------

## 19. Versioning and change management

This is a living source of truth.

### 19.1 Version policy

Use semantic versioning: - **MAJOR:** breaking change to visual system,
tokens, component behavior, or page architecture. - **MINOR:** new
component, page pattern, or approved feature. - **PATCH:**
clarification, typo fix, small token adjustment, or non-breaking
refinement.

Current version: `1.0.0`.

### 19.2 Change process

For every design-system change: 1. Identify the reason and affected
pages/components. 2. Check impact on English and Bangla layouts. 3.
Check desktop, tablet, and mobile behavior. 4. Check accessibility and
motion implications. 5. Update this document and version number. 6.
Update centralized tokens/components. 7. Run visual and functional QA.
8. Record the change in the changelog.

Do not silently introduce one-off styles that conflict with this
document. If a genuine exception is needed, document the reason and
scope.

### 19.3 Changelog

  -----------------------------------------------------------------------
  Version                 Date                    Change
  ----------------------- ----------------------- -----------------------
  1.0.0                   2026-11-15              Initial approved MADINA
                                                  storefront
                                                  design-system source of
                                                  truth based on the
                                                  approved homepage
                                                  design.

  -----------------------------------------------------------------------

------------------------------------------------------------------------

## 20. Final implementation rule

**If a storefront page or component is not explicitly specified, reuse
the closest existing pattern in this document. Do not invent a new
visual language.**

The goal is one coherent MADINA experience across every page:
white-first, green-led, clean, premium, modest, accessible, bilingual,
responsive, and easy for customers to use.

All product facts, prices, inventory, promotions, delivery estimates,
warranty terms, and order states must come from authoritative
application data---not from this visual specification or mockup.


---

# 21. Image System, Asset Dimensions and Placeholder Rules

**This section is mandatory for every storefront page and every image-bearing component.** The approved reference supplied for social preview dimensions demonstrates that image slots must have a defined aspect ratio and pixel size before real artwork is available. The same principle applies throughout MADINA: reserve the correct image space first, show a dimension-labelled placeholder when the real image is missing, and replace it automatically when a valid, current asset becomes available.

## 21.1 Non-negotiable image behavior

1. Every image slot must have a declared purpose, aspect ratio, target dimensions, responsive variants, crop rule, and fallback.
2. Never render a broken-image icon, empty black box, random stock image, or unrelated product image.
3. If an image is missing, render a branded placeholder with:
   - the intended image aspect ratio;
   - the recommended pixel dimensions (for example, `1200 × 628 px`);
   - a neutral MADINA visual treatment;
   - a short label such as `Hero Banner`, `Product Image`, or `Category Image`;
   - optional neutral icon/silhouette only when it helps identify the slot.
4. The placeholder is a temporary design state, not a permanent asset. When a valid image is added or updated in the authoritative media/catalog source, the newest valid image must replace the placeholder without a code change.
5. Never use a placeholder's dimensions as the actual image content. The placeholder must not be mistaken for a real product photo.
6. Never substitute another product's image because the correct image is missing.
7. Do not invent product images, model details, packaging, included accessories, certifications, or product variants.
8. Keep image containers dimensionally stable before the image loads to prevent layout shift.
9. Use descriptive alt text for meaningful images and empty alt text for purely decorative images.
10. If an image fails to load, show the same branded placeholder and log/report the asset failure through the application's normal observability path.

## 21.2 Image dimension terminology

- **Master/original:** high-quality source file uploaded to the media library.
- **Display size:** intended rendered pixel size for a particular UI slot.
- **Responsive derivative:** optimized file generated for a specific width/DPR.
- **Aspect ratio:** width divided by height; must remain stable in the slot.
- **Safe area:** central region where essential product/logo/text content should remain visible after responsive cropping.
- **Placeholder:** temporary labelled visual used when no valid image is available.

The dimensions below are recommended source/display targets for this storefront. The implementation may generate additional responsive derivatives, but must preserve the specified aspect ratio unless a component explicitly defines another crop.

## 21.3 Global image handling standards

### Supported formats

Preferred:
- AVIF for modern browsers when generated and validated.
- WebP as broad modern fallback.
- JPEG for photographic source compatibility.
- PNG for transparency where required.
- SVG only for trusted vector artwork such as approved logos and interface illustrations.

Do not accept SVG uploads from untrusted sources without sanitization. Do not use animated GIFs for ordinary product or banner imagery.

### Responsive delivery

Use responsive image delivery (`srcset` and `sizes`, or the project's equivalent image component/CDN transformation). Generate appropriate derivatives rather than serving a 3000 px original to a 320 px mobile card.

Suggested responsive widths:
- 320, 480, 640, 768, 960, 1200, 1440, 1920 px, where the source resolution supports them.
- Do not upscale a small original and present it as a high-resolution image.
- Preserve the original master separately from optimized derivatives.

### Loading and layout stability

- Hero/LCP image: prioritize loading; do not lazy-load the first visible hero image.
- Above-the-fold logo and essential UI images: eager/high priority as appropriate.
- Below-the-fold category, product and editorial images: lazy-load.
- Always specify intrinsic width/height or an aspect-ratio container.
- Use `object-fit: contain` for product cut-outs and technical equipment unless the approved asset specifically requires cropping.
- Use `object-fit: cover` for lifestyle/editorial banners only when the focal subject remains visible.
- Use focal-point metadata for images with important subject placement.
- Avoid CSS background images for meaningful product images because they do not provide equivalent alt text and responsive semantics.

### Asset quality and content

- Product images should use a clean, consistent background and show the actual product.
- Prefer a consistent viewing angle and lighting across a product family.
- Avoid watermarks, third-party logos, unrelated props, text baked into product photos, and low-resolution images.
- Do not use AI-generated imagery as an actual product photo. It may be used for abstract decorative artwork only if clearly not representing a real product.
- Promotional artwork must not contain prices or discount claims that can become stale. Prefer live HTML text layered over a clean image where practical.

## 21.4 Storefront image dimension matrix

The dimensions below are recommended design-system targets. Keep these values centralized as media-slot configuration rather than scattering them through components.

| Image slot | Recommended master / target | Aspect ratio | Display behavior |
|---|---:|---:|---|
| Main logo (header) | SVG preferred; PNG 800 × 240 px fallback | 3.33:1 | `contain`; preserve exact logo proportions |
| Footer logo | SVG preferred; PNG 800 × 240 px fallback | 3.33:1 | `contain`; approved light/dark variant |
| Favicon / app icon | 512 × 512 px | 1:1 | `contain`; generate favicon sizes |
| Home hero desktop | 1920 × 600 px | 3.2:1 | `cover` only if focal safe area is protected |
| Home hero mobile | 900 × 1100 px | 0.82:1 | Separate art direction preferred; do not blindly crop desktop |
| Secondary promotional banner | 1200 × 420 px | 2.86:1 | `cover`; keep text in HTML |
| Main category card | 640 × 420 px | 1.52:1 | `contain` for isolated product/category image |
| Subcategory tile | 480 × 360 px | 4:3 | `contain` |
| Product listing card | 800 × 800 px | 1:1 | `contain`; consistent product scale |
| Product detail primary gallery | 1400 × 1400 px | 1:1 | `contain`; zoomable |
| Product detail gallery thumbnail | 240 × 240 px | 1:1 | `contain` |
| Product detail lifestyle/usage image | 1400 × 1050 px | 4:3 | `contain` or `cover` based on source |
| Product specification diagram | 1600 × 1200 px | 4:3 | `contain`; preserve labels and dimensions |
| Brand logo tile | 600 × 360 px | 5:3 | `contain`; transparent background where available |
| Flash Deals product image | 800 × 800 px | 1:1 | Same product-card image pipeline |
| Power Tools collection banner | 1200 × 500 px | 2.4:1 | `cover`; focal point protected |
| Ladder collection banner | 1200 × 500 px | 2.4:1 | `cover`; full ladder should remain visible |
| Track Order decorative illustration | 640 × 420 px | 1.52:1 | `contain`; decorative, empty alt |
| Customer avatar | 160 × 160 px | 1:1 | `cover`; only with permission/valid source |
| Customer review product image (optional) | 320 × 320 px | 1:1 | `contain` |
| Blog/article card thumbnail | 900 × 600 px | 3:2 | `cover` |
| Blog/article hero | 1600 × 900 px | 16:9 | `cover`; focal point protected |
| Payment provider logo | 240 × 120 px | 2:1 | `contain`; use approved provider marks |
| Social icon | SVG preferred; PNG 96 × 96 px | 1:1 | `contain` |
| Contact/location image (optional) | 1200 × 800 px | 3:2 | `cover` |
| Social share image (Facebook/LinkedIn/X) | 1200 × 628 px | 1.91:1 | `cover`; central safe area |
| Pinterest share image | 1000 × 1500 px | 2:3 | `cover`; vertical composition |
| Generic Open Graph fallback | 1200 × 628 px | 1.91:1 | `cover`; brand mark and page title safe |
| Email/newsletter header (optional) | 1200 × 400 px | 3:1 | `cover` |
| Error/empty-state illustration | 640 × 480 px | 4:3 | `contain`; preferably SVG/vector |

If an existing official brand asset has another aspect ratio, preserve the original proportions and update the slot specification through a documented design-system change. Never stretch the asset to force it into the table's dimensions.

## 21.5 Social share image standards

The user-provided reference image illustrates the following social preview ratios:

| Platform / use | Target dimensions | Ratio |
|---|---:|---:|
| Facebook link preview | 1200 × 628 px | 1.91:1 |
| LinkedIn link preview | 1200 × 628 px | 1.91:1 |
| X/Twitter large card | 1200 × 628 px | 1.91:1 |
| Pinterest vertical pin | 1000 × 1500 px | 2:3 |

Rules:
- Create a platform-appropriate derivative from a common campaign/source artwork where possible.
- Keep MADINA logo and important text away from crop edges.
- Use concise page-specific title/description metadata.
- Product pages should use the actual product image where it is suitable and available.
- Category pages should use category artwork, not a random product.
- If no page-specific image exists, use the branded generic Open Graph fallback.
- Ensure Open Graph and Twitter/X metadata use absolute, publicly accessible image URLs.
- Do not place tiny legal copy or important product details in the social image.
- Test the final URL with the relevant platform preview/debugger before a campaign goes live.

## 21.6 Placeholder preview design

All missing image slots must show a reusable placeholder component. The component must accept:
- `kind` (hero, product, category, brand, avatar, banner, social, diagram, generic)
- `width` and `height` or a named aspect-ratio token
- `label`
- optional `recommendedDimensions`
- optional neutral icon
- optional `variant` (light, green-tint, dark)
- optional `className`/style hook consistent with the project component convention

### Placeholder appearance

- Background: white or pale neutral/green according to the parent surface.
- Border: subtle dashed or solid neutral border; use dashed only to signal a missing asset in editorial/admin contexts, not as a permanent storefront decoration.
- Center: a restrained outline icon or neutral silhouette.
- Text: `Image unavailable` or localized equivalent.
- Dimension hint: `800 × 800 px` (or actual slot size), in small muted text.
- Do not show the dimension hint to ordinary customers in production storefront mode if it would look like technical/debug UI. The dimension-labelled preview is mandatory in design previews, development, content management and missing-asset QA. For live customers, use a polished neutral placeholder without technical labels unless the business explicitly wants dimensions visible.
- Keep the placeholder's exact aspect ratio equal to the target image slot.

Example development preview:

```text
┌────────────────────────────────────┐
│                                    │
│          [Product image icon]      │
│                                    │
│          Product Image             │
│          800 × 800 px              │
│                                    │
└────────────────────────────────────┘
```

Example hero placeholder: 1920 × 600 px; category placeholder: 640 × 420 px; product card placeholder: 800 × 800 px; social preview placeholder: 1200 × 628 px.

## 21.7 Image replacement and freshness rules

The “latest image” means the newest approved image attached to the correct entity/slot in the authoritative media/catalog system, not simply the newest file uploaded anywhere.

When a new image is uploaded:
1. Validate file type, size, dimensions, and content.
2. Associate it with the correct product/category/banner/brand and image role.
3. Generate responsive derivatives.
4. Mark the asset as current/approved according to the media workflow.
5. Invalidate or version the relevant CDN/cache URLs.
6. Refresh the storefront component using the new asset.
7. Preserve the previous approved asset according to the project's media retention policy, so rollback remains possible.

Rules:
- Use stable media IDs and versioned URLs or cache-busting strategy.
- Do not reference local filesystem paths from storefront HTML.
- Do not assume upload order equals approval order.
- A rejected/unapproved image must not replace the current approved asset.
- If a product image is removed, return to the correct placeholder; never retain a stale image from another product.
- Use the latest approved product image as the primary image and additional approved images for the gallery.

## 21.8 Page-by-page image inventory

### Home
- Header logo: 800 × 240 source, contain.
- Hero desktop: 1920 × 600; mobile art direction: 900 × 1100.
- Main category images: 640 × 420.
- Digital Scale subcategory images: 480 × 360.
- Flash Deals and popular product images: 800 × 800.
- Power Tools and Ladder banners: 1200 × 500.
- Track Order illustration: 640 × 420.
- Review avatars: 160 × 160 only if genuine and approved.
- Footer logo: 800 × 240; payment marks: 240 × 120.

### All Products / Category / Search
- Product card: 800 × 800.
- Category hero, when needed: 1600 × 500 (3.2:1) or use the main hero slot.
- Brand logo: 600 × 360.
- Empty-state illustration: 640 × 480.

### Product Details
- Main image: 1400 × 1400.
- Gallery thumbnails: 240 × 240.
- Usage/lifestyle: 1400 × 1050.
- Specification/measurement diagram: 1600 × 1200.
- Variant-specific images must map to the selected variant.
- If no image exists, show a product-specific placeholder with `1400 × 1400 px` in development preview.

### Cart / Checkout
- Compact product thumbnail: 240 × 240 or a generated 320 × 320 derivative.
- Payment provider marks: 240 × 120.
- Do not add decorative imagery that makes checkout slower or distracts from order completion.

### Track Order
- Optional decorative package/truck illustration: 640 × 420.
- Courier logo: 600 × 240 (2.5:1) or preserve official source ratio.
- Do not invent courier branding or tracking-map imagery.
- If a map/route is not available from a verified provider, show the status timeline instead.

### Account / Wishlist / Order History
- Product thumbnail: 240 × 240 or 320 × 320.
- Account avatar: 160 × 160 only when customer supplied it; otherwise use initials/avatar placeholder.
- No generic stock portrait as a substitute for a real customer.

### About / Contact / Editorial
- Team/location/brand imagery: 1200 × 800 (3:2).
- Article thumbnails: 900 × 600.
- Article hero: 1600 × 900.
- Missing editorial images use dimension-labelled placeholders in preview/development.

### Social and campaign assets
- Facebook/LinkedIn/X: 1200 × 628.
- Pinterest: 1000 × 1500.
- Email header: 1200 × 400.
- Do not use the same crop for all platforms if the platform's aspect ratio differs.

## 21.9 Media library and content management requirements

The CMS/media workflow should support:
- Upload and preview.
- Image role/type.
- Alt text (localized where appropriate).
- Caption/description where relevant.
- Focal point/crop position.
- Dimensions and file size.
- Approval/current status.
- Replace image without changing the entity's stable media identity where possible.
- Version history/rollback.
- Clear missing-image report.
- Duplicate/unused asset review.
- Responsive derivative generation status.

For products, media should be linked to the product/variant and image role, such as `primary`, `gallery`, `usage`, `specification`, or `packaging`. Do not infer a variant image from a sibling product.

## 21.10 Image acceptance checklist

Before a page is marked complete, verify:

- [ ] Every image slot has a defined aspect ratio and target dimensions.
- [ ] Correct approved asset appears for the correct entity.
- [ ] Missing asset shows the correct dimension-labelled development placeholder.
- [ ] Live customer placeholder remains polished and does not expose debug labels.
- [ ] Latest approved image replaces the placeholder automatically.
- [ ] No image is stretched or distorted.
- [ ] Product images use the correct `contain`/crop behavior.
- [ ] Hero and banner safe areas survive desktop/tablet/mobile layouts.
- [ ] Responsive derivatives are used.
- [ ] Image dimensions prevent layout shift.
- [ ] Alt text is meaningful and localized where appropriate.
- [ ] Decorative images have empty alt text.
- [ ] Broken/failed images fall back gracefully.
- [ ] Social preview images use the correct platform ratio.
- [ ] No unrelated, stale, fabricated or unapproved images are shown.

---

## 22. Additional implementation rules for image-aware components

### 22.1 Reusable image component

Create or reuse a single shared image component for storefront content. It should:
- accept a media entity/URL from trusted application data;
- know the image slot type and target ratio;
- render the correct responsive source;
- reserve space before load;
- render a placeholder if no approved asset exists;
- switch to placeholder on load failure;
- support alt text and decorative mode;
- avoid exposing internal storage paths;
- optionally report missing assets in development/QA.

Do not build a separate one-off image fallback for every page.

### 22.2 CMS preview vs storefront display

**CMS/design preview:**
- Show placeholder label and exact recommended dimensions.
- Show source/current/approved state where useful.
- Make missing assets obvious to administrators.

**Public storefront:**
- Show the real approved asset when available.
- If unavailable, show a polished neutral placeholder that maintains layout and does not display technical debug copy.
- Never show raw file names, upload IDs, filesystem paths, or admin-only status.

### 22.3 No-image design preview mode

A page must remain visually complete even when the media library is empty. During design development:
- Render every planned image slot at its final ratio.
- Put the slot name and recommended dimensions in the placeholder.
- Preserve the same card/banner geometry as the final design.
- When a real approved image becomes available, swap the source only; do not redesign the layout.
- Maintain separate desktop and mobile art direction where specified.

---

## 23. Changelog update

| Version | Date | Change |
|---|---|---|
| 1.0.0 | 2026-11-15 | Initial approved MADINA storefront design-system source of truth. |
| 1.1.0 | 2026-11-15 | Added complete image system: page-by-page image inventory, recommended pixel dimensions/aspect ratios, social preview sizes based on supplied reference, responsive delivery, labelled missing-image placeholders, latest-approved-image replacement, media library requirements and image QA checklist. |
