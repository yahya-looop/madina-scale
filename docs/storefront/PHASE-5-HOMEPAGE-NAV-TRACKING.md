# Phase 5 — Homepage navigation and order tracking

## Scope

- Removed the Homepage's “Shop by Main Category” and “Browse by Digital Scale Category” content sections. Category discovery remains in the global All Categories mega-menu and mobile grouped navigation.
- Reworked the utility bar to include Track Order and an EN / বাংলা language selector.
- Added the requested primary navigation: Home, Products, Special Offers, Flash Sales, Warranty and Contact.
- Rebuilt the desktop category mega-menu and mobile category accordion using the Laravel category tree supplied to the shared layout.
- Corrected search action alignment using a centered grid control.
- Added a floating support launcher. It reads only public contact settings; a WhatsApp action appears only when the ERP support channel `chat` and a contact phone are configured. Otherwise the panel offers Track Order, Warranty and Contact shortcuts and explains that direct chat is not configured.
- Replaced the Homepage Track Order placeholder with Order Number / Tracking Number + checkout mobile number fields.
- Added `/track-order` with an order progress timeline, item summary and shipment/courier references.
- Added a public tracking endpoint secured by the order's checkout phone number, a generic mismatch response, `Cache-Control: no-store`, and `throttle:5,1`.

## API contract

`POST /api/v1/storefront/orders/track`

Request JSON:

```json
{
  "reference": "<order_number_or_courier_tracking_code>",
  "phone": "<mobile_number_used_at_checkout>"
}
```

Successful response returns only order number, status, timestamps, item names/variants/quantities, order status history and shipment tracking status/events. Customer identity/contact/address, order notes, prices, internal IDs and courier raw payloads are not returned.

The lookup accepts an order number or a shipment tracking code. Bangladesh country-code notation (`+880...`) is normalized to local `0...` notation before comparing with the stored checkout phone number.

## Support contact contract

`GET /api/v1/storefront/support/contact` returns only the active public `general.contact_phone`, `general.contact_email` and supported `general.support_channels` values. The frontend does not invent a phone number or email address.

## Verification performed in the build environment

- PHP syntax: routes, both new controllers and the new feature test passed `php -l`.
- Vue SFC parsing, script compilation and template compilation: 8 included storefront Vue files passed.
- TypeScript syntax parsing: included storefront composable passed.
- CSS/PostCSS parse: passed (799 rules).
- Full Laravel feature-test execution and full Nuxt production build were not run in this environment; the patch does not claim those runtime tests passed.

## Follow-up

- Complete full-site EN/BN localization, including CMS/product localized content and all page validation/error strings.
- Configure the real support phone and `chat` channel in ERP settings to enable WhatsApp chat from the support launcher.
- Publish CMS pages with slugs `warranty`, `special-offers` and `contact`.
- Run `php artisan test --filter=PublicOrderTrackingTest` and `npm run typecheck` / `npm run build` in the user's Windows environment after replacement.
