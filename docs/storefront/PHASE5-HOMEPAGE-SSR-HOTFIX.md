# Phase 5 Homepage SSR Hotfix

## Root cause
`storefront/app/pages/index.vue` fetched categories as `categoryResponse`, but
its `powerToolsCategory` and `ladderCategory` computed properties referenced
`categories.value` without declaring `categories`. During Nuxt server-side
rendering this raised `ReferenceError: categories is not defined` and returned
HTTP 500.

## Correction
A typed computed value now maps the API response to a safe array:

```ts
const categories = computed<Category[]>(() => categoryResponse.data.value?.data ?? [])
```

The existing Power Tools and Ladder category matching now uses this declared
computed value. Empty or unavailable category data safely resolves to an empty
array; it does not create fake categories.

## Validation performed
- All 19 Vue Single File Components parsed.
- All 19 script-setup blocks compiled.
- All 19 templates compiled.
- Homepage TypeScript syntax transpilation passed.
- The corrected Homepage SFC passed individual parse, script and template compilation.

## Scope
This hotfix changes only the Homepage Vue file. It does not alter Laravel routes,
controllers, database, `.env`, dependencies, `node_modules`, or lockfiles.

A full Nuxt production build and live Laravel API integration test were not run
in this review environment. They should be verified in the project's supported
Node environment and local Laravel runtime.
