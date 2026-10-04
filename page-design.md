# Madina Scale --- Universal Page Design System

**File:** `page-design.md`\
**Purpose:** This document defines the universal visual, spacing,
typography, component, layout, interaction, and semantic rules for every
authenticated page in the Madina Scale system.

> **Design principle:** Every page must feel like it belongs to the same
> product. A new module may introduce different data or components, but
> it must not introduce a different visual language.

------------------------------------------------------------------------

## 1. Design Direction

Madina Scale uses a **premium modern enterprise admin** visual language.

The design must be:

-   Clean
-   Spacious, but not over-padded
-   Information-dense without feeling crowded
-   Consistent across every module
-   Accessible and keyboard-friendly
-   Responsive
-   Production-ready
-   Easy to extend
-   Semantically structured
-   Visually aligned with the existing **Roles & Permissions** page

### Reference page

The **Roles & Permissions** page is the primary visual reference for
authenticated management pages.

The **Users / User Management** page must follow the same:

-   page shell
-   content width
-   breadcrumb treatment
-   heading hierarchy
-   title + icon composition
-   typography
-   spacing rhythm
-   card treatment
-   button treatment
-   border radius
-   border color
-   background color
-   table styling
-   status badges
-   search controls
-   action controls
-   responsive behavior

------------------------------------------------------------------------

# 2. Global Application Shell

## 2.1 Layout

The application uses a persistent ERP shell:

``` text
┌──────────────────────────────────────────────────────────────┐
│ Sidebar │ Topbar                                             │
│         ├─────────────────────────────────────────────────────┤
│         │                                                     │
│         │ Main page content                                  │
│         │                                                     │
│         │                                                     │
└─────────┴─────────────────────────────────────────────────────┘
```

### Sidebar

-   Fixed/sticky left navigation.
-   Dark navy background.
-   Primary navigation icons are always visible.
-   Navigation labels use the same font family as the application.
-   Active navigation item uses the system accent treatment.
-   Do not redesign the sidebar on individual pages.

### Topbar

-   Fixed/sticky within the main application shell.
-   Light background.
-   Search field on the left.
-   Utility controls on the right.
-   Theme selector.
-   User profile control.
-   Consistent height across all authenticated pages.

### Main content

All page-specific content lives inside:

``` html
<main id="main-content" class="erp-content">
```

Do not create another application shell inside an individual page.

------------------------------------------------------------------------

# 3. Universal Page Container

## 3.1 Content width

The page content must use the same horizontal alignment as the Roles &
Permissions page.

### Required rule

Do **not** add excessive left/right padding inside individual pages.

The page should visually align with:

-   topbar content
-   page heading
-   KPI cards
-   search/filter card
-   data table
-   bottom metadata

### Recommended page padding

``` text
Desktop:
left/right: 20px–24px
top: 18px–24px
bottom: 24px–32px
```

The exact value should be inherited from the shared page/layout system
where possible.

### Forbidden

Do not create:

``` css
padding-left: 60px;
padding-right: 60px;
```

or other page-specific oversized horizontal spacing unless the reference
design explicitly requires it.

------------------------------------------------------------------------

# 4. Page Header System

Every major management page must use the same page-header structure.

## 4.1 Required hierarchy

``` text
Breadcrumb
    ↓
Page title row
    ↓
Description
```

For pages that use a leading module icon:

``` text
Breadcrumb

┌──────────┐  Page Title
│  Icon    │  Description
└──────────┘
```

## 4.2 Header structure

Recommended semantic structure:

``` html
<header class="page-header">
    <nav aria-label="Breadcrumb">
        ...
    </nav>

    <div class="page-header-main">
        <div class="page-header-icon" aria-hidden="true">
            ...
        </div>

        <div>
            <h1>Page Title</h1>
            <p>Page description.</p>
        </div>

        <div class="page-header-actions">
            ...
        </div>
    </div>
</header>
```

## 4.3 Breadcrumb

Breadcrumb must appear **above the page title**.

Example:

``` text
Identity & Access  ›  Users
```

### Breadcrumb styling

-   Font size: **13px--14px**
-   Weight: **500**
-   Color: muted slate/blue-gray
-   Current segment: darker text
-   Separator: subtle gray
-   No oversized breadcrumb text
-   Breadcrumb must not compete with the H1

### Semantic element

Use:

``` html
<nav aria-label="Breadcrumb">
```

with an ordered list where appropriate.

------------------------------------------------------------------------

# 5. Page Title

## 5.1 H1

The page title is the primary visual anchor.

Recommended:

``` text
Font family: Instrument Sans
Font size: 36px
Font weight: 500–600
Line height: 1.1–1.2
Letter spacing: approximately -0.02em
Color: primary dark navy
```

The existing **User Management** and **Roles & Permissions** headings
demonstrate this visual hierarchy.

### Important

Do not use a different font family or arbitrary font size for another
page.

------------------------------------------------------------------------

# 6. Page Header Icon

A page-level icon must appear before the page title on management pages.

Example:

``` text
┌─────────┐
│   👥    │  User Management
└─────────┘  Manage platform users...
```

## Icon container

Recommended:

``` text
Size: approximately 50px × 50px
Border radius: 14px–16px
Background: very light accent/green tint
Border: subtle accent border
```

## Icon

-   Size: approximately **22px--24px**
-   Stroke-based icon
-   Same icon family used throughout the application
-   Consistent stroke width
-   Never replace a defined icon with a random Unicode symbol

### Critical rule

Icons must be rendered through the project's established icon system.

Do **not** use:

``` html
<span>○</span>
```

as a substitute for a real icon.

Do not use text glyphs as icon replacements.

------------------------------------------------------------------------

# 7. Page Header Description

The subtitle explains the page purpose.

Recommended:

``` text
Font family: Instrument Sans
Font size: 16px
Font weight: 400
Line height: 1.5
Color: muted slate
```

Example:

> Manage platform users, account status and assigned roles from one
> workspace.

Keep descriptions concise.

------------------------------------------------------------------------

# 8. Header Action Buttons

Actions such as:

-   Refresh
-   Add user
-   Create role
-   Export

must use the shared button system.

## Primary button

Example:

``` text
+ Add user
```

Recommended:

``` text
Height: 44px–48px
Horizontal padding: 18px–20px
Border radius: 12px
Font size: 14px–15px
Font weight: 600
```

Primary action:

-   Uses the system green/accent color.
-   White text.
-   Has a subtle hover state.
-   Icon is placed before the label.

## Secondary button

Example:

``` text
↻ Refresh
```

Recommended:

``` text
Height: 44px–48px
Border: 1px solid #E2E8F0-ish
Background: white
Text: primary dark
Border radius: 12px
```

### Icon rule

Buttons must use actual icons.

Refresh must display a refresh icon, not a circle glyph.

------------------------------------------------------------------------

# 9. Spacing System

Madina Scale should use a consistent spacing scale.

Recommended base:

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
```

## Page-level rhythm

Typical hierarchy:

``` text
Topbar
↓ 18–24px
Breadcrumb
↓ 12–16px
Title row
↓ 24–32px
KPI section
↓ 20–24px
Main data card
```

Do not introduce random spacing such as:

``` text
17px
29px
37px
63px
```

unless there is a deliberate component-level reason.

------------------------------------------------------------------------

# 10. KPI Cards

Management pages may use KPI cards beneath the page header.

The Users page uses:

-   Total users
-   Active
-   Inactive

The Roles & Permissions page uses:

-   Total Roles
-   Active Roles
-   Permissions
-   Coverage

## Card structure

``` text
┌───────────────────────────────────────────┐
│  Icon   Label                             │
│          Value                            │
│          Optional supporting text         │
└───────────────────────────────────────────┘
```

## KPI card rules

-   Background: white
-   Border: subtle neutral border
-   Radius: approximately 16px
-   Soft shadow
-   Internal padding: approximately 18px--20px
-   Height: visually consistent across pages

### Label

``` text
Font size: 13px–14px
Weight: 400–500
Color: muted
```

### Value

``` text
Font size: 26px–30px
Weight: 600
Color: primary
```

### Supporting text

``` text
Font size: 12px–13px
Weight: 400
Color: muted
```

------------------------------------------------------------------------

# 11. Icon Containers

All semantic icons should follow a shared visual language.

### General icon sizes

``` text
Navigation icon: 18px–20px
Button icon: 16px–18px
Table/action icon: 17px–18px
Page icon: 22px–24px
KPI icon: 20px–22px
```

### Never

-   Use different icon libraries on different pages without a reason.
-   Use emoji as UI icons.
-   Use Unicode circles/squares as icon replacements.
-   Change icon stroke style from one page to another.

------------------------------------------------------------------------

# 12. Main Data Card

The Users table and Roles & Permissions workspace both use a large white
content card.

## Card

``` text
Background: white
Border: subtle neutral
Border radius: 16px–18px
Overflow: hidden where necessary
```

The card should contain:

1.  Search/filter toolbar
2.  Table header
3.  Table rows
4.  Footer/meta information

------------------------------------------------------------------------

# 13. Search & Filter Toolbar

## Layout

``` text
┌──────────────────────────────────────────────────────────┐
│ Search field                              Status filter  │
└──────────────────────────────────────────────────────────┘
```

### Search input

Recommended:

``` text
Height: 48px
Border radius: 12px
Padding: 0 16px
Font size: 14px–15px
```

Placeholder:

-   muted
-   not overly dark
-   concise

### Filter

Status dropdown should have the same height and radius as the search
field.

Do not use a visually different dropdown on another page.

------------------------------------------------------------------------

# 14. Table Design

## Column hierarchy

The Users page currently follows:

``` text
USER
STATUS
ROLES
LAST LOGIN
CREATED
ACTIONS
```

This column structure must remain stable unless the business requirement
changes.

## Table header

Recommended:

``` text
Font size: 12px–13px
Font weight: 600
Text transform: uppercase
Letter spacing: approximately 0.02em–0.04em
Color: muted slate
```

## Table row

Recommended:

``` text
Min height: approximately 72px–80px
Border bottom: subtle
Vertical alignment: middle
```

Rows must have enough space for:

-   avatar
-   user name
-   email
-   status badge
-   role badge
-   last login
-   creation date
-   action buttons

------------------------------------------------------------------------

# 15. User Cell

The user column contains:

``` text
[Avatar]  User Name
          email@example.com
```

## User name

``` text
Font size: 15px–16px
Weight: 600
Color: primary
```

## Email

``` text
Font size: 13px–14px
Weight: 400
Color: muted
```

## Avatar

Recommended:

``` text
Size: 44px–48px
Border radius: 12px–14px
Background: subtle green/accent tint
```

Initials should be derived from the user's name.

------------------------------------------------------------------------

# 16. Status Badges

Examples:

``` text
● Active
● Inactive
```

## Badge

Recommended:

``` text
Height: 28px–32px
Padding: 0 10px–12px
Border radius: 999px
Font size: 12px–13px
Font weight: 600
```

### Active

-   Green-tinted background
-   Green text
-   Small green status dot

### Inactive

-   Amber/orange-tinted background
-   Amber/orange text
-   Small amber status dot

The semantic meaning must remain consistent throughout the application.

------------------------------------------------------------------------

# 17. Role Badges

Example:

``` text
Manager
Developer
```

Recommended:

``` text
Background: very light neutral/blue-gray
Border: subtle
Border radius: 8px–10px
Font size: 12px–13px
Font weight: 500–600
Padding: 5px–9px
```

System roles may use the project's special system-role treatment.

------------------------------------------------------------------------

# 18. Last Login

The `LAST LOGIN` column is a first-class data column and must never
disappear merely because the UI is being restyled.

## Data source

The UI should read:

``` text
user.last_login_at
```

from the authenticated user's persisted login metadata.

## Display rule

If login data exists:

``` text
Oct 04, 2026, 07:32 PM
```

or the project's approved relative/absolute date format.

If no login exists:

``` text
Never
```

### Important

The frontend must not fabricate last-login data.

The backend must persist it during successful authentication.

The value is expected to be updated by the authentication flow:

``` text
successful login
    ↓
AuthenticationService
    ↓
AccountSecurityService
    ↓
last_login_at = current time
last_login_ip = request IP
    ↓
database
    ↓
UserController payload
    ↓
Users/Index.vue
```

------------------------------------------------------------------------

# 19. Created Date

The Created column should use the application's standard date format.

Example:

``` text
Oct 01, 2026
```

Recommended:

``` text
Font size: 14px–15px
Weight: 400–500
Color: muted/secondary
```

------------------------------------------------------------------------

# 20. Action Buttons

The Actions column should contain compact icon buttons.

Typical actions:

``` text
View
Edit
```

## Action button

Recommended:

``` text
Width: 40px–42px
Height: 40px–42px
Border radius: 10px–12px
Border: 1px solid neutral
Background: white
```

### Critical rule

The actual icons must always render.

Use the project's established icon components/library.

Do not replace icons with:

``` text
○
•
□
```

or text characters.

------------------------------------------------------------------------

# 21. Footer

The data card footer may display:

``` text
Showing 6 of 6 users
```

Recommended:

``` text
Font size: 13px–14px
Color: muted
```

Right-side contextual controls may appear when appropriate, e.g.:

``` text
Developer access
```

------------------------------------------------------------------------

# 22. Color System

The visual system is based on a restrained enterprise palette.

## Primary

Use the application's established dark navy for:

-   H1
-   primary text
-   important labels
-   navigation

## Accent

Use the application's green accent for:

-   primary buttons
-   active navigation
-   success states
-   page icon accents
-   positive status indicators

## Neutral

Use blue-gray/slate tones for:

-   secondary text
-   descriptions
-   metadata
-   table headers
-   placeholders

## Surface

Use:

-   very light cool-gray page background
-   white cards
-   subtle neutral borders

## Status colors

``` text
Success → green
Warning/Inactive → amber
Error → red
Info → blue
```

Do not introduce arbitrary colors per page.

------------------------------------------------------------------------

# 23. Border System

Use a consistent subtle border.

Recommended visual:

``` text
1px solid rgba(148, 163, 184, 0.18–0.28)
```

Borders should separate surfaces without becoming visually heavy.

------------------------------------------------------------------------

# 24. Border Radius System

Use a predictable radius hierarchy.

``` text
Small controls:     8px–10px
Inputs:             10px–12px
Buttons:            10px–12px
Badges:             999px
Cards:              16px–18px
Page icon:          14px–16px
Avatar:             12px–14px
```

Do not use random radii on equivalent components.

------------------------------------------------------------------------

# 25. Shadow System

Shadows should be subtle.

Use them primarily for:

-   cards
-   dropdowns
-   floating elements
-   modal surfaces

Avoid strong black shadows.

The design should look premium through:

-   spacing
-   hierarchy
-   borders
-   typography
-   restrained shadows

---not through heavy effects.

------------------------------------------------------------------------

# 26. Typography System

## Font family

The project uses **Instrument Sans**.

This must be the universal application font unless a specific technical
requirement says otherwise.

``` css
font-family: 'Instrument Sans', sans-serif;
```

## Primary weights

Use:

``` text
400 — regular
500 — medium
600 — semibold
```

Avoid unnecessary use of:

``` text
700+
```

unless the design system explicitly requires it.

------------------------------------------------------------------------

# 27. Typography Scale

  Element                      Size     Weight
  ---------------------- ---------- ----------
  Page H1                  34--38px   500--600
  Page description         15--16px        400
  Breadcrumb               13--14px        500
  Section title            18--20px   500--600
  Card label               13--14px   400--500
  KPI value                26--30px        600
  Table header             12--13px        600
  Table primary text       15--16px   500--600
  Table secondary text     13--14px        400
  Button                   14--15px        600
  Input                    14--15px   400--500
  Badge                    12--13px   500--600
  Footer/meta              13--14px   400--500

------------------------------------------------------------------------

# 28. Semantic HTML Rules

Every page must use semantic HTML.

### Required where appropriate

``` html
<header>
<nav>
<main>
<section>
<article>
<h1>
<h2>
<h3>
<table>
<thead>
<tbody>
<tr>
<th>
<td>
<form>
<label>
<button>
```

### Do not

Build an entire page using meaningless:

``` html
<div>
    <div>
        <div>
            ...
        </div>
    </div>
</div>
```

Use semantic structure whenever the element has a semantic equivalent.

------------------------------------------------------------------------

# 29. Accessibility Rules

Every page must support:

-   keyboard navigation
-   visible focus states
-   screen-reader labels
-   semantic buttons
-   semantic headings
-   accessible form labels
-   sufficient color contrast
-   meaningful `aria-label` values for icon-only buttons

Example:

``` html
<button
    type="button"
    aria-label="View user"
>
    ...
</button>
```

Never make an icon-only button accessible only through its visual
appearance.

------------------------------------------------------------------------

# 30. Responsive Rules

The page must work on:

-   desktop
-   laptop
-   tablet
-   mobile

### Desktop

Use the full multi-column layout.

### Tablet

Reduce:

-   horizontal gaps
-   card grid columns
-   table density where necessary

### Mobile

Stack:

``` text
Breadcrumb
Title
Description
Actions
KPI cards
Search/filter
Data
```

Do not allow the page to become horizontally broken.

For very wide tables, use a controlled horizontal scroll container
rather than breaking table semantics.

------------------------------------------------------------------------

# 31. Loading State

Loading states must preserve layout dimensions.

Do not cause large layout jumps.

Use skeletons or appropriate loading indicators that match the same:

-   radius
-   spacing
-   typography
-   surface colors

------------------------------------------------------------------------

# 32. Empty State

Empty states must use the same page language.

Example:

``` text
[Icon]

No users found

There are no users matching the current filters.
```

Include an appropriate action when useful.

------------------------------------------------------------------------

# 33. Error State

Errors must be:

-   visible
-   concise
-   actionable
-   semantically associated with the failed operation

Do not rely only on color.

------------------------------------------------------------------------

# 34. Interaction Rules

Every interactive element needs:

``` text
Default
Hover
Focus
Active
Disabled
Loading
```

states where applicable.

Transitions should be subtle.

Recommended:

``` css
transition: 150ms–200ms ease;
```

Do not use excessive animations.

------------------------------------------------------------------------

# 35. Page Consistency Rules

When creating a new page, developers must not independently decide:

-   a new font
-   a new card radius
-   a new button style
-   a new input style
-   a new spacing system
-   a new page heading structure
-   a new icon style
-   a new color palette

Instead, reuse the universal design system.

------------------------------------------------------------------------

# 36. Page Blueprint

A standard management page should follow this structure:

``` text
ERP Layout
│
├── Sidebar
│
├── Topbar
│
└── Main
    │
    └── Page Container
        │
        ├── Breadcrumb
        │
        ├── Page Header
        │   ├── Page Icon
        │   ├── H1
        │   ├── Description
        │   └── Actions
        │
        ├── KPI / Summary Cards
        │
        └── Main Content Card
            ├── Search / Filters
            ├── Table / Content
            └── Footer / Pagination / Metadata
```

------------------------------------------------------------------------

# 37. Users Page Reference Blueprint

The Users page specifically follows:

``` text
Identity & Access › Users

[Users Icon]  User Management
              Manage platform users, account status and assigned roles from one workspace.

                                  [Refresh] [ + Add user ]

┌──────────────────┐ ┌──────────────────┐ ┌──────────────────┐
│ Total users      │ │ Active           │ │ Inactive         │
│ 6                │ │ 5                │ │ 1                │
└──────────────────┘ └──────────────────┘ └──────────────────┘

┌─────────────────────────────────────────────────────────────┐
│ Search                                  All statuses        │
├─────────────────────────────────────────────────────────────┤
│ USER       STATUS       ROLES       LAST LOGIN  CREATED ... │
│                                                             │
│ User       Active       Manager     date        date   ... │
│ User       Active       Developer   date        date   ... │
│ User       Inactive     No role     Never       date   ... │
├─────────────────────────────────────────────────────────────┤
│ Showing X of Y users                         Developer ...  │
└─────────────────────────────────────────────────────────────┘
```

This blueprint must remain visually consistent with the Roles &
Permissions page.

------------------------------------------------------------------------

# 38. Roles & Permissions as the Primary Reference

When there is uncertainty about page design, compare the new page
against the Roles & Permissions page.

The following must match:

1.  Header alignment
2.  Breadcrumb position
3.  Page icon treatment
4.  H1 typography
5.  Description typography
6.  Action button height
7.  Action button radius
8.  KPI card dimensions
9.  Search field dimensions
10. Filter dimensions
11. Card radius
12. Table header treatment
13. Row spacing
14. Status badges
15. Icon style
16. Color usage
17. Border treatment
18. Overall density
19. Responsive behavior
20. Horizontal content boundaries

------------------------------------------------------------------------

# 39. Implementation Rule --- Preserve Existing Functionality

When updating an existing page, **visual changes must not remove working
functionality**.

For example, updating Users/Index.vue must not accidentally remove:

-   Last Login column
-   Created column
-   Roles
-   Status
-   Search
-   Status filter
-   Refresh
-   Add user
-   View action
-   Edit action
-   User count
-   Active count
-   Inactive count
-   Developer access
-   API data mapping

The correct approach is:

``` text
Existing functionality
        +
Universal design system
        =
Updated page
```

Not:

``` text
New visual design
        →
rewrite page
        →
accidentally remove functionality
```

------------------------------------------------------------------------

# 40. Backend / Frontend Data Contract

For user-management pages, the backend should expose:

``` text
id
name
email
status
last_login_at
last_login_ip
failed_login_attempts
locked_until
password_changed_at
email_verified_at
created_at
updated_at
roles[]
```

The frontend should map these values without silently renaming or
dropping required fields.

------------------------------------------------------------------------

# 41. Last Login Data Contract

Successful authentication must update:

``` php
$user->last_login_at = Carbon::now();
$user->last_login_ip = $ipAddress;
```

The Users API must serialize:

``` php
'last_login_at' => $user->last_login_at?->toISOString(),
'last_login_ip' => $user->last_login_ip,
```

The frontend must consume:

``` ts
user.last_login_at
```

and render:

``` text
Never
```

only when the value is genuinely `null`.

------------------------------------------------------------------------

# 42. Code Quality Rules

All page code must be:

-   TypeScript-safe
-   Vue 3 compatible
-   Composition API based
-   semantically structured
-   reusable where appropriate
-   free of dead code
-   free of duplicated styling when a shared component exists

Run:

``` bash
npm run types:check
```

Then:

``` bash
php artisan optimize:clear
```

Then:

``` bash
npm run build
```

All three should complete successfully before considering a UI change
complete.

------------------------------------------------------------------------

# 43. Change-Safety Rule

When a user requests a small UI change:

> Change only what was requested.

Do not simultaneously redesign:

-   table columns
-   spacing
-   data mapping
-   backend contracts
-   icon system
-   navigation
-   unrelated components

unless the requested change explicitly requires it.

------------------------------------------------------------------------

# 44. Final Acceptance Checklist

Before marking any page as complete:

### Layout

-   [ ] Same ERP shell
-   [ ] Same content boundaries
-   [ ] Same horizontal spacing
-   [ ] Same vertical rhythm
-   [ ] No excessive page padding

### Header

-   [ ] Breadcrumb exists
-   [ ] Page icon exists
-   [ ] H1 hierarchy matches
-   [ ] Description matches
-   [ ] Actions align correctly

### Typography

-   [ ] Instrument Sans
-   [ ] Correct size hierarchy
-   [ ] Correct weights
-   [ ] No random fonts

### Components

-   [ ] Buttons match universal style
-   [ ] Inputs match universal style
-   [ ] Cards match universal style
-   [ ] Badges match universal style
-   [ ] Icons render correctly

### Data

-   [ ] Existing columns preserved
-   [ ] Last Login preserved
-   [ ] Created preserved
-   [ ] Roles preserved
-   [ ] Status preserved
-   [ ] API data correctly mapped

### Accessibility

-   [ ] Semantic HTML
-   [ ] Keyboard support
-   [ ] Focus states
-   [ ] Icon button labels
-   [ ] Proper table semantics

### Validation

-   [ ] `npm run types:check`
-   [ ] `php artisan optimize:clear`
-   [ ] `npm run build`

------------------------------------------------------------------------

# 45. Golden Rule

> **Madina Scale must look like one system, not a collection of
> independently designed pages.**

Every new page should be able to sit next to the Roles & Permissions
page and look as if both were designed and implemented as part of the
same design system.

**Roles & Permissions = visual reference.**\
**This document = universal implementation rule.**
