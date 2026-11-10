<?php

use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\Unit;
use Inertia\Testing\AssertableInertia as Assert;

function createStorefrontProduct(array $overrides = []): Product
{
    $category = ProductCategory::firstOrCreate(
        ['slug' => 'scales'],
        [
            'name' => 'Scales',
            'status' => 'active',
            'visibility' => 'visible',
        ],
    );

    $unit = Unit::firstOrCreate(
        ['slug' => 'piece'],
        [
            'name' => 'Piece',
            'short_name' => 'pcs',
            'status' => 'active',
        ],
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

    // Variable products keep identifiers on their variants.
    if ($productType === 'variable') {
        $attributes['sku'] = null;
        $attributes['barcode'] = null;
    }

    // Active simple products must have their own SKU.
    if ($productType === 'simple' && empty($attributes['sku'])) {
        $attributes['sku'] = 'TEST-'.strtoupper(
            str_replace('-', '', $slug)
        );
    }

    $product = Product::create($attributes);

    // Only variable products may have variants.
    if ($productType === 'variable') {
        ProductVariant::create([
            'product_id' => $product->id,
            'name' => 'Standard',
            'sku' => 'VAR-'.strtoupper(
                str_replace('-', '', $slug)
            ),
            'cost_price' => '500.00',
            'selling_price' => '1200.00',
            'wholesale_price' => '1000.00',
            'is_default' => true,
            'status' => 'active',
        ]);
    }

    return $product;
}

it('shows only active visible products on the public storefront', function (): void {
    createStorefrontProduct();

    createStorefrontProduct([
        'name' => 'Draft Scale',
        'slug' => 'draft-scale',
        'status' => 'draft',
    ]);

    createStorefrontProduct([
        'name' => 'Hidden Scale',
        'slug' => 'hidden-scale',
        'visibility' => 'hidden',
    ]);

    $this->get('/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/Index')
            ->has('products.data', 1)
            ->where('products.data.0.name', 'Platform Scale')
            ->where('products.data.0.price', '1200.00')
            ->where('products.data.0.has_wholesale_pricing', true)
        );
});

it('shows public product details by slug and does not expose hidden products', function (): void {
    createStorefrontProduct();

    createStorefrontProduct([
        'name' => 'Hidden Scale',
        'slug' => 'hidden-scale',
        'visibility' => 'hidden',
    ]);

    $this->get('/shop/platform-scale')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/Show')
            ->where('product.name', 'Platform Scale')
            ->where('product.variants.0.price', '1200.00')
            ->where('product.variants.0.has_wholesale_pricing', true)
        );

    $this->get('/shop/hidden-scale')->assertNotFound();
});

it('allows guests to add a public product to a session cart without signing in', function (): void {
    $product = createStorefrontProduct();
    $variant = $product->activeVariants()->firstOrFail();

    $this->post(route('storefront.cart.add'), [
        'product_variant_id' => $variant->id,
        'quantity' => 2,
    ])->assertRedirect();

    $this->get(route('storefront.cart'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Storefront/Cart')
            ->has('items', 1)
            ->where('items.0.variant_id', $variant->id)
            ->where('items.0.quantity', 2)
        );
});

it('rejects adding a new cart line after the 100-item session limit', function (): void {
    $product = createStorefrontProduct();
    $variant = $product->activeVariants()->firstOrFail();
    $existingCart = array_fill_keys(range(900001, 900100), 1);

    $this->withSession(['storefront_cart' => $existingCart])
        ->from(route('storefront.cart'))
        ->post(route('storefront.cart.add'), [
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ])
        ->assertRedirect(route('storefront.cart'))
        ->assertSessionHasErrors('cart');
});

it('validates Bangladesh mobile numbers before accepting a storefront order', function (): void {
    $this->from(route('storefront.cart'))
        ->post(route('storefront.checkout'), [
            'customer_name' => 'Test Customer',
            'customer_phone' => '12345',
            'district' => 'Dhaka',
            'address_line' => 'Test address',
            'idempotency_key' => 'test-storefront-invalid-phone',
        ])
        ->assertSessionHasErrors('customer_phone');
});

it('places a guest storefront order using only a Bangladesh mobile number and defers stock reservation', function (): void {
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

    $this->post(route('storefront.cart.add'), [
        'product_variant_id' => $variant->id,
        'quantity' => 1,
    ])->assertRedirect();

    $this->post(route('storefront.checkout'), [
        'customer_phone' => '০১৭১২৩৪৫৬৭৮',
        'idempotency_key' => 'storefront-phone-only-checkout-001',
    ])->assertRedirect(route('storefront.order-success'));

    $order = SalesOrder::query()->where('channel', 'storefront')->latest('id')->firstOrFail();
    expect($order->status)->toBe('pending')
        ->and($order->customer_phone)->toBe('01712345678')
        ->and($order->created_by)->toBeNull()
        ->and($order->items()->count())->toBe(1)
        ->and($order->items()->first()?->reservation_id)->toBeNull();
});
