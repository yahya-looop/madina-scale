# Storefront Demo Courier Selection

This storefront-only implementation uses illustrative provider data. It does not call courier APIs or create real shipments.

## Checkout payload contract (future Laravel integration)

The checkout request currently includes:

- `customer_preferred_courier_code`: selected provider code, or `null` when the customer chooses No Preference.
- `delivery_selection_mode`: `customer_choice` or `no_preference`.
- `delivery_zone`: `inside_dhaka`, `outside_dhaka`, or `remote_special`.
- `estimated_parcel_weight_grams`: storefront demo estimate only.
- `demo_delivery_quote`: provider code/name, estimated charge, delivery window and `is_demo: true`.

Laravel must not trust these client-supplied amounts or weight values. The backend must recalculate weight, serviceability and rates from authoritative product/variant and delivery configuration before confirming an order.

## Demo provider codes

- `pathao`
- `redx`
- `carrybee`
- `bangladesh_post`

The rates, zone multipliers, service features, availability and delivery estimates in `useDemoDeliveryOptions.ts` are sample values for UI testing only. Brand marks are text-based labels, not official logos.

## Future assignment policy

- Before shipment booking: Admin can change the assigned provider with a reason and audit log.
- After shipment booking: changes must follow cancel/rebook workflow, with booking state reconciliation to prevent duplicate consignments.
- Keep customer preference, system recommendation and final assigned provider as separate backend fields.
