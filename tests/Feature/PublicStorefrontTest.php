<?php

use App\Models\CmsPage;
use App\Models\FeatureFlag;
use App\Models\ProductMedia;
use App\Models\FlashSaleItem;
use App\Models\FlashSale;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\Unit;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

function createStorefrontProduct(array $overrides = []): Product
{
    $category = ProductCategory::firstOrCreate(
        ['slug' => 'scales'],
        ['name' => 'Scales', 'status' => 'active', 'visibility' => 'visible'],
    );

    $unit = Unit::firstOrCreate(
        ['slug' => 'piece'],
        ['name' => 'Piece', 'short_name' => 'pcs', 'status' => 'active'],
    );

    $attributes = array_merge([
        'category_id' => $category->id,
        'unit_id' => $unit->id,
        'name' => 'Platform Scale',
        'slug' => 'platform-scale',
        'product_type' => 'variable',
        'sku' => null,
        'short_description' => 'A platform weighing scale.',
        'status' => 'active',
        'visibility' => 'visible',
    ], $overrides);

    $productType = $attributes['product_type'];
    $slug = $attributes['slug'];

    if ($productType === 'variable') {
        $attributes['sku'] = null;
        $attributes['barcode'] = null;
    }

    if ($productType === 'simple' && empty($attributes['sku'])) {
        $attributes['sku'] = 'TEST-'.strtoupper(str_replace('-', '', $slug));
    }

    $product = Product::create($attributes);

    if ($productType === 'variable') {
        ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Standard',
            'sku' => 'VAR-'.strtoupper(str_replace('-', '', $slug)),
            'cost_price' => '500.00',
            'selling_price' => '1200.00',
            'wholesale_price' => '1000.00',
            'is_default' => true,
            'status' => 'active',
        ]);
    }

    return $product;
}

it('serves only active visible products through the Nuxt catalogue API', function (): void {
    createStorefrontProduct();
    createStorefrontProduct(['name' => 'Draft Scale', 'slug' => 'draft-scale', 'status' => 'draft']);
    createStorefrontProduct(['name' => 'Hidden Scale', 'slug' => 'hidden-scale', 'visibility' => 'hidden']);

    $this->getJson('/api/v1/storefront/products')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'Platform Scale')
        ->assertJsonPath('data.0.price', '1200.00')
        ->assertJsonPath('data.0.has_wholesale_pricing', true);
});

it('serves product detail through the Nuxt catalogue API and hides unpublished products', function (): void {
    createStorefrontProduct();
    createStorefrontProduct(['name' => 'Hidden Scale', 'slug' => 'hidden-scale', 'visibility' => 'hidden']);

    $this->getJson('/api/v1/storefront/products/platform-scale')
        ->assertOk()
        ->assertJsonPath('data.name', 'Platform Scale')
        ->assertJsonPath('data.variants.0.price', '1200.00')
        ->assertJsonPath('data.variants.0.has_wholesale_pricing', true);

    $this->getJson('/api/v1/storefront/products/hidden-scale')->assertNotFound();
});

it('serves published CMS page data through the storefront API', function (): void {
    CmsPage::query()->create([
        'title' => 'About Madina Scale',
        'slug' => 'about',
        'content' => '<p>About our company.</p>',
        'status' => 'published',
        'published_at' => now()->subMinute(),
        'robots_index' => true,
        'robots_follow' => true,
    ]);

    $this->getJson('/api/v1/storefront/cms/pages/about')
        ->assertOk()
        ->assertJsonPath('data.page.slug', 'about')
        ->assertJsonPath('data.page.seo.canonical', url('/about'));
});

it('supports a guest session cart through the Nuxt cart API', function (): void {
    $product = createStorefrontProduct();
    $variant = $product->activeVariants()->firstOrFail();

    $this->postJson('/api/v1/storefront/cart/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 2,
    ])->assertCreated()
        ->assertJsonPath('data.item_count', 2);

    $this->getJson('/api/v1/storefront/cart')
        ->assertOk()
        ->assertJsonPath('data.items.0.variant_id', $variant->id)
        ->assertJsonPath('data.items.0.quantity', 2);
});

it('rejects a new cart line after the 100-item session limit', function (): void {
    $product = createStorefrontProduct();
    $variant = $product->activeVariants()->firstOrFail();
    $existingCart = array_fill_keys(range(900001, 900100), 1);

    $this->withSession(['storefront_cart' => $existingCart])
        ->postJson('/api/v1/storefront/cart/items', [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('cart');
});

it('validates Bangladesh mobile numbers before accepting a storefront order', function (): void {
    $this->postJson('/api/v1/storefront/checkout', [
        'customer_name' => 'Test Customer',
        'customer_phone' => '12345',
        'district' => 'Dhaka',
        'address_line' => 'Test address',
        'idempotency_key' => 'test-storefront-invalid-phone',
    ])->assertUnprocessable()->assertJsonValidationErrors('customer_phone');
});

it('places a guest storefront order through the Nuxt checkout API and defers stock reservation', function (): void {
    $product = createStorefrontProduct();
    $variant = $product->activeVariants()->firstOrFail();
    Location::create([
        'name' => 'Main Branch',
        'code' => 'MAIN-STORE',
        'type' => 'branch',
        'is_active' => true,
        'is_primary' => true,
        'country_code' => 'BD',
    ]);

    $this->postJson('/api/v1/storefront/cart/items', [
        'product_variant_id' => $variant->id,
        'quantity' => 1,
    ])->assertCreated();

    $this->postJson('/api/v1/storefront/checkout', [
        'customer_phone' => '০১৭১২৩৪৫৬৭৮',
        'idempotency_key' => 'storefront-phone-only-checkout-001',
    ])->assertCreated()
        ->assertJsonPath('data.customer_phone', '01712345678');

    $order = SalesOrder::query()->where('channel', 'storefront')->latest('id')->firstOrFail();
    expect($order->status)->toBe('pending')
        ->and($order->customer_phone)->toBe('01712345678')
        ->and($order->created_by)->toBeNull()
        ->and($order->items()->count())->toBe(1)
        ->and($order->items()->first()?->reservation_id)->toBeNull();
});

it('does not expose customer account data to guests', function (): void {
    $this->getJson('/api/v1/storefront/account')->assertUnauthorized();
    $this->getJson('/api/v1/storefront/auth/me')->assertUnauthorized();
});

it('does not register retired Laravel customer-facing page routes', function (): void {
    foreach ([
        'storefront.home',
        'storefront.products.index',
        'storefront.cart',
        'storefront.checkout',
        'storefront.order-success',
        'storefront.flash-sales',
        'storefront.show',
        'storefront.pages.show',
        'storefront.pages.legacy',
        'customer.login',
        'customer.account',
        'customer.logout',
    ] as $routeName) {
        expect(Route::has($routeName))->toBeFalse();
    }

    $this->get('/')->assertNotFound();
    $this->get('/cart')->assertNotFound();
    $this->get('/checkout')->assertNotFound();
});


it('keeps public catalogue browsing available when checkout is disabled', function (): void {
    createStorefrontProduct();

    FeatureFlag::query()->updateOrCreate(
        ['key' => 'storefront.checkout.enabled'],
        [
            'name' => 'Storefront checkout',
            'is_enabled' => false,
            'rollout_percentage' => 100,
            'environment' => 'all',
            'is_system' => true,
        ],
    );

    $this->getJson('/api/v1/storefront/categories')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'scales');

    $this->getJson('/api/v1/storefront/products')
        ->assertOk()
        ->assertJsonPath('data.0.slug', 'platform-scale');
});


it('includes the approved primary product image in public flash-sale items', function (): void {
    $product = createStorefrontProduct();
    $variant = $product->activeVariants()->firstOrFail();

    ProductMedia::query()->create([
        'product_id' => $product->id,
        'disk' => 'public',
        'path' => 'products/platform-scale-primary.jpg',
        'collection' => 'images',
        'original_name' => 'platform-scale-primary.jpg',
        'mime_type' => 'image/jpeg',
        'size' => 1200,
        'width' => 800,
        'height' => 800,
        'alt_text' => 'Platform Scale',
        'sort_order' => 0,
        'is_primary' => true,
    ]);

    $sale = FlashSale::query()->create([
        'title' => 'Test Flash Sale',
        'slug' => 'test-flash-sale',
        'status' => 'active',
        'accent_color' => '#087A32',
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDay(),
        'show_on_storefront' => true,
        'priority' => 1,
    ]);

    FlashSaleItem::query()->create([
        'flash_sale_id' => $sale->id,
        'product_variant_id' => $variant->id,
        'regular_price' => 1200,
        'sale_price' => 1000,
        'quantity_limit' => null,
        'quantity_sold' => 0,
        'sort_order' => 0,
    ]);

    $this->getJson('/api/v1/storefront/flash-sales')
        ->assertOk()
        ->assertJsonPath('data.0.items.0.product_slug', 'platform-scale')
        ->assertJsonPath('data.0.items.0.product_variant_id', $variant->id)
        ->assertJsonPath('data.0.items.0.image_url', Storage::disk('public')->url('products/platform-scale-primary.jpg'));
});
