# Madina Scale — Page Design & Layout Standard

> **Purpose:** This document is the reusable UI specification for new pages and for restyling existing pages in Madina Scale. Use it as the single reference for typography, spacing, layout, color, responsive behavior, and semantic HTML.
>
> **Reference files reviewed:** `resources/js/pages/Developer/Modules.vue` and `resources/js/pages/Settings/System.vue`.
>
> **Important distinction:** Values below are based on the supplied Vue/CSS source where noted. Where the two pages differ, the document defines a recommended canonical standard so future pages converge on one design system. The exact global font family cannot be confirmed from these page files alone because the main content inherits typography from `ErpLayout`/global CSS.

---

## 1. Design direction

Madina Scale uses a compact, professional ERP/admin dashboard style:

- **Visual character:** clean, restrained, data-dense, card-based, operational rather than decorative.
- **Primary structure:** page header → optional operation message → optional KPI/stat cards → main content workspace → optional note/footer → dialogs when required.
- **Surfaces:** white/light or theme-aware panels, subtle borders, modest shadows, rounded corners.
- **Density:** compact controls and small supporting text; maintain legibility and avoid making critical text smaller than necessary.
- **Consistency:** use the same shared layout, heading structure, control heights, spacing scale, status badges, and focus/hover behavior on every page.
- **Theme:** use design tokens/CSS variables instead of hard-coded colors wherever possible.

## 2. Typography

### 2.1 Font family

The reviewed page components do **not** declare a base `font-family` for all page text. They inherit it from `ErpLayout` or global CSS. Therefore:

1. Do not introduce a new font family inside individual page components.
2. Keep the system-wide font stack defined in one global location.
3. Use `font-family: inherit` for form controls and ordinary UI text.
4. Use a monospace stack only for technical identifiers such as slugs, keys, IDs, or code-like values.

Monospace stack observed in the source:

```css
font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
```

**Action to confirm later:** inspect `ErpLayout.vue`, global CSS, and the app entry stylesheet to record the exact inherited font family here. Do not guess it.

### 2.2 Canonical type scale

The Modules page uses a compact scale with observed sizes from about `8px` to `23px`. The table below keeps that visual language while defining a consistent recommended scale for all new pages.

| UI element                |         Canonical size |                    Weight | Notes                                                      |
| ------------------------- | ---------------------: | ------------------------: | ---------------------------------------------------------- |
| Main page title (`h1`)    | `23px` (mobile `20px`) |                     `750` | One per page; compact enterprise heading                   |
| Main panel heading (`h2`) |              `15–16px` |                 `700–750` | Content or workspace title                                 |
| Subsection/card heading   |              `12–14px` |                     `700` | Avoid using heading tags just for bold styling             |
| KPI/stat value            |              `17–21px` |                     `750` | Use tabular numerals where appropriate                     |
| KPI/stat label            |               `9–11px` |                 `600–650` | Secondary to the value                                     |
| Breadcrumb                |                 `11px` | `600`; current item `700` | Current page should be distinguishable                     |
| Page description          |                 `12px` |                     `400` | Line-height around `1.5–1.55`                              |
| Section kicker/eyebrow    |                `8–9px` |                     `800` | Uppercase, letter-spacing `0.10–0.13em`; use sparingly     |
| Button label              |                 `11px` |                 `650–700` | Keep all primary/secondary buttons consistent              |
| Input/select text         |              `12–13px` |                 `400–600` | Prefer `13px` for general form controls                    |
| Field label               |              `10–11px` |                 `650–700` | Must remain readable and clearly associated with its field |
| Field hint/description    |              `10–11px` |                     `400` | Usually line-height `1.4–1.5`                              |
| Table header              |                `8–9px` |                 `750–800` | Uppercase, letter-spacing `0.08em`; do not overuse         |
| Table primary cell        |              `11–12px` |                 `650–750` | Main record name/value                                     |
| Table secondary cell      |               `9–10px` |                 `400–600` | Slug, metadata, supporting details                         |
| Badge/chip                |                `8–9px` |                 `700–800` | Compact, short labels                                      |
| Validation/error text     |              `10–11px` |                 `500–600` | Must remain legible; pair color with text/icon             |
| Modal title               |              `16–18px` |                 `700–750` | Use same heading hierarchy as the page                     |
| Modal description         |              `11–12px` |                     `400` | Supporting context                                         |
| Technical key/slug        |               `9–10px` |                 `500–700` | Monospace stack                                            |
| Tiny decorative metadata  |          `8px` minimum |                 `600–800` | Avoid for essential information                            |

**Rules**

- Use `rem` for the final shared token system where practical; the pixel sizes above document the current visual scale.
- Avoid arbitrary one-off font sizes. Choose the closest defined role.
- Do not use low-contrast faint text for important content.
- Body text should not become tiny simply to fit more content; reflow or simplify instead.
- Use `font-weight: 750` only if the chosen font supports it; otherwise the browser will synthesize the nearest available weight.

## 3. Spacing system

Use a predictable 4px-based spacing scale. The reference pages use values such as `4px`, `5px`, `7px`, `9px`, `10px`, `12px`, `14px`, `16px`, `18px`, and `22px`; the canonical scale below regularizes these values.

| Token       |  Value | Typical use                                       |
| ----------- | -----: | ------------------------------------------------- |
| `--space-1` |  `4px` | Tiny icon/text separation, kicker margin          |
| `--space-2` |  `8px` | Button groups, compact field gaps                 |
| `--space-3` | `12px` | KPI gap, compact card padding                     |
| `--space-4` | `16px` | Form/control groups, compact panel padding        |
| `--space-5` | `20px` | Main content padding, larger component gaps       |
| `--space-6` | `24px` | Page-header gap, major section separation         |
| `--space-8` | `32px` | Separation between major page regions when needed |

### Recommended spacing by component

| Component                        | Standard         |
| -------------------------------- | ---------------- |
| Page header bottom margin        | `20–22px`        |
| Breadcrumb-to-heading gap        | `8–9px`          |
| Heading icon-to-copy gap         | `10–12px`        |
| Heading title-to-description gap | `5–6px`          |
| Header action gap                | `8px`            |
| KPI/stat grid gap                | `12px`           |
| KPI card padding                 | `12–14px`        |
| Main panel toolbar padding       | `16–18px`        |
| Panel heading-to-description gap | `5–6px`          |
| Search/filter control gap        | `8px`            |
| Table cell horizontal padding    | `14–17px`        |
| Table cell vertical padding      | `10–12px`        |
| Form grid gap                    | `14–16px`        |
| Field label-to-control gap       | `6–8px`          |
| Field-to-field vertical gap      | `14–16px`        |
| Modal header padding             | `18px 18px 14px` |
| Modal body padding               | `16–18px`        |
| Modal footer padding             | `12–18px`        |
| Inline icon-to-label gap         | `6–8px`          |

Prefer `gap` in Flexbox/Grid over adding margins to every child. Use margins mainly for clear section-level separation.

## 4. Layout and positioning

### 4.1 Standard page composition

```text
ErpLayout
└── main/page root
    ├── header.page-header
    │   ├── page-heading
    │   │   ├── breadcrumb
    │   │   └── heading-row
    │   │       ├── heading-icon
    │   │       └── h1 + description
    │   └── header-actions
    ├── operation-message (optional, role="status")
    ├── stat-grid (optional)
    ├── main content/workspace
    │   ├── toolbar/filter region (optional)
    │   ├── content / table / form / category navigation
    │   └── loading, empty, or error state
    ├── contextual/security note (optional)
    └── dialog/overlay (only when needed)
```

Use the same order unless a page’s workflow clearly requires another structure. Do not add KPI cards to a page that has no useful metrics.

### 4.2 Page root

- Width: `100%`.
- `min-width: 0` to prevent flex/grid children from forcing horizontal overflow.
- `box-sizing: border-box`.
- The reviewed pages use `padding: 0` because page spacing is managed by the layout and component structure. Confirm `ErpLayout` content padding before adding page-level padding.
- Use a single clear page root class, e.g. `.products-page`.

### 4.3 Page header

- Use semantic `<header class="page-header">`.
- Desktop: `display: flex`, `justify-content: space-between`, `align-items: flex-end`, `gap: 24px`.
- Page heading must be allowed to shrink: `min-width: 0`.
- Header actions use `display: flex`, `align-items: center`, `gap: 8px`, and wrap when necessary.
- Use a breadcrumb followed by exactly one page-level `<h1>`.
- Main title and description should form one text group; keep action buttons aligned with the heading group on wide screens.

### 4.4 KPI/stat grid

- Use semantic `<section aria-label="Summary">` or a similarly meaningful accessible label.
- Use `<article>` for each independent metric card.
- Recommended desktop layout: four equal columns when there are four useful metrics; use three columns for three metrics.
- `display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px`.
- Cards: padding `12–14px`; radius `10–12px`.
- Do not force four columns when labels or values become cramped; responsive rules should reduce the column count.

### 4.5 Main workspace and two-column pages

The Settings page uses a sidebar/content architecture and the Modules page uses a single main panel. Pick the pattern that fits the task:

- **Single workspace:** one full-width bordered panel containing toolbar and primary content.
- **Sidebar workspace:** `grid-template-columns: 220px minmax(0, 1fr)` as the recommended baseline. The Settings source uses `190px` at a narrower breakpoint and switches to one column at `900px`.
- Use `minmax(0, 1fr)` for flexible columns to prevent overflow.
- Keep navigation in `<aside>` and `<nav aria-label="…">`; put the principal page content in `<main>` only when it is the primary main landmark and no competing `<main>` exists in the parent layout.
- Do not create nested `<main>` landmarks. If `ErpLayout` already renders `<main>`, use `<section>` or `<div>` for the page content wrapper instead.

### 4.6 Cards, panels, and surfaces

Recommended shared values:

- Standard card radius: `10–12px`.
- Main workspace radius: `12–14px`.
- Modal radius: `14–16px`.
- Standard border: `1px solid var(--ms-border)`.
- Shadow: use a shared token; keep it subtle.
- Panel content padding: `16–18px`.
- Avoid excessive nested cards. Use a card only when it creates a meaningful grouping.

### 4.7 Tables

- Use real `<table>`, `<thead>`, `<tbody>`, `<tr>`, `<th scope="col">`, and `<td>` markup.
- Wrap wide tables in a horizontally scrollable container instead of allowing the whole page to overflow.
- Header labels should be short and consistent.
- Use a dedicated action column with accessible button names.
- Use badges for status/type, not color alone.
- Ensure empty and loading states are inside the table’s content region and are announced appropriately.

### 4.8 Forms

- Use a real `<form>` for submit-based workflows.
- Use `<label for="…">` or wrap the control inside a `<label>`. Each field needs a visible label.
- Use `fieldset` and `legend` for logically related groups where useful.
- Desktop forms may use two columns: `grid-template-columns: repeat(2, minmax(0, 1fr))`; full-width fields span both columns.
- Input/select recommended height: `36–38px` for compact admin controls; use at least `40px` where touch accessibility is important.
- Textarea: min-height based on expected content; preserve visible resize behavior unless there is a strong reason not to.
- Error text must be connected using `aria-describedby`; invalid fields should use `aria-invalid="true"`.
- Keep helper text, errors, and character counts close to their field.

### 4.9 Dialogs/modals

- Use a proper dialog pattern with `role="dialog"`, `aria-modal="true"`, and `aria-labelledby`.
- Use `<header>`, `<form>` or body region, and `<footer>` for the modal’s structure.
- Prevent accidental close while saving if that matches the operation’s behavior.
- Support Escape to close when safe, focus management, keyboard navigation, and focus return to the trigger.
- Use a backdrop with restrained opacity/blur; do not rely on animation alone to communicate state.
- Keep modal width responsive and the body scrollable when content exceeds viewport height.

## 5. Color and design tokens

The two source pages use different CSS variable naming conventions. Modules uses the `--ms-*` family (for example `--ms-text-body`, `--ms-text-heading`, `--ms-text-muted`, `--ms-border`, `--ms-surface-soft`, `--ms-control-bg`). Settings uses names such as `--text-primary`, `--text-secondary`, `--text-muted`, `--border`, and `--surface`.

This difference should be resolved centrally rather than copied into every page. **Do not mix both token families in newly written page CSS.** Choose one canonical token API in global styles and optionally map legacy names to it.

Suggested semantic token roles (the exact values should be defined in the global theme file):

```css
:root {
    /* Brand */
    --ms-primary: /* brand primary */;

    /* Text */
    --ms-text-heading: /* strongest text */;
    --ms-text-body: /* normal text */;
    --ms-text-muted: /* secondary text */;
    --ms-text-faint: /* low-emphasis text; never for essential content */;

    /* Surfaces and borders */
    --ms-surface: /* card/page surface */;
    --ms-surface-muted: /* muted panel surface */;
    --ms-surface-soft: /* subtle chip/hover surface */;
    --ms-control-bg: /* input/select background */;
    --ms-border: /* standard border */;
    --ms-border-subtle: /* low-emphasis divider */;
    --ms-border-strong: /* emphasized border */;

    /* Status */
    --ms-success: /* success */;
    --ms-warning: /* warning */;
    --ms-danger: /* error/danger */;
    --ms-info: /* informational */;
    --ms-focus-ring: /* accessible focus indicator */;

    /* Shape, spacing, motion */
    --ms-radius-control: 8px;
    --ms-radius-card: 12px;
    --ms-radius-panel: 14px;
    --ms-space-1: 4px;
    --ms-space-2: 8px;
    --ms-space-3: 12px;
    --ms-space-4: 16px;
    --ms-space-5: 20px;
    --ms-space-6: 24px;
    --ms-transition-fast: 150ms ease;
    --ms-transition-standard: 180ms ease;
}
```

The comments above are placeholders, not valid final CSS values. Define the actual colors and tokens in the global theme stylesheet, then consume them in components. Do not paste this block unchanged into production.

### Color behavior

- Primary action: brand primary token.
- Secondary action: neutral surface with standard border.
- Success/warning/danger: distinct semantic tokens, ideally with a small icon or text label as well as color.
- Status badges: subtle tinted background, matching border/text, pill or compact rounded shape.
- Hover states should preserve contrast in both light and dark themes.
- Avoid isolated hard-coded hex values inside pages except where the color is intentionally part of a data visualization or a documented brand asset.

## 6. Buttons and controls

### Buttons

- Compact standard height: `36px`; compact controls may be `34px` only where density requires it.
- Horizontal padding: `12–14px`.
- Radius: `8–9px`.
- Label: `11px`, weight `650–700`.
- Icon/label gap: `7–8px`.
- Use `type="button"` for non-submit buttons and `type="submit"` only for form submission.
- Disabled controls must be visually distinct and actually disabled.
- Keep primary and secondary button variants consistent across all modules.

### Inputs and selects

- Use inherited font family.
- Standard text size: `12–13px`.
- Border radius: `8–10px`.
- Use visible focus border/ring, not just a color change too subtle to notice.
- Placeholder text must not substitute for a label.
- Search inputs should have an accessible name, visible label, or `aria-label`.

### Badges

- Use concise labels.
- Use `aria-label` only when a badge’s visible text is insufficient to communicate its meaning.
- Avoid overusing uppercase microtext.
- Do not use badges for decorative section headings.

## 7. Semantic HTML and accessibility — required standard

Use semantic elements according to the meaning of the content, not merely because their default appearance is convenient.

| Purpose                               | Preferred markup                                                        |
| ------------------------------------- | ----------------------------------------------------------------------- |
| Page-level top region                 | `<header>`                                                              |
| Primary page title                    | `<h1>`                                                                  |
| Major content section                 | `<section aria-labelledby="…">`                                         |
| Independent metric card               | `<article>`                                                             |
| Primary page landmark                 | One `<main>` in the overall page                                        |
| Related navigation                    | `<nav aria-label="…">`                                                  |
| Sidebar/related complementary content | `<aside>`                                                               |
| Data grid                             | `<table>` with proper header/body structure                             |
| Form                                  | `<form>`                                                                |
| Form group                            | `<fieldset>` + `<legend>` when appropriate                              |
| Field name                            | `<label>` associated with its control                                   |
| Operation feedback                    | `role="status"` for non-urgent status; `role="alert"` for urgent errors |
| Dialog                                | `role="dialog"`, `aria-modal="true"`, accessible title                  |
| Repeated independent item             | `<article>` or list markup, depending on meaning                        |
| Generic layout wrapper                | `<div>` only when no semantic element better describes it               |

### Semantic rules

1. Maintain a logical heading hierarchy (`h1` → `h2` → `h3`); do not select headings based on font size.
2. Do not use `<strong>` or `<span>` as a replacement for headings or labels.
3. Use `<button>` for actions and `<a>`/Inertia `<Link>` for navigation.
4. Do not nest interactive controls inside other interactive controls.
5. Add `scope="col"` to table column headers; add row headers where appropriate.
6. Add accessible names to icon-only buttons.
7. Ensure keyboard focus is visible.
8. Respect `prefers-reduced-motion`.
9. Do not use color as the only signal for status, validation, or selection.
10. Keep landmark usage correct when `ErpLayout` already provides the page-level header, navigation, or main landmark.

## 8. Responsive behavior

The source files use several breakpoints rather than one unified breakpoint system. Modules includes `1100px`, `820px`, `760px`, and `480px`; Settings includes `1100px`, `1050px`, `900px`, `760px`, and `560px`. For future pages, use a common set unless a component-specific breakpoint is needed.

| Breakpoint | Recommended behavior                                                               |
| ---------- | ---------------------------------------------------------------------------------- |
| `1100px`   | Reduce KPI columns from 4 to 2; tighten wide workspace/sidebar layout              |
| `900px`    | Collapse two-column sidebar/content layouts to one column when needed              |
| `760px`    | Stack page header and action groups; allow filters/toolbars to wrap                |
| `560px`    | Single-column forms/navigation/cards where necessary; stack modal/footer actions   |
| `480px`    | Reduce title to around `20px`; make action controls wrap or occupy available width |

Example:

```css
.stat-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: var(--ms-space-3);
}

@media (max-width: 1100px) {
    .stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 560px) {
    .stat-grid {
        grid-template-columns: 1fr;
    }
}
```

Responsive requirements:

- Avoid fixed widths for the whole page.
- Use `min-width: 0` on flex/grid children where content may be long.
- Let toolbars wrap or stack on small screens.
- Make tables scroll horizontally within their own container if required.
- Keep all actions reachable; do not hide essential controls without an alternative.
- Test long labels, translated text, empty states, and validation errors at narrow widths.

## 9. Motion, hover, and focus

- Use short transitions, typically `150–180ms`.
- Hover elevation should be subtle; avoid moving large components enough to cause layout shift.
- Add clear `:focus-visible` styling to buttons, links, inputs, navigation items, and custom controls.
- Loading spinners should not be the only status indication; provide text such as “Loading products…”.
- Disable or reduce nonessential animation under `@media (prefers-reduced-motion: reduce)`.
- Do not make important information depend on hover.

## 10. Page states every data-driven page should consider

A list/detail/form page should intentionally design the following states, not only the happy path:

- Initial loading state.
- Empty state when no records exist.
- No-results state when search/filter returns nothing.
- Error state with a clear message and recovery action where possible.
- Success feedback after create/update/delete.
- Validation errors next to the relevant fields.
- Disabled/saving state to prevent duplicate submissions.
- Permission-restricted or protected-record state where applicable.
- Responsive layout for all of the above.

Use `role="status"` for routine feedback and reserve `role="alert"` for errors that require immediate attention.

## 11. Recommended component CSS baseline

Use this as a pattern, adapting class names to the page. It assumes the canonical variables are defined globally.

```css
.page-root {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
    color: var(--ms-text-body);
}

.page-header {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: var(--ms-space-6);
    margin-bottom: 22px;
}

.page-heading {
    min-width: 0;
}

.breadcrumb {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 7px;
    margin-bottom: 9px;
    color: var(--ms-text-muted);
    font-size: 11px;
    font-weight: 600;
}

.page-title {
    margin: 0;
    color: var(--ms-text-heading);
    font-size: 23px;
    font-weight: 750;
    letter-spacing: -0.025em;
}

.page-description {
    max-width: 680px;
    margin: 5px 0 0;
    color: var(--ms-text-muted);
    font-size: 12px;
    line-height: 1.55;
}

.header-actions {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: var(--ms-space-2);
}

.stat-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: var(--ms-space-3);
    margin-bottom: 14px;
}

.stat-card,
.content-panel {
    min-width: 0;
    border: 1px solid var(--ms-border);
    border-radius: var(--ms-radius-card);
    background: var(--ms-surface);
}

.stat-card {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
}

.content-panel {
    overflow: hidden;
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    min-height: 36px;
    padding: 0 13px;
    border: 1px solid transparent;
    border-radius: 9px;
    font: inherit;
    font-size: 11px;
    font-weight: 700;
    cursor: pointer;
    transition:
        border-color var(--ms-transition-fast),
        background var(--ms-transition-fast),
        color var(--ms-transition-fast),
        box-shadow var(--ms-transition-fast);
}

.btn:focus-visible,
input:focus-visible,
select:focus-visible,
textarea:focus-visible,
a:focus-visible {
    outline: 2px solid var(--ms-focus-ring);
    outline-offset: 2px;
}

@media (max-width: 1100px) {
    .stat-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 760px) {
    .page-header {
        align-items: flex-start;
        flex-direction: column;
    }

    .header-actions {
        width: 100%;
    }
}

@media (max-width: 560px) {
    .stat-grid {
        grid-template-columns: 1fr;
    }

    .page-title {
        font-size: 20px;
    }
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        scroll-behavior: auto !important;
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}
```

## 12. New-page implementation checklist

Before marking a page complete, verify each item:

### Structure

- [ ] Wrapped in the existing `ErpLayout`.
- [ ] One clear page title (`h1`) and a logical heading hierarchy.
- [ ] Correct semantic landmarks; no nested `<main>` if the layout already provides one.
- [ ] Breadcrumbs and action buttons follow the standard header pattern.
- [ ] Main content uses a panel/grid pattern suited to the workflow.

### Typography and spacing

- [ ] Inherits the global font family; no accidental page-specific font reset.
- [ ] Uses the canonical type scale rather than arbitrary sizes.
- [ ] Uses spacing tokens or values from the spacing table.
- [ ] Uses consistent button/input heights, border radii, and icon gaps.
- [ ] Supporting text remains readable and has sufficient contrast.

### Theme and components

- [ ] Uses canonical theme variables; no second token naming system introduced.
- [ ] Buttons, inputs, badges, panels, and feedback match other modules.
- [ ] Light/dark theme behavior is checked if both are supported.
- [ ] Focus, hover, disabled, loading, and reduced-motion states are considered.

### Accessibility and responsive layout

- [ ] Every form control has a label and errors are programmatically associated.
- [ ] Icon-only actions have accessible names.
- [ ] Table headings and navigation labels are semantic.
- [ ] Keyboard navigation works.
- [ ] Layout checked at desktop, tablet, and mobile breakpoints.
- [ ] Long names, empty states, errors, and loading states do not break the layout.

### Verification

- [ ] Existing business logic and API behavior are preserved when restyling.
- [ ] TypeScript check passes.
- [ ] Production build passes.
- [ ] Relevant create/edit/save/filter flows are tested.

---

## 13. Source observations and follow-up work

The supplied pages share the same overall ERP visual direction but are **not yet a fully unified design system**:

1. They use different CSS variable namespaces (`--ms-*` vs. plain names such as `--text-primary`).
2. Their font-size choices overlap but differ by component.
3. Their responsive breakpoints are not identical.
4. The base font family is inherited and is not explicitly declared in either component’s local CSS.
5. Many common components (header, breadcrumb, KPI card, buttons, messages, panel headings) are styled locally in each page.

### Recommended next step

After agreeing on this standard, move shared design tokens into one global theme stylesheet and, where practical, extract reusable components such as:

- `PageHeader`
- `Breadcrumbs`
- `StatCard` / `StatGrid`
- `BaseButton`
- `StatusBadge`
- `OperationMessage`
- `EmptyState`
- `LoadingState`
- `FormField`
- `ConfirmDialog` / `BaseModal`

Do this incrementally. Do not replace all local CSS at once if that risks regressions. Migrate one page at a time and verify its behavior after each change.

**Source files reviewed:** `resources/js/pages/Developer/Modules.vue`, `resources/js/pages/Settings/System.vue`.
