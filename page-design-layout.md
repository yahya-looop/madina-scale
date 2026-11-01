**# MADINA SCALE — MASTER PAGE DESIGN & UI/UX SOURCE OF TRUTH**



**\*\*Document type:\*\*** Authoritative project-wide design specification and implementation prompt  

**\*\*Project:\*\*** Madina Scale ERP  

**\*\*Application stack:\*\*** Laravel 13 · Vue 3 · TypeScript · Inertia  

**\*\*Scope:\*\*** All authenticated backend pages, shared ERP shell, page layouts, forms, tables, KPI cards, buttons, icons, status indicators, dialogs, modals, popovers, drawers, feedback states, responsive behavior and accessibility.  

**\*\*Authority:\*\*** This document supersedes the two earlier page-design standards and is the single reference for future backend UI/UX work.



**---**



**## 0. Authority, precedence and purpose**



This document consolidates two earlier design standards:



1\. **\*\*Madina Scale — Page Design & Layout Standard\*\***, based on \`resources/js/pages/Developer/Modules.vue\` and \`resources/js/pages/Settings/System.vue\`.

2\. **\*\*Madina Scale — Master Backend UI/UX Design Source of Truth\*\***, based on the supplied Product and Inventory pages.



The consolidated reference hierarchy is:



\| Design concern | Primary reference / authority | Supporting reference |

\|---|---|---|

\| Page-level visual language | \`resources/js/pages/Products/Index.vue\` | \`Developer/Modules.vue\`, \`Settings/System.vue\` |

\| Create/edit form composition | \`Products/Create.vue\` and \`Products/Edit.vue\` for workflow/content only | \`Settings/System.vue\` for sidebar/content structure |

\| Detail/show composition | \`Products/Show\.vue\` for information architecture only | Shared page and card standards in this document |

\| Small confirmation modal | \`Products/Index.vue\` | \`Inventory/Reservations.vue\` |

\| Medium operational modal | \`Inventory/Operations.vue\` | \`Inventory/Index.vue\` reservation modal |

\| Large structured modal | \`Inventory/Index.vue\` | This document's large-modal specification |

\| Semantic HTML and accessibility | This document's consolidated requirements | Earlier Page Design & Layout Standard |

\| Global tokens, typography and spacing | This document's canonical scales | Source-page measurements summarized below |



**### Binding rules**



1\. \`Products/Index.vue\` is the primary visual reference for backend register/list pages: breadcrumb, page header, title/icon, actions, KPI cards, main content card, toolbar, filters, table, badges, row actions and data states.

2\. The Product Create/Edit/Show pages are workflow and content references, not independent visual authorities. Preserve their capabilities and normalize their presentation to this design system.

3\. \`Developer/Modules.vue\` and \`Settings/System.vue\` remain useful secondary references for semantic page composition, sidebar navigation and content organization. Their legacy CSS values do not override the canonical tokens and measurements in this document.

4\. Inventory pages define practical modal patterns as listed above. Their workflows and content are references; the standardized modal dimensions and behavior below are authoritative.

5\. Existing business logic, routes, permissions, validation, API contracts, database behavior, data transformations and user workflows must remain unchanged during UI-only work.

6\. If a future implementation conflicts with this document, this document takes precedence unless the project owner explicitly approves a documented exception.

7\. Do not copy unrelated page-scoped CSS simply because it exists. Prefer shared tokens and reusable components.

8\. This is a design contract, not permission to rewrite the application architecture or backend.



**---**



**## 1. Audited reference pages and design decisions**



**### 1.1 Product module**



\| Page | Existing role | Required treatment |

\|---|---|---|

\| \`Products/Index.vue\` | Compact enterprise catalog/register with header, KPI row, toolbar, filters, table, badges and delete confirmation | **\*\*Primary page-level visual reference\*\*** |

\| \`Products/Create.vue\` | Rich form with media upload, product information, variant builder, summary and action area | Preserve fields and workflow; normalize visual structure |

\| \`Products/Edit.vue\` | Product editing workflow with media and variants | Preserve editing capabilities; align with Create and global controls |

\| \`Products/Show\.vue\` | Product overview/detail information and actions | Preserve information architecture; normalize cards, typography and action placement |



**### 1.2 Inventory and CRM pages reviewed as supporting references**



\- \`Inventory/Index.vue\`

\- \`Inventory/LowStock.vue\`

\- \`Inventory/Movements.vue\`

\- \`Inventory/Operations.vue\`

\- \`Inventory/Reservations.vue\`

\- \`SalesCRM/Leads/Index.vue\`



These pages provide examples of operational data, modal workflows, confirmation patterns, inventory summaries, filters and CRM records. They are not separate design systems.



**### 1.3 Product Index measurements used as the register baseline**



\- Page title approximately 24px; title icon tile 42×42px.

\- Breadcrumb approximately 11px; description approximately 12px.

\- Header action gap 8px; page-header bottom spacing approximately 18–20px.

\- Standard buttons approximately 38px high, 9px radius, 12px text.

\- KPI grid: 4 columns on wide desktop, 12px gap; cards approximately 78px minimum height.

\- KPI padding approximately 12px 14px; icon tile 34×34px; KPI value approximately 21px.

\- Main catalog card radius approximately 14px, with a subtle border and shadow.

\- Search/filter controls approximately 36px high.

\- Table header approximately 38px; product-like body rows approximately 62px.

\- Table header text approximately 9px; cell text approximately 10–11px.

\- Status badges approximately 23px high.

\- Row action buttons approximately 29px square.

\- Empty state is centered, icon-led and includes a useful explanation/action.

\- Product delete confirmation is a compact, record-aware destructive confirmation.



The source page contains a few local inconsistencies and duplicate declarations. The canonical values in this document resolve those differences; do not reproduce accidental CSS duplication.



**---**



**## 2. Design direction and non-negotiable principles**



**\*\*Visual direction: premium enterprise ERP — clean, compact, calm, precise and data-first.\*\***



\- Use clean neutral surfaces with the existing Madina Scale primary brand color.

\- Keep information density high without sacrificing readability.

\- Use clear hierarchy, consistent alignment and predictable interactions.

\- Use color to communicate meaning, not as decoration.

\- Avoid decorative gradients, glow effects, excessive glassmorphism, oversized whitespace and unnecessary animation.

\- All pages must feel like parts of one product, not independently designed applications.

\- Prioritize operational clarity, accessibility, speed and consistency over novelty.



**### Non-negotiable system rules**



\- One shared ERP shell and content gutter.

\- One page-header and breadcrumb pattern.

\- One typography scale and spacing scale.

\- One canonical theme-token namespace.

\- One button and form-control system.

\- One icon library and sizing convention.

\- One card, border, radius and shadow system.

\- One status-color map.

\- One table/register pattern.

\- One toast/alert and data-state system.

\- Three standard modal sizes: small, medium and large.

\- Light, dark and system theme compatibility wherever supported by the application.

\- Responsive behavior must be designed as part of the component, not added as a last-minute patch.



**---**



**## 3. Canonical design tokens**



**### 3.1 Token namespace**



Use **\*\*\`--ms-\*\`\*\*** as the canonical token namespace for new and migrated backend UI components.



The older pages use both \`--ms-\*\` and unprefixed variables such as \`--text-primary\`, \`--surface\` and \`--border\`. Do not continue this split in new components. If existing application styles still depend on unprefixed variables, map them centrally as aliases to canonical tokens during a controlled migration. Do not maintain two independently configurable palettes.



**### 3.2 Semantic color tokens**



The exact brand color values must come from the existing Madina Scale theme implementation. Do not invent a new primary green or silently replace the current brand palette.



\| Canonical token | Role |

\|---|---|

\| \`--ms-primary\` | Primary actions, active navigation, links and focus accents |

\| \`--ms-primary-hover\` | Primary action hover |

\| \`--ms-primary-soft\` | Soft primary backgrounds and selected surfaces |

\| \`--ms-background\` | App/page background |

\| \`--ms-surface\` | Cards, panels, menus, inputs and dialogs |

\| \`--ms-surface-muted\` | Muted sections, input backgrounds and hover surfaces |

\| \`--ms-border\` | Standard borders and dividers |

\| \`--ms-border-strong\` | Emphasized and interactive borders |

\| \`--ms-text-primary\` | Main headings, values and primary content |

\| \`--ms-text-secondary\` | Supporting copy and descriptions |

\| \`--ms-text-muted\` | Breadcrumbs, metadata and placeholders |

\| \`--ms-success\`, \`--ms-success-soft\` | Success/completed states |

\| \`--ms-warning\`, \`--ms-warning-soft\` | Warnings and attention states |

\| \`--ms-danger\`, \`--ms-danger-soft\` | Destructive actions and errors |

\| \`--ms-info\`, \`--ms-info-soft\` | Informational and in-progress states |

\| \`--ms-overlay\` | Modal backdrop |

\| \`--ms-focus-ring\` | Visible keyboard focus indicator |



Status colors must be consistent across modules. Identical status values must not change color arbitrarily between pages.



**### 3.3 Shape, spacing and motion tokens**



\`\`\`css

:root {

  --ms-radius-control: 8px;

  --ms-radius-button: 9px;

  --ms-radius-card: 12px;

  --ms-radius-panel: 14px;

  --ms-radius-modal-sm: 18px;

  --ms-radius-modal-md: 22px;

  --ms-radius-modal-lg: 20px;



  --ms-space-1: 4px;

  --ms-space-2: 8px;

  --ms-space-3: 12px;

  --ms-space-4: 16px;

  --ms-space-5: 20px;

  --ms-space-6: 24px;

  --ms-space-7: 28px;

  --ms-space-8: 32px;



  --ms-transition-fast: 150ms ease;

  --ms-transition-standard: 180ms ease;

}

\`\`\`



These are design-token values, not a requirement to paste this block into an arbitrary file. Add or map them in the project's actual global theme layer after inspecting the existing theme architecture.



**### 3.4 Border and shadow**



\- Standard border: \`1px solid var(--ms-border)\`.

\- Emphasized border: \`1px solid var(--ms-border-strong)\`.

\- Cards use a subtle shadow only; border should define the component.

\- Avoid heavy shadows on every card.

\- Focus treatment: visible approximately 2–3px primary-derived ring with adequate offset.

\- Equivalent components must use the same radius and shadow values.



**---**



**## 4. Typography system**



**### 4.1 Font family**



The reviewed page-level source files do not establish a trustworthy global font-family source. The actual font must be confirmed from \`ErpLayout\`, global CSS and the app entry stylesheet before any global font change.



Rules:



1\. Preserve the existing application-wide font if it is already loaded and consistently used.

2\. Do not introduce a page-specific font family.

3\. Do not add a new font dependency just for visual redesign.

4\. Controls and ordinary UI text must inherit the global font.

5\. Use a monospace stack only for technical identifiers, slugs, keys and code-like values.

6\. If a future audit confirms that no global font has been established, choose and document one global stack centrally; do not make that choice separately inside pages.



Recommended technical monospace stack:



\`\`\`css

font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;

\`\`\`



**### 4.2 Canonical type scale**



\| UI role | Canonical size | Weight | Notes |

\|---|---:|---:|---|

\| Page title (\`h1\`) | 24px desktop; 20px narrow mobile | 700–750 | One page-level title |

\| Page description | 12px | 400–500 | Line-height 1.5–1.55 |

\| Breadcrumb | 11px | 600; current 700 | Muted; current item distinct |

\| Main panel/section title | 14–16px | 700–750 | Use semantic heading level |

\| Subsection/card title | 12–14px | 650–700 | Consistent hierarchy |

\| KPI value | 21px | 700–750 | Tabular numerals |

\| KPI label | 10px | 500–600 | Muted |

\| Section eyebrow/kicker | 8–9px | 750–800 | Sparse use only |

\| Standard button | 12px | 650–700 | Compact button may use 11px |

\| Input/select value | 12–13px | 400–500 | Prefer 12px for dense admin controls |

\| Field label | 11px | 600–650 | Visible and associated |

\| Helper text | 10–11px | 400 | Line-height 1.4–1.5 |

\| Table header | 9px | 700–750 | Uppercase only when useful |

\| Table primary cell | 10–11px | 600–700 | Entity name/value |

\| Table secondary cell | 9–10px | 400–600 | Metadata/identifier |

\| Badge | 9–10px | 600–700 | Short labels |

\| Validation message | 10–11px | 500–600 | Clear and readable |

\| Modal title — small | 18–20px | 700–750 | Confirmation |

\| Modal title — medium | 20–21px | 750–800 | Workflow |

\| Modal title — large | 22–24px | 700–760 | Complex operation |

\| Modal body | 11–12px | 400–500 | Line-height 1.55–1.65 |

\| Technical key/slug | 9–10px | 500–700 | Monospace |



Typography rules:



\- Use tabular numerals for quantities, prices, IDs, dates and KPI values where alignment matters.

\- Negative letter spacing is reserved for prominent headings and large values.

\- Do not use 7–8px text for essential information.

\- Do not shrink important body text merely to fit more content; reflow or simplify the layout instead.

\- Avoid arbitrary one-off font sizes.

\- Use \`rem\` for shared tokens where practical, while preserving the pixel values as the documented visual baseline.



**---**



**## 5. Spacing and layout rhythm**



Use a 4px base rhythm.



\| Token | Value | Typical use |

\|---|---:|---|

\| \`space-1\` | 4px | Tight icon/text separation |

\| \`space-2\` | 8px | Button groups, compact gaps |

\| \`space-3\` | 12px | KPI gap, toolbar, compact padding |

\| \`space-4\` | 16px | Standard card/form padding |

\| \`space-5\` | 20px | Section separation |

\| \`space-6\` | 24px | Major component padding |

\| \`space-7\` | 28px | Large layout separation |

\| \`space-8\` | 32px | Major page regions |



**### Component spacing**



\| Component | Canonical spacing |

\|---|---:|

\| Page header bottom margin | 18–20px |

\| Breadcrumb-to-title gap | 8px |

\| Title icon-to-copy gap | 10–12px |

\| Title-to-description gap | 5–6px |

\| Header action gap | 8px |

\| KPI grid gap | 12px |

\| KPI-to-content gap | 14px |

\| Standard card padding | 14–16px |

\| Card header padding | approximately 16px |

\| Search/filter gap | 8px |

\| Form grid gap | 14–16px |

\| Field label-to-control gap | 5–6px |

\| Field-to-field vertical gap | 12–16px |

\| Modal body section gap | 14–18px |

\| Inline icon-to-label gap | 6–8px |



Prefer \`gap\` in Flexbox/Grid over margins on every child. Use margins for clear section-level separation only.



**---**



**## 6. Global page shell and page composition**



Every backend page must use the existing shared ERP shell (currently \`ErpLayout\` or its project equivalent). Do not add a second competing outer page gutter.



**### 6.1 Standard composition**



\`\`\`text

ErpLayout

└── page root (section/div; do not duplicate parent landmarks)

    ├── page header

    │   ├── breadcrumb

    │   ├── title row (optional icon + h1 + description)

    │   └── page actions

    ├── operation message (optional)

    ├── KPI/stat grid (optional)

    ├── main content workspace

    │   ├── toolbar and filters (optional)

    │   ├── table / form / chart / detail content

    │   └── loading, empty or error state

    ├── contextual note/footer (optional)

    └── dialogs/overlays (only when needed)

\`\`\`



Do not add KPI cards when there are no useful decision-making metrics.



**### 6.2 Page root**



\- Width: 100%.

\- \`min-width: 0\`.

\- \`box-sizing: border-box\`.

\- Page-level outer padding is controlled by the shared shell unless the shell explicitly requires otherwise.

\- Use one clear root class per page.



**### 6.2.1 Page-root padding and content-gutter ownership**



The shared ERP shell owns the page's outer content gutter. A page root must not introduce a second, oversized layer of top or left/right padding.



**Specific implementation requirement — Sales Returns (`.sr-page`):**



```css
.sr-page {
  padding: 0;
}
```



Implementation rules:



1. Set the `.sr-page` root padding to exactly `0` on all sides. Do not retain separate top, left, right or bottom padding on this page root.

2. Keep a small, intentional gap between the shared shell boundary and the page content. That outer gap must be provided by the shared ERP shell/content container—not recreated as padding on `.sr-page`.

3. Do not add a second wrapper with equivalent large padding around `.sr-page` content. Check parent containers, layout utilities and page-specific selectors for duplicated spacing.

4. Preserve the established internal spacing between the breadcrumb, page header, KPI cards, forms, tables and content panels. Removing root padding must not collapse component-level spacing.

5. Keep the page root at `width: 100%`, `min-width: 0` and `box-sizing: border-box`; prevent horizontal overflow at narrow viewport widths.

6. Scope this zero-padding rule to `.sr-page`. Do not globally reset padding on unrelated page roots or change the shared ERP shell's gutter as a side effect.

7. If the shared shell currently applies excessive top or left/right spacing, adjust the shell's content-gutter value deliberately and consistently rather than compensating with negative margins on the page.



**Acceptance criteria:** The Sales Returns content begins closer to the top and left boundaries of its workspace, with only a modest outer shell gutter remaining. The page must not touch or overlap the sidebar, top navigation or viewport edge, and its internal component spacing must remain intact.



**### 6.3 Page header**



\- Use a semantic header region where the parent layout does not already provide a conflicting landmark.

\- Breadcrumb appears above the title.

\- Exactly one page-level \`h1\`.

\- Desktop: flex row, \`justify-content: space-between\`, \`align-items: flex-end\`, gap around 24px.

\- Heading group must be shrinkable (\`min-width: 0\`).

\- Optional title icon tile: 42×42px, 12px radius, soft primary surface, 20px icon.

\- Header actions: flex row, 8px gap, wrap when required.

\- On narrow screens, stack heading and actions; do not hide essential actions.



**### 6.4 Page type patterns**



**\*\*Register/list pages\*\***



\`Header → optional KPI row → main register card → toolbar → filters → table → pagination\`



**\*\*Create/edit pages\*\***



\`Header/actions → form sections → optional summary sidebar → bottom/sticky action area where useful\`



**\*\*Detail/show pages\*\***



\`Header/actions → summary/hero region → information cards → related records/timeline → lifecycle metadata\`



**\*\*Analytics pages\*\***



\`Header/filter row → KPI cards → chart grid → breakdown tables\`



Choose the structure that supports the workflow. Do not force every page into the same content layout.



**### 6.5 Single workspace and sidebar workspace**



\- Single workspace: one full-width bordered panel for toolbar and primary content.

\- Sidebar workspace: recommended baseline \`220px minmax(0, 1fr)\`; adjust only when the content requires it.

\- Use \`minmax(0, 1fr)\` for flexible grid columns.

\- Put related navigation in \`aside\`/\`nav\` where semantically appropriate.

\- Do not create nested \`\<main>\` landmarks. If \`ErpLayout\` already owns the main landmark, use \`section\` or \`div\` inside the page.



**### 6.6 Cards and panels**



\- Standard card radius: 12–14px.

\- Main workspace radius: 14px.

\- Border: 1px canonical border.

\- Surface: canonical theme surface.

\- Padding: 14–16px; large content regions may use 18–20px.

\- Shadow: subtle.

\- Avoid unnecessary nested cards; group content only when it improves comprehension.



**---**



**## 7. KPI and statistic cards**



Use the Product Index KPI pattern.



\- Desktop: 4 equal columns where four useful metrics exist.

\- Medium: 2 columns.

\- Mobile: 1 or 2 columns depending on available width; never force overflow.

\- Gap: 12px.

\- Minimum height: approximately 78px.

\- Padding: 12px 14px.

\- Radius: 12px.

\- Border: 1px canonical border.

\- Icon tile: 34×34px, 9px radius, 17px icon.

\- Label: 10px, weight 500–600.

\- Value: 21px, weight 700, tabular numerals.

\- Secondary/trend value: 9–10px.

\- Use soft semantic icon backgrounds, not saturated full-card fills.

\- Equivalent KPI cards must share height, icon size and typography.



KPI cards must add decision value. Do not add them as decoration or simply to fill space.



**---**



**## 8. Buttons and action hierarchy**



**### 8.1 Sizes**



\| Variant | Height | Horizontal padding | Font | Radius |

\|---|---:|---:|---:|---:|

\| Small | 32px | 10px | 11px | 8px |

\| Standard | 38px | 12–14px | 12px | 9px |

\| Large/modal primary | 42–44px | 16–18px | 12px | 10–11px |

\| Icon-only | 29–36px square | 0 | icon 13–16px | 8–10px |



**### 8.2 Variants**



\- **\*\*Primary:\*\*** solid Madina primary, high-contrast foreground; normally one primary action per context.

\- **\*\*Secondary:\*\*** surface background, standard border and secondary text.

\- **\*\*Tertiary/Ghost:\*\*** transparent with a clear hover surface.

\- **\*\*Danger:\*\*** semantic danger treatment for destructive actions only.

\- **\*\*Link:\*\*** text action with clear affordance and accessible focus.

\- **\*\*Icon-only:\*\*** fixed square dimensions, accessible name and tooltip/title where appropriate.



**### 8.3 Interaction**



\- Enabled buttons use pointer cursor.

\- Hover changes background/border; optional elevation is subtle and no more than 1px movement.

\- Active state gives a restrained pressed response.

\- \`:focus-visible\` must show a clear focus ring.

\- Disabled controls are actually disabled and visually distinct; use \`not-allowed\` cursor.

\- Loading state shows progress and a meaningful label such as \`Saving…\`; prevent duplicate submission.

\- Standard icon size: 14–16px; compact: 13–15px.

\- Icon/label gap: 7–8px.

\- Do not place several competing primary actions side by side without a genuine equal-priority workflow reason.

\- Use \`type="button"\` for non-submit buttons and \`type="submit"\` only for form submission.



**---**



**## 9. Iconography**



Use the existing project icon wrapper, currently \`@/components/dashboard/Icon.vue\`, and its supported icon set. Confirm its current API before implementation. Do not add a second icon library.



\| Context | Icon size |

\|---|---:|

\| Page title | 20px |

\| Section | 18px |

\| KPI | 17px |

\| Standard button | 14–16px |

\| Table action | 13–15px |

\| Metadata | 12–13px |



Rules:



\- Maintain one consistent outline/fill style and stroke weight.

\- Use the same semantic icon for the same action across modules.

\- Icon-only controls require accessible names and, where useful, tooltips.

\- Never use emoji as a substitute for interface icons.

\- Do not mix icon libraries or visual styles arbitrarily.



**---**



**## 10. Forms and input controls**



All forms across Products, Customers, Orders, Inventory, CRM, Finance, CMS, Settings and other backend modules must use the same control language.



**### 10.1 Standard input/select**



\- Standard height: 36px; use 40px or more when touch accessibility requires it.

\- Padding: 0 10px.

\- Border: 1px canonical border.

\- Radius: 8px.

\- Background: canonical surface.

\- Text: 12–13px.

\- Placeholder: muted token.

\- Focus: visible primary border and soft focus ring.

\- Disabled/read-only states must be visually distinct and semantically correct.

\- Placeholder is never a substitute for a label.



**### 10.2 Labels, helper text and errors**



\- Label: 11px, weight 600–650.

\- Label-to-control gap: 5–6px.

\- Required marker uses semantic danger color.

\- Helper text: 10–11px, line-height 1.4–1.5.

\- Validation: 10–11px, placed directly under the relevant field.

\- Associate errors with the field using \`aria-describedby\`; use \`aria-invalid="true"\` where appropriate.

\- Error meaning must be communicated in text, not color alone.



**### 10.3 Textarea and grouped fields**



\- Textarea min-height: generally 88–112px depending on expected content.

\- Padding: 10–12px.

\- Font: 12px; line-height 1.5–1.6.

\- Keep resize behavior consistent.

\- Use two-column forms on desktop only where fields benefit from it.

\- Full-width fields span both columns.

\- Collapse forms to one column when space is constrained.

\- Use \`fieldset\` and \`legend\` for logically related controls where appropriate.



**### 10.4 Specialized controls**



Selects, date pickers, checkboxes, radios, switches, file uploads and custom dropdowns must share the same border, radius, typography and focus treatment.



\- Date picker/calendar must use one project-wide design.

\- Dropdown menus need consistent elevation, item height, selected state and keyboard support.

\- File upload/dropzone needs a shared border, dashed/active state, icon tile and helper text.

\- Avoid browser-native \`prompt()\`, \`alert()\` and \`confirm()\` for core workflows; use the standardized dialog system.



**---**



**## 11. Tables and register pages**



The Product Index table is the baseline for compact enterprise data.



\- Header height: approximately 38px.

\- Header text: 9px, weight 700–750; uppercase only where appropriate.

\- Standard product-like row height: approximately 62px.

\- Compact rows may be 48–54px when content permits.

\- Cell text: 10–11px, line-height 1.4.

\- Horizontal cell padding: approximately 10px.

\- Vertical cell padding: approximately 7px.

\- Dividers: 1px canonical border.

\- Row hover: subtle muted surface.

\- Row action: approximately 29px square, 8px radius.

\- Badge: approximately 23px high, 9–10px text.

\- Primary entity name: 11px, weight 600–650.

\- Secondary identifier: 9–10px, muted.

\- Numeric columns: right-aligned with tabular numerals.

\- Dates must use consistent project formatting and timezone behavior.



Use real semantic table markup (\`table\`, \`thead\`, \`tbody\`, \`tr\`, \`th\`, \`td\`) with column headers and \`scope="col"\` as appropriate.



Register features, where supported by the backend and useful to the page:



\- Search.

\- Advanced filters.

\- Sorting.

\- Server-side pagination and page-size selection.

\- Clear filters.

\- Empty, no-results, loading and error states.

\- Bulk selection/actions where safe and authorized.

\- Column visibility where useful.

\- Export where authorized.



Do not move large datasets to client-side rendering merely for UI convenience. Preserve existing server-side filtering, sorting and pagination contracts.



Wide tables must scroll within their own container or use a deliberate mobile representation. Never create page-wide horizontal overflow or clip essential row actions.



**---**



**## 12. Status badges and semantic states**



Use one shared badge component and one centrally documented status map.



\| Meaning | Default semantic family |

\|---|---|

\| Active / success / completed | Green |

\| Pending / attention / draft | Amber or neutral according to business meaning |

\| Failed / cancelled / overdue | Red |

\| Processing / in transit / in progress | Blue or primary |

\| Archived / inactive | Neutral gray |



Badge baseline:



\- Height: approximately 23px.

\- Horizontal padding: 8px.

\- Radius: pill (\`999px\`).

\- Font: 9–10px, weight 600–700.

\- Optional dot: 5–6px.

\- Use short, clear labels.

\- Color must never be the only signal.

\- Identical status values must map consistently throughout the ERP.



**---**



**## 13. Modal, dialog and popup system**



All page dialogs must use a shared modal foundation with standardized size variants. Do not create bespoke modal systems for individual pages.



**### 13.1 General modal behavior**



\- Fixed full-screen backdrop.

\- Centered dialog on desktop.

\- Backdrop uses a dark neutral overlay, approximately 54–64% opacity, with restrained 3–8px blur only where appropriate.

\- Dialog uses canonical surface, border, radius and shadow tokens.

\- Header, body and footer are separate structural regions.

\- Body scrolls independently when content exceeds max height.

\- Footer remains visible/sticky for long dialogs.

\- Close button is positioned consistently and has an accessible name.

\- Escape closes non-destructive dialogs unless the workflow intentionally blocks dismissal.

\- Click-outside closes only when safe and expected.

\- Focus moves into the dialog on open and returns to the trigger on close.

\- Focus remains within the modal while it is open.

\- Prevent background scrolling while modal is open.

\- Use \`role="dialog"\`, \`aria-modal="true"\` and an accessible title; prefer native \`\<dialog>\` only if consistent with the existing implementation architecture.

\- Prevent accidental close while saving when required by the operation.

\- Respect reduced-motion and mobile safe-area insets.

\- Avoid nested modal stacks unless there is a clear workflow requirement.



**### 13.2 Small modal — confirmation/destructive action**



**\*\*Primary references:\*\*** \`Products/Index.vue\` delete modal and \`Inventory/Reservations.vue\` confirmation dialog.



Use for:

\- Delete/archive/cancel confirmation.

\- Release/fulfill confirmation with a few details.

\- Simple consequential action with a short explanation and compact record summary.



Specifications:



\| Property | Standard |

\|---|---|

\| Width | \`min(460px, calc(100vw - 32px))\` |

\| Desktop max-height | 85vh |

\| Padding | 18–20px, or equivalent structured header/body/footer |

\| Radius | 16–18px |

\| Title | 18–20px, weight 700–750 |

\| Description | 11–12px, line-height 1.55–1.65 |

\| Icon tile | 42–46px square, 12–13px radius |

\| Summary block | 10–14px padding, 10px radius |

\| Footer buttons | 36–40px high, 8px gap |



Actions: Cancel secondary + one clearly named semantic primary/danger action. The destructive action must state the consequence; avoid vague labels such as \`OK\`.



Mobile:

\- Full available width with approximately 16px outer gutter.

\- Buttons may stack where necessary.

\- Do not force bottom-sheet behavior for small confirmations unless adopted consistently by the product.



**### 13.3 Medium modal — operational form/workflow**



**\*\*Primary reference:\*\*** \`Inventory/Operations.vue\` transfer receive/issue workflow and adjustment confirmation.  

**\*\*Supporting reference:\*\*** \`Inventory/Index.vue\` reservation modal.



Use for:

\- Stock transfer receive/issue.

\- Adjustment confirmation with summary.

\- Simple add/edit form.

\- Workflow action with several fields and contextual information.



Specifications:



\| Property | Standard |

\|---|---|

\| Width | \`min(560px, calc(100vw - 40px))\` |

\| Max-height | \`min(90dvh, 820px)\` |

\| Radius | 20–22px |

\| Header padding | 20–24px |

\| Body padding | 20–24px |

\| Body section gap | 14–18px |

\| Footer padding | 14–16px 20–24px |

\| Title | 20–21px, weight 750–800 |

\| Description | 12px, line-height 1.55 |

\| Icon tile | 48px square, 14–15px radius |

\| Footer buttons | 42–44px high, 11px radius |



The body may contain summary cards, record/route information, form fields, notices and textareas. Footer stays visible while the body scrolls.



Adjustment confirmation may expand to approximately 680px when structured details require it. Show:

\- A clear quantity/metric summary.

\- A bordered key/value record summary.

\- A semantic note explaining ledger or inventory impact.

\- Distinct cancel and confirm actions.



Mobile:

\- Width 100%; max-height approximately 94dvh.

\- Bottom-sheet alignment is acceptable where it improves the workflow.

\- Radius approximately \`20px 20px 14px 14px\`.

\- Reduce header/body/footer padding to approximately 16px.

\- Buttons become equal-width or stack as necessary.

\- Respect \`env(safe-area-inset-bottom)\`.



**### 13.4 Large modal — complex form/multi-section operation**



**\*\*Primary reference:\*\*** \`Inventory/Index.vue\` structured stock modal.



Use for:

\- Complex stock operations.

\- Multi-section create/edit forms.

\- Main record content plus summary sidebar.

\- Operations with multiple sections and contextual information.



Specifications:



\| Property | Standard |

\|---|---|

\| Desktop width | \`min(1120px, calc(100vw - 48px))\` |

\| Max-height | \`min(92vh, 940px)\` |

\| Radius | 20px |

\| Header padding | 22–24px 26px |

\| Header icon | 58px square, 17px radius |

\| Header title | 22–24px, weight 750 |

\| Body padding | 20–24px |

\| Main/sidebar ratio | approximately 1.7fr / 0.92fr |

\| Sidebar min-width | 270px when viewport permits |

\| Main/sidebar gap | 16px |

\| Footer padding | 13–16px 24px |



\- Main content and summary sidebar collapse to one column when space is insufficient.

\- Footer remains visible and separated by a border.

\- Body scrolls independently.

\- Header includes a clear title, short description and close action.



Mobile:

\- Near-fullscreen sheet/dialog; max-height approximately 96dvh.

\- Radius approximately \`20px 20px 12px 12px\`.

\- Header padding around 15–16px.

\- Body padding around 12–16px.

\- Footer padding around 10–12px plus safe-area inset.

\- Never squeeze a desktop two-column form into a narrow mobile viewport.



**### 13.5 Modal decision matrix**



\| User task | Size |

\|---|---|

\| Confirm delete/archive/cancel | Small |

\| Confirm release/fulfill with a few details | Small |

\| Simple add/edit form | Medium |

\| Stock receive/issue action | Medium |

\| Adjustment confirmation with ledger details | Medium, up to \~680px |

\| Multi-section stock/product operation | Large |

\| Form with main content and summary sidebar | Large |



Do not use a large modal for a one-line confirmation. Do not use a small modal for a complex multi-section form.



**### 13.6 Modal animation**



\- Enter/exit: approximately 160–220ms.

\- Backdrop: opacity fade.

\- Dialog: subtle 6–10px translateY and scale 0.985→1.

\- Use ease-out; no bounce or dramatic zoom.

\- Under reduced motion, remove transform and minimize/disable transition.



**---**



**## 14. Popovers, dropdowns, tooltips and drawers**



These are not modal dialogs and must have distinct interaction patterns.



**### Popover/dropdown**



\- Surface background, canonical border, 10–12px radius, subtle shadow.

\- Menu item height: 34–38px.

\- Padding: 5–6px.

\- Text: 11–12px.

\- Clear hover and selected states.

\- Keyboard arrow navigation where it is a menu/listbox.

\- Escape dismissal.

\- Position to avoid viewport clipping.



**### Tooltip**



\- Compact neutral high-contrast surface.

\- Text: 10–11px.

\- Radius: approximately 6px.

\- Short delay.

\- Never put essential instructions only in a tooltip.

\- Icon-only actions still need accessible names without relying on the tooltip.



**### Detail drawer**



\- Use for quick preview/contextual details when preserving list context matters.

\- Desktop width: approximately 420–520px.

\- Full-width or near-full-width on mobile.

\- Same header/body/footer visual language as dialogs.

\- Do not use drawer and modal interchangeably without a clear UX reason.



**---**



**## 15. Toasts, alerts, loading, empty and error states**



**### Toast**



\- One global toast system for all modules.

\- Consistent global position and stacking.

\- Maximum width: approximately 300–380px.

\- Padding: 11–14px.

\- Radius: 10–12px.

\- Text: 11–12px.

\- Semantic icon and clear message.

\- Success/info may auto-dismiss after a reasonable interval; errors remain visible long enough to read.

\- Avoid duplicate messages for the same operation.



**### Inline alert**



\- Semantic soft background and border.

\- Text: approximately 11px.

\- Padding: 9px 11px.

\- Radius: 9px.

\- Dismiss action where useful.



**### Loading**



\- Use consistent skeletons for cards, tables and forms.

\- Spinner-only feedback is reserved for small immediate actions.

\- Keep layout stable to prevent layout shift.

\- Never leave a blank-looking panel when a request is pending.

\- Loading state should include meaningful context where appropriate, e.g. “Loading products…”.



**### Empty/no-results**



\- Centered icon treatment, approximately 44px.

\- Title: 12–14px, weight 650–700.

\- Description: 10–11px.

\- One relevant action where applicable.

\- Distinguish “no records exist” from “no results match current filters”.



**### Error**



\- Human-readable explanation.

\- Retry action when safe.

\- Never expose stack traces or sensitive backend details.

\- Preserve user-entered form data where possible.

\- Use \`role="alert"\` for urgent errors and \`role="status"\` for routine status messages.



**### Data-driven page states**



Every applicable list/detail/form page must consider:

\- Initial loading.

\- Empty dataset.

\- No matching search/filter results.

\- Error and recovery.

\- Success after create/update/delete.

\- Field validation errors.

\- Disabled/saving state to prevent duplicate submission.

\- Permission-restricted/protected-record state.

\- Responsive presentation of all states.



**---**



**## 16. Responsive behavior**



Use the shared shell breakpoints if they already exist. Do not create competing global breakpoint systems. If no shared system exists, use these recommended behavior bands:



\| Breakpoint | Expected behavior |

\|---|---|

\| 1100px | Reduce KPI columns from 4 to 2; tighten wide/sidebar layouts |

\| 900px | Collapse sidebar/content layouts when needed |

\| 760px | Stack page header/actions; allow toolbars and filters to wrap |

\| 560px | Single-column forms/cards where needed; stack modal actions |

\| 480px | Reduce page title to about 20px; controls wrap or fill available width |



General rules:



\- Avoid fixed widths for the overall page.

\- Use \`min-width: 0\` on flex/grid children.

\- KPI layout changes 4 → 2 → 1 columns as needed.

\- Multi-column forms collapse to one column when constrained.

\- Toolbars wrap; search retains useful width.

\- Tables use controlled horizontal scroll or a deliberate mobile card representation.

\- Never clip essential actions or hide them without an alternative.

\- Touch targets should generally be at least 40–44px on mobile.

\- Modal variants follow their specific mobile rules.

\- Test at 320px viewport width and prevent page-wide horizontal overflow.

\- Test long labels, translated text, empty states and validation errors at narrow widths.



**---**



**## 17. Semantic HTML and accessibility**



Target WCAG 2.2 AA where practical.



\| Purpose | Preferred semantic element |

\|---|---|

\| Page-level top region | \`header\` |

\| Primary page title | \`h1\` |

\| Major content section | \`section\` with accessible heading |

\| Independent metric card | \`article\` where appropriate |

\| Primary page landmark | One \`main\` in the overall page |

\| Related navigation | \`nav\` with \`aria-label\` |

\| Sidebar/complementary content | \`aside\` |

\| Data grid | \`table\` with proper header/body structure |

\| Form workflow | \`form\` |

\| Related form group | \`fieldset\` + \`legend\` where useful |

\| Field name | Associated \`label\` |

\| Routine operation feedback | \`role="status"\` |

\| Urgent error | \`role="alert"\` |

\| Dialog | \`role="dialog"\`, \`aria-modal="true"\`, labelled title |

\| Generic wrapper | \`div\` only when no more meaningful semantic element applies |



Required rules:



1\. Maintain logical heading hierarchy (\`h1\` → \`h2\` → \`h3\`).

2\. Do not choose heading tags merely for their default font size.

3\. Use buttons for actions and links/Inertia \`Link\` for navigation.

4\. Do not nest interactive controls inside other interactive controls.

5\. Use \`scope="col"\` for table column headers and row headers where appropriate.

6\. Give icon-only buttons accessible names.

7\. Keep keyboard focus visible.

8\. Respect \`prefers-reduced-motion\`.

9\. Never use color as the only status, validation or selection signal.

10\. Ensure form errors are associated with controls.

11\. Ensure dialog focus is managed and returned to the invoking control.

12\. Maintain logical tab order and usable hit areas.

13\. Do not rely on hover-only functionality.

14\. Avoid nested page landmarks when the shared ERP shell already supplies them.

15\. Text contrast must meet WCAG AA requirements where practical.



**---**



**## 18. Theme behavior**



\- All shared components must consume semantic theme tokens.

\- Light, dark and system modes must use the same component structure and semantic color roles.

\- Do not hard-code white backgrounds or fixed light-only text colors in theme-aware components.

\- Hover, focus, disabled, selected and status states must remain distinguishable in all supported themes.

\- Do not infer theme colors from screenshots; inspect the existing global theme source.

\- If a theme mode is not currently supported by the application, do not claim it is implemented. Preserve the current architecture and document any limitation.



**---**



**## 19. Shared component architecture**



Create or extend shared components rather than duplicating page-scoped markup and CSS. Adapt names to the existing codebase; do not create duplicate components if equivalent ones already exist.



Suggested inventory:



\- \`PageHeader\`

\- \`Breadcrumbs\`

\- \`PageActions\`

\- \`KpiCard\`

\- \`KpiGrid\`

\- \`SectionCard\`

\- \`AppButton\`

\- \`IconButton\`

\- \`AppInput\`

\- \`AppSelect\`

\- \`AppTextarea\`

\- \`AppDatePicker\`

\- \`AppCheckbox\`

\- \`AppSwitch\`

\- \`DataTable\`

\- \`TableToolbar\`

\- \`FilterPanel\`

\- \`StatusBadge\`

\- \`AppModal\`

\- \`ConfirmDialog\`

\- \`ToastHost\`

\- \`InlineAlert\`

\- \`EmptyState\`

\- \`LoadingSkeleton\`

\- \`DetailDrawer\`

\- \`AppPopover\`

\- \`AppTooltip\`



Suggested modal API concept:



\- \`size: 'sm' | 'md' | 'lg'\`

\- \`title\`

\- \`description\`

\- \`icon\`

\- \`closeable\`

\- \`loading\`

\- \`confirmLabel\`

\- \`cancelLabel\`

\- \`tone: 'default' | 'success' | 'warning' | 'danger'\`

\- Body/footer slots for workflow-specific content.



Use existing Vue, Inertia and state-management conventions. Do not introduce a new UI framework or state-management library solely for this redesign.



**---**



**## 20. Strict implementation instructions for future AI/code work**



When asked to redesign any backend page:



1\. Read this entire document first.

2\. Inspect the current page, parent layout, relevant shared components, styles and related pages before editing.

3\. Identify the page type and select the appropriate layout pattern.

4\. Use \`Products/Index.vue\` as the primary page-level visual reference.

5\. Use the modal reference hierarchy and standardized sizes in this document.

6\. Preserve every existing field, filter, action, permission, route, API request, validation rule, business rule, data contract and workflow unless explicitly asked to change it.

7\. For UI-only work, do not change backend logic, database schema, routes or API behavior.

8\. Do not remove content, columns, KPIs, filters or actions merely to make the page look cleaner.

9\. Use canonical theme tokens and shared components; do not create a new page-specific design language.

10\. Do not introduce page-specific font families or arbitrary type scales.

11\. Ensure controls have real working interactions; do not leave decorative/inert buttons.

12\. Cover loading, empty, no-results, error, disabled, hover, focus and success states as applicable.

13\. Verify keyboard behavior and accessibility for menus, forms and dialogs.

14\. Check supported themes and desktop/tablet/mobile layouts.

15\. Avoid unrelated refactoring and dependency additions.

16\. Run relevant typecheck, build and tests when available.

17\. Report exactly which files changed and which checks were actually run.

18\. Never claim tests/build/runtime verification unless they were executed successfully.



**### Explicit prohibitions**



\- No competing token namespace in new components.

\- No arbitrary page-specific font family.

\- No arbitrary font sizes outside the scale without documented justification.

\- No inconsistent button heights, radii or icon styles for equivalent controls.

\- No bespoke page-only modal system.

\- No native browser \`alert()\`, \`confirm()\` or \`prompt()\` for core workflows.

\- No hard-coded light-only surfaces/text in theme-aware components.

\- No emoji in place of interface icons.

\- No random gradients, excessive shadows, glow or glass effects.

\- No removal of business content or actions for visual simplification.

\- No backend/API/database changes during a UI-only task.

\- No claim of verification without running the relevant command/test.



**---**



**## 21. Redesign acceptance checklist**



A page is accepted only when all relevant checks pass.



**### Visual consistency**

\- [ ] Uses the shared ERP shell and content alignment.

\- [ ] Uses the standard breadcrumb and page-header hierarchy.

\- [ ] Uses canonical typography and spacing scales.

\- [ ] Uses canonical theme tokens.

\- [ ] Uses consistent card borders, radii and shadows.

\- [ ] Uses standard button variants and dimensions.

\- [ ] Uses the existing icon family and standard icon sizing.

\- [ ] Uses the KPI pattern where metrics add value.

\- [ ] Uses consistent table density and status badges.

\- [ ] Uses shared form-control and validation styles.

\- [ ] Uses the correct modal size and structure.



**### Functional preservation**

\- [ ] Existing routes and permissions are preserved.

\- [ ] Existing actions and workflows still work.

\- [ ] Search, filters, sorting and pagination are preserved.

\- [ ] Forms and validation are preserved.

\- [ ] Loading, empty, no-results, error and success states work.

\- [ ] Dialog keyboard and close behavior work.

\- [ ] No dead buttons or fake interactions.



**### Theme, accessibility and responsive**

\- [ ] Supported theme modes are checked.

\- [ ] Focus and contrast are checked.

\- [ ] Form labels and errors are programmatically associated.

\- [ ] Icon-only actions have accessible names.

\- [ ] Keyboard navigation works.

\- [ ] Desktop, tablet and mobile are checked.

\- [ ] Narrow mobile width has no page-level horizontal overflow.

\- [ ] Long labels, empty states and errors do not break the layout.



**### Engineering verification**

\- [ ] TypeScript check passes.

\- [ ] Production build passes.

\- [ ] Relevant tests pass.

\- [ ] No new console/runtime errors.

\- [ ] No unnecessary dependency is added.

\- [ ] No unrelated backend changes are included.



**---**



**## 22. Final source-of-truth statement**



**\*\*\`Products/Index.vue\` defines the primary backend page/register visual language. The Product Create/Edit/Show pages provide workflow and content references. Inventory modal implementations provide practical small, medium and large dialog examples. \`Developer/Modules.vue\` and \`Settings/System.vue\` remain secondary structural and semantic references. This document is the single consolidated contract that governs all of them.\*\***



Every future Madina Scale backend page must use this same design system. Business content and workflow may differ by module; typography, spacing, semantic color roles, controls, cards, buttons, icons, tables, KPI treatment, overlays, modal behavior, accessibility and responsive principles must remain consistent.



**\*\*Do not redesign each module independently. Establish and maintain one shared design system, then apply it consistently across the entire ERP.\*\***
