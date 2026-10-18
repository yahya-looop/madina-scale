# Storefront frontend module

This directory contains reusable storefront-specific frontend code.

- `components/`: product cards, galleries, navigation, cart and checkout UI components.
- `layouts/`: public storefront shell, header, footer and responsive navigation.
- `composables/`: reusable Vue state and interaction logic.
- `services/`: typed API/data-access functions.
- `types/`: storefront domain and API types.
- `styles/`: storefront-only styles and design tokens.
- `assets/`: storefront-specific source assets.
- `config/`: storefront UI configuration and constants.

Keep Inertia-resolved page entry points in `resources/js/pages/Storefront/` unless the existing page resolver is deliberately updated and tested. This avoids an unnecessary resolver change during this structure-only preparation.
