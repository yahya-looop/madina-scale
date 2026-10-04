# Madina Scale --- Users/Index.vue Page Design Specification

**Reference implementation:** `resources/js/Pages/Users/Index.vue`\
**Purpose:** This document captures the _actual implemented_ visual
system of the Users Index page so future pages can reproduce the same
visual language without changing the established layout.

> **Golden rule:** Preserve the same visual hierarchy, typography,
> spacing rhythm, surfaces, controls, icon treatment, semantic
> structure, and animation across the authenticated system. Do not
> introduce page-specific visual language unless explicitly required.

---

## 1. Design Direction

The page uses a **premium, modern enterprise admin** style:

- Clean and information-dense.
- Light cool-gray application surface with white content cards.
- Dark navy primary text.
- Restrained green accent.
- Soft borders and very subtle shadows.
- Compact controls and table density.
- Rounded cards and controls.
- Stroke-based UI icons.
- Smooth, restrained entrance animation.
- Semantic HTML rather than a div-only structure.

The page is designed to visually belong beside the **Roles &
Permissions** page.

---

## 2. Application Shell

The page is rendered inside:

```html
<ErpLayout>
    <main id="main-content" class="erp-content">...</main>
</ErpLayout>
```

The page itself does **not** create another application shell.

Required shell elements:

- Persistent sidebar.
- Persistent topbar.
- Main content area.
- Page-specific content inside `.erp-content`.

---

## 3. Page Container / Spacing

The Users page root is:

```css
.users-page {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    padding: 0;
}
```

### Critical rule

The page itself must **not** add large left/right padding.

The spacing is inherited from the shared `.erp-content` layout.

The established project rule is:

```css
.erp-content {
    padding: 16px 16px 28px;
}
```

Therefore:

- Left: **16px**
- Right: **16px**
- Top: **16px**
- Bottom: **28px**

Do not add another page-level `padding: 24px`, `32px`, `40px`, etc.

---

# 4. Page Header

The header uses:

```html
<header class="page-header">...</header>
```

### Layout

```css
.page-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 24px;
    margin-bottom: 22px;
}
```

### Header animation

The header enters with:

```css
animation: users-header-enter 480ms cubic-bezier(0.22, 1, 0.36, 1) 20ms both;
```

---

# 5. Breadcrumb

Breadcrumb appears above the title.

Semantic markup:

```html
<nav class="page-breadcrumb" aria-label="Breadcrumb">
    <span>Identity &amp; Access</span>
    <AppIcon name="chevron-right" />
    <span class="is-current">Users</span>
</nav>
```

### Actual styling

```css
.page-breadcrumb {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-bottom: 9px;

    color: var(--text-muted, #7b8794);
    font-size: 11px;
    font-weight: 600;
    line-height: 1.2;
}
```

Current breadcrumb:

```css
.page-breadcrumb .is-current {
    color: var(--text-secondary, #596573);
}
```

Separator:

```css
.breadcrumb-icon {
    color: var(--border-strong, #cdd4dd);
}
```

### Important

Breadcrumb is intentionally smaller than the H1. It must never visually
compete with the page title.

---

# 6. Page Title + Icon

The title row is:

```html
<div class="page-title-row">
    <div class="page-title-icon" aria-hidden="true">
        <AppIcon name="users" />
    </div>

    <div>
        <h1>User Management</h1>
        <p>
            Manage platform users, account status and assigned roles from one
            workspace.
        </p>
    </div>
</div>
```

### Title row

```css
.page-title-row {
    display: flex;
    align-items: center;
    gap: 12px;
}
```

### Page icon container

Actual implementation:

```css
.page-title-icon {
    display: inline-flex;
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    align-items: center;
    justify-content: center;

    border: 1px solid
        color-mix(in srgb, var(--primary, #1f8f68) 22%, var(--border, #e5e9ee));

    border-radius: 12px;

    color: var(--primary, #1f8f68);

    background: color-mix(
        in srgb,
        var(--primary, #1f8f68) 10%,
        var(--surface, #ffffff)
    );
}
```

### Icon

- Size: **21px**
- Stroke-based.
- Green accent.
- Same application icon family.
- No emoji.
- No Unicode replacement.

---

# 7. H1 Typography

Actual implementation:

```css
.page-title-row h1 {
    margin: 0;
    color: var(--text-primary, #17202a);

    font-size: clamp(24px, 3vw, 30px);
    font-weight: 700;
    line-height: 1.12;
    letter-spacing: -0.035em;
}
```

### Actual Users page hierarchy

Property Value

---

Font family Instrument Sans
Size `clamp(24px, 3vw, 30px)`
Weight 700
Line height 1.12
Letter spacing -0.035em
Color `#17202a` / `var(--text-primary)`

---

# 8. Page Description

```css
.page-title-row p {
    max-width: 620px;
    margin: 7px 0 0;

    color: var(--text-muted, #7b8794);

    font-size: 13px;
    line-height: 1.55;
}
```

Desktop actual size: **13px**.

At mobile width below 560px:

```css
font-size: 12px;
```

---

# 9. Header Action Buttons

Header contains:

- Refresh
- Add user

Semantic markup:

```html
<button type="button" class="btn btn-secondary">
    <AppIcon />
    <span>Refresh</span>
</button>

<button type="button" class="btn btn-primary">
    <AppIcon />
    <span>Add user</span>
</button>
```

### Action group

```css
.header-actions {
    display: flex;
    align-items: center;
    gap: 9px;
}
```

### Button

```css
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;

    min-height: 38px;
    padding: 0 14px;

    border: 1px solid transparent;
    border-radius: 10px;

    font: inherit;
    font-size: 13px;
    font-weight: 700;

    cursor: pointer;

    transition:
        transform 140ms ease,
        border-color 140ms ease,
        background 140ms ease,
        color 140ms ease,
        opacity 140ms ease,
        box-shadow 140ms ease;
}
```

### Hover

```css
.btn:not(:disabled):hover {
    transform: translateY(-1px);
}
```

### Primary

```css
.btn-primary {
    color: #fff;
    background: var(--primary, #1f8f68);
    box-shadow: 0 5px 14px
        color-mix(in srgb, var(--primary, #1f8f68) 18%, transparent);
}
```

### Secondary

```css
.btn-secondary {
    color: var(--text-primary, #17202a);
    border-color: var(--border, #e5e9ee);
    background: var(--surface, #fff);
}
```

---

# 10. Statistics / KPI Cards

The Users page has three KPI cards:

1.  Total users
2.  Active
3.  Inactive

Markup:

```html
<section class="stat-grid" aria-label="User statistics">
    <article class="stat-card">...</article>
    <article class="stat-card">...</article>
    <article class="stat-card">...</article>
</section>
```

### Grid

```css
.stat-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 18px;
}
```

### Card

Actual card dimensions/style:

```css
.stat-card {
    display: flex;
    align-items: center;
    gap: 12px;

    min-height: 82px;
    padding: 16px;

    border: 1px solid var(--border, #e5e9ee);
    border-radius: 14px;

    background: var(--surface, #fff);

    box-shadow: 0 5px 18px rgb(15 23 42 / 3%);
}
```

### KPI icon

```css
.stat-icon {
    width: 38px;
    height: 38px;
    flex: 0 0 38px;

    display: grid;
    place-items: center;

    border-radius: 10px;

    color: var(--text-secondary, #596573);
    background: var(--surface-muted, #f3f5f7);
}
```

Active icon:

```css
color: #19734f;
background: #eaf7f1;
```

Inactive icon:

```css
color: #9a6518;
background: #fff5e4;
```

### KPI label

```css
.stat-card span {
    display: block;
    color: var(--text-muted, #7b8794);
    font-size: 12px;
}
```

### KPI value

```css
.stat-card strong {
    display: block;
    margin-top: 3px;

    color: var(--text-primary, #17202a);
    font-size: 22px;
    line-height: 1.1;
}
```

---

# 11. Main Workspace Card

The user table lives inside:

```html
<section class="workspace-card" aria-labelledby="users-table-title"></section>
```

### Actual styling

```css
.workspace-card {
    overflow: hidden;

    border: 1px solid var(--border, #e5e9ee);
    border-radius: 15px;

    background: var(--surface, #fff);

    box-shadow: 0 8px 28px rgb(15 23 42 / 4%);

    animation: workspace-enter 520ms cubic-bezier(0.22, 1, 0.36, 1) 80ms both;
}
```

This is the primary content surface.

---

# 12. Toolbar

Toolbar contains:

- Search.
- Status filter.
- Clear filters when active.

```css
.toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;

    padding: 15px;

    border-bottom: 1px solid var(--border, #e5e9ee);
}
```

---

# 13. Search Field

Semantic markup:

```html
<label class="search-field">
    <AppIcon name="search" />
    <input type="search" placeholder="Search by name, email or role..." />
</label>
```

### Actual styling

```css
.search-field {
    display: flex;
    align-items: center;
    gap: 9px;

    width: min(470px, 100%);
    height: 39px;
    padding: 0 12px;

    border: 1px solid var(--border, #e5e9ee);
    border-radius: 10px;

    color: var(--text-muted, #7b8794);
    background: var(--surface-muted, #f8fafb);
}
```

Input:

```css
font-size: 13px;
```

Focus:

```css
border-color: color-mix(
    in srgb,
    var(--primary, #1f8f68) 45%,
    var(--border, #e5e9ee)
);

background: var(--surface, #fff);

box-shadow: 0 0 0 3px
    color-mix(in srgb, var(--primary, #1f8f68) 10%, transparent);
```

---

# 14. Status Filter

Actual select:

```css
.toolbar select {
    height: 39px;
    padding: 0 34px 0 11px;

    border: 1px solid var(--border, #e5e9ee);
    border-radius: 10px;

    color: var(--text-primary, #17202a);
    background: var(--surface, #fff);

    font: inherit;
    font-size: 13px;
}
```

Search and filter deliberately use the same control height and radius.

---

# 15. Table Structure

The page uses real table semantics:

```html
<table>
    <thead>
        <tr>
            <th>User</th>
            <th>Status</th>
            <th>Roles</th>
            <th>Last Login</th>
            <th>Created</th>
            <th>Actions</th>
        </tr>
    </thead>

    <tbody>
        ...
    </tbody>
</table>
```

Column order is fixed:

1.  USER
2.  STATUS
3.  ROLES
4.  LAST LOGIN
5.  CREATED
6.  ACTIONS

---

# 16. Table Header

Actual implementation:

```css
th {
    padding: 12px 16px;

    color: var(--text-muted, #7b8794);
    background: var(--surface-muted, #fafbfc);

    font-size: 11px;
    font-weight: 800;

    letter-spacing: 0.06em;

    text-align: left;
    text-transform: uppercase;

    white-space: nowrap;
}
```

This is a deliberately compact enterprise table header.

---

# 17. Table Cells / Rows

Actual:

```css
td {
    padding: 13px 16px;

    border-top: 1px solid var(--border, #eef1f4);

    vertical-align: middle;

    color: var(--text-primary, #17202a);
    font-size: 13px;
}
```

Row hover:

```css
tbody tr:hover {
    background: var(--surface-muted, #fafbfc);
}
```

---

# 18. User Cell

Structure:

```text
[Avatar]  User Name
          email@example.com
```

Semantic interactive element:

```html
<button class="user-cell" type="button">...</button>
```

### Layout

```css
.user-cell {
    display: flex;
    align-items: center;
    gap: 11px;

    padding: 0;
    border: 0;

    background: transparent;
    text-align: left;
}
```

### Avatar

Actual:

```css
.avatar {
    display: grid;
    width: 38px;
    height: 38px;
    flex: 0 0 38px;
    place-items: center;

    border-radius: 11px;

    color: var(--primary, #1f8f68);
    background: var(--primary-soft, #eaf7f1);

    font-size: 12px;
    font-weight: 800;
}
```

### User name

```css
.user-copy strong {
    color: var(--text-primary, #17202a);
    font-weight: 700;
}
```

### Email

```css
.user-copy small {
    color: var(--text-muted, #7b8794);
    font-size: 12px;
}
```

The user name and email are ellipsis-truncated at approximately 280px.

---

# 19. Status Badge

Markup:

```html
<span class="status-badge active">
    <i></i>
    active
</span>
```

### Badge

```css
.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;

    padding: 5px 9px;
    border-radius: 999px;

    font-size: 11px;
    font-weight: 800;

    text-transform: capitalize;
}
```

Dot:

```css
width: 6px;
height: 6px;
border-radius: 50%;
background: currentColor;
```

### Active

```css
color: #17714e;
background: #eaf7f1;
```

### Inactive

```css
color: #9a6518;
background: #fff5e4;
```

---

# 20. Role Chips

Roles are displayed as compact chips.

```css
.roles-cell {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
    min-width: 190px;
}
```

Chip:

```css
.role-chip,
.role-more {
    display: inline-flex;
    align-items: center;

    min-height: 25px;
    padding: 0 8px;

    border: 1px solid var(--border, #e5e9ee);
    border-radius: 7px;

    color: var(--text-secondary, #596573);
    background: var(--surface-muted, #f8fafb);

    font-size: 11px;
    font-weight: 700;
}
```

System role:

```css
.role-chip.system {
    color: #6c4b9e;
    border-color: #dfd3f0;
    background: #f7f2fc;
}
```

Maximum visible roles: **2**, then `+N`.

---

# 21. Last Login Column

This column is mandatory and must never disappear during UI restyling.

Data source:

```ts
user.last_login_at;
```

Optional secondary metadata:

```ts
user.last_login_ip;
```

### Cell

```css
.last-login-cell {
    min-width: 155px;
}
```

### Layout

```css
.last-login {
    display: grid;
    gap: 3px;
}
```

### Login time

```css
.last-login-time {
    color: var(--text-secondary, #596573);
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
}
```

If missing:

```css
.last-login-time.never {
    color: var(--text-muted, #9aa4b2);
    font-weight: 500;
}
```

Display:

```text
Oct 04, 2026, 07:32 PM
```

If genuinely null:

```text
Never
```

The frontend must never fabricate login information.

---

# 22. Last Login IP

```css
.last-login-ip {
    color: var(--text-muted, #8a94a5);
    font-size: 10px;
    line-height: 1.2;
    white-space: nowrap;
}
```

At max-width 1100px:

```css
.last-login-ip {
    display: none;
}
```

---

# 23. Created Column

```css
.date-cell {
    color: var(--text-secondary, #596573);
    white-space: nowrap;
}
```

The page formats the stored `created_at` value using
`Intl.DateTimeFormat`.

---

# 24. Action Buttons

Actions:

- View
- Edit

Buttons are icon-only but accessible:

```html
<button type="button" aria-label="View user" title="View user">
    <AppIcon name="eye" />
</button>
```

### Actual dimensions

```css
.icon-btn {
    display: grid;

    width: 32px;
    height: 32px;

    place-items: center;

    border: 1px solid var(--border, #e5e9ee);
    border-radius: 8px;

    color: var(--text-secondary, #596573);
    background: var(--surface, #fff);
}
```

Hover:

```css
color: var(--primary, #1f8f68);
border-color: color-mix(
    in srgb,
    var(--primary, #1f8f68) 50%,
    var(--border, #e5e9ee)
);
background: var(--surface-muted, #fafbfc);
transform: translateY(-1px);
```

---

# 25. Empty State

The empty state is semantic and centered.

```css
.empty-state {
    display: grid;
    min-height: 250px;

    place-items: center;
    align-content: center;

    padding: 40px 20px;

    text-align: center;
}
```

Icon:

```css
.empty-icon {
    width: 44px;
    height: 44px;
    margin-bottom: 10px;

    display: grid;
    place-items: center;

    border-radius: 12px;

    color: var(--text-muted, #7b8794);
    background: var(--surface-muted, #f3f5f7);
}
```

Text:

- Heading: 14px.
- Supporting text: 12px.
- Clear filters action appears when filters are active.

---

# 26. Footer

```css
.table-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;

    padding: 12px 16px;

    border-top: 1px solid var(--border, #e5e9ee);

    color: var(--text-muted, #7b8794);
    font-size: 12px;
}
```

Example:

```text
Showing 6 of 6 users
```

Developer users additionally see:

```text
Developer access
```

Developer note color:

```css
#6c4b9e
```

---

# 27. Color System

The page uses restrained enterprise colors.

## Primary text

```text
#17202a
```

Variable:

```css
var(--text-primary)
```

Used for:

- H1
- User names
- Important labels
- Table primary text

## Secondary text

```text
#596573
```

Variable:

```css
var(--text-secondary)
```

## Muted text

```text
#7b8794
```

Variable:

```css
var(--text-muted)
```

## Primary accent

```text
#1f8f68
```

Variable:

```css
var(--primary)
```

Used for:

- Primary button
- Page icon
- Active state
- Positive UI
- Hover accent

## Surface

```text
#ffffff
```

Variable:

```css
var(--surface)
```

## Muted surface

Common values:

```text
#f8fafb
#fafbfc
#f3f5f7
```

## Border

```text
#e5e9ee
```

Variable:

```css
var(--border)
```

## Strong border

```text
#cdd4dd
```

Variable:

```css
var(--border-strong)
```

## Active status

```text
foreground: #17714e
background: #eaf7f1
```

## Inactive status

```text
foreground: #9a6518
background: #fff5e4
```

## System role

```text
foreground: #6c4b9e
background: #f7f2fc
border: #dfd3f0
```

---

# 28. Font System

The project uses:

```css
font-family: 'Instrument Sans', sans-serif;
```

No page may introduce another font family.

### Actual Users page sizes

Element Size Weight

---

Breadcrumb 11px 600
H1 24--30px responsive 700
Description 13px 400
Header button 13px 700
KPI label 12px 400
KPI value 22px default strong
Search 13px inherited
Filter 13px inherited
Table header 11px 800
Table body 13px regular
User name inherited, 700 700
Email 12px regular
Status badge 11px 800
Role chip 11px 700
Last login 12px 600
Last-login IP 10px regular
Footer 12px regular

> **Important:** Future pages should use the established project's
> typography system rather than inventing page-specific font sizes.

---

# 29. Border Radius System

Actual Users page uses:

Component Radius

---

Page icon 12px
Header buttons 10px
KPI card 14px
KPI icon 10px
Workspace card 15px
Search 10px
Filter 10px
Avatar 11px
Status badge 999px
Role chip 7px
Action button 8px
Empty icon 12px

---

# 30. Shadow System

Shadows are deliberately subtle.

KPI:

```css
0 5px 18px rgb(15 23 42 / 3%)
```

Workspace:

```css
0 8px 28px rgb(15 23 42 / 4%)
```

Primary button:

```css
0 5px 14px
color-mix(in srgb, var(--primary) 18%, transparent)
```

Do not use heavy black shadows.

---

# 31. Icon System

The page currently uses a self-contained SVG icon renderer because
several shared icon names previously failed to resolve reliably.

Icons are:

- SVG.
- Stroke based.
- `viewBox="0 0 24 24"`.
- `fill="none"`.
- `stroke="currentColor"`.
- `stroke-linecap="round"`.
- `stroke-linejoin="round"`.
- Default stroke width around **1.8**.

Used icons include:

```text
users
refresh
plus
check
lock
search
close
eye
edit
chevron-right
developer
```

### Permanent rule

UI icons must never be represented with:

```text
○
•
□
+
emoji
```

Use the established icon component/library.

---

# 32. Semantic HTML

The page correctly uses:

```html
<header>
    <nav>
        <section>
            <article>
                <h1>
                    <h2>
                        <p>
                            <button>
                                <label>
                                    <input />
                                    <select>
                                        <table>
                                            <thead></thead>
                                            <tbody>
                                                <tr>
                                                    <th></th>
                                                    <td>
                                                        <footer></footer>
                                                    </td>
                                                </tr>
                                            </tbody>
                                        </table></select
                                ></label>
                            </button>
                        </p>
                    </h2>
                </h1>
            </article>
        </section>
    </nav>
</header>
```

This structure must be preserved.

---

# 33. Accessibility

The page provides:

- `aria-label="Breadcrumb"`.
- `aria-label="User statistics"`.
- `aria-labelledby` for the main workspace.
- Screen-reader-only table heading.
- Proper `<label>` around search.
- Explicit label for status filter.
- `aria-label` and `title` on icon-only action buttons.
- `focus-visible` outlines.
- Disabled states.
- Keyboard-friendly buttons.

Focus style:

```css
outline: 2px solid color-mix(in srgb, var(--primary, #1f8f68) 65%, transparent);

outline-offset: 2px;
```

---

# 34. Animation System

The page has three entrance animations.

## Root

```css
@keyframes users-page-enter {
    from {
        opacity: 0;
        transform: translateY(8px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}
```

Duration:

```text
500ms
```

Easing:

```text
cubic-bezier(0.22, 1, 0.36, 1)
```

## Header

```text
480ms
20ms delay
translateY(7px)
```

## Workspace

```text
520ms
80ms delay
translateY(9px)
```

The animation should feel:

- Smooth.
- Subtle.
- Premium.
- Fast enough for an admin system.
- Never distracting.

Future pages should reuse this animation language.

---

# 35. Responsive Behavior

## \<= 1100px

- Last-login minimum width becomes 125px.
- Last-login IP is hidden.

## \<= 900px

- Header becomes vertical.
- Header actions become full width.
- KPI grid becomes one column.
- Toolbar becomes vertical.
- Search becomes full width.
- Toolbar actions distribute horizontally.

## \<= 720px

- Page title icon becomes 38×38px.
- H1 becomes 24px.
- Header buttons become full width.
- Header actions stack vertically.
- Toolbar actions stack vertically.
- Status select becomes full width.
- Footer stacks vertically.

## \<= 560px

- Header gap: 18px.
- Header bottom margin: 18px.
- Description: 12px.
- KPI card minimum height: 76px.
- KPI padding: 14px.
- Table horizontal padding becomes 12px.

---

# 36. Data / Functional Elements That Must Not Be Removed

Visual refactoring must preserve:

- Total users.
- Active users.
- Inactive users.
- Search.
- Status filter.
- Clear filters.
- Refresh.
- Add user.
- View user.
- Edit user.
- Roles.
- Status.
- Last Login.
- Last Login IP where available.
- Created date.
- Developer access indicator.
- Empty state.
- API refresh.
- Backend capability checks.

---

# 37. Last Login Data Flow

The page expects:

```text
Successful login
    ↓
AuthenticationService
    ↓
AccountSecurityService
    ↓
last_login_at + last_login_ip
    ↓
Database
    ↓
UserController
    ↓
/api/users
    ↓
Users/Index.vue
```

Frontend:

```ts
user.last_login_at;
```

Display:

```text
Oct 04, 2026, 07:32 PM
```

If truly null:

```text
Never
```

The frontend must never manufacture a date.

---

# 38. Search Behavior

Search indexes:

- User name.
- Email.
- Assigned role names.

The query is normalized:

```ts
search.value.trim().toLowerCase();
```

The searchable content is joined and compared case-insensitively.

---

# 39. Page Blueprint

Future management pages should follow this structure:

```text
ERP Layout
│
├── Sidebar
│
├── Topbar
│
└── Main
    │
    └── erp-content
        │
        └── Page
            │
            ├── Breadcrumb
            │
            ├── Page Header
            │   ├── Page Icon
            │   ├── H1
            │   ├── Description
            │   └── Actions
            │
            ├── KPI / Summary
            │
            └── Main Workspace Card
                ├── Search / Filter Toolbar
                ├── Data Table / Content
                └── Footer / Metadata
```

---

# 40. Universal Rules for Future Pages

Every new page must:

1.  Use the same ERP shell.
2.  Use Instrument Sans.
3.  Respect `.erp-content` spacing.
4.  Avoid extra page-level horizontal padding.
5.  Put breadcrumb above H1.
6.  Put module icon before H1.
7.  Use the same heading hierarchy.
8.  Use the same button system.
9.  Use the same input/filter system.
10. Use the same card surfaces.
11. Use the same border language.
12. Use the same radius language.
13. Use the same icon family.
14. Use semantic HTML.
15. Preserve keyboard accessibility.
16. Use restrained animation.
17. Preserve existing data functionality when restyling.
18. Keep table semantics where tabular data is used.
19. Never replace real icons with text characters.
20. Never introduce arbitrary colors or fonts.

---

# 41. Design Validation Checklist

Before accepting a new page:

### Layout

- [ ] Same ERP shell.
- [ ] Same `.erp-content` boundaries.
- [ ] 16px left/right content spacing.
- [ ] 16px top / 28px bottom shell spacing.
- [ ] No oversized page padding.

### Header

- [ ] Breadcrumb above title.
- [ ] Module icon before H1.
- [ ] H1 matches Users/Roles visual hierarchy.
- [ ] Description uses the same muted style.
- [ ] Actions align with the header.

### Typography

- [ ] Instrument Sans.
- [ ] No arbitrary font family.
- [ ] Hierarchy matches the system.
- [ ] Weight usage is consistent.

### Components

- [ ] Buttons match.
- [ ] Inputs match.
- [ ] Filters match.
- [ ] Cards match.
- [ ] Badges match.
- [ ] Icons render correctly.

### Data

- [ ] Existing functionality preserved.
- [ ] Required columns preserved.
- [ ] Last Login preserved where applicable.
- [ ] API contract preserved.
- [ ] Null values handled honestly.

### Accessibility

- [ ] Semantic HTML.
- [ ] Keyboard support.
- [ ] Focus states.
- [ ] Icon-only labels.
- [ ] Table semantics.
- [ ] Form labels.

### Validation

```bash
npm run types:check
php artisan optimize:clear
npm run build
```

All three should pass before the page is considered complete.

---

# 42. Golden Rule

> **Madina Scale must look like one system, not a collection of
> independently designed pages.**

The Users Index page is the concrete reference for compact table-heavy
management screens.

The Roles & Permissions page remains the primary visual reference for
the overall authenticated page hierarchy.

When uncertain:

**reuse the existing system instead of inventing a new one.**
