<?php

use App\Models\FinancialAccount;
use App\Models\InventoryStock;
use App\Models\SalesOrder;
use App\Models\SalesOrderFulfillment;
use App\Models\SalesOrderFulfillmentItem;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Services\LocationInventoryAccessService;
use App\Services\SalesReturnService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

function salesReturnFixture(): array
{
    $user = User::factory()->create();
    $location = inventoryTestLocation('return-'.strtolower(Str::random(6)));
    $user->locations()->attach($location->id, ['access_level' => 'manage']);
    $product = inventoryTestProduct('variable');
    $variant = inventoryTestVariant($product);
    $order = SalesOrder::query()->create([
        'order_number' => 'RET-'.strtoupper(Str::random(8)), 'status' => 'completed', 'channel' => 'backoffice',
        'location_id' => $location->id, 'customer_name' => 'Return Customer', 'currency' => 'BDT',
        'subtotal' => '200.00', 'discount_total' => '0.00', 'tax_total' => '0.00', 'grand_total' => '200.00', 'created_by' => $user->id,
    ]);
    $item = SalesOrderItem::query()->create([
        'sales_order_id' => $order->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id,
        'product_name' => $product->name, 'variant_name' => $variant->name, 'sku' => $variant->sku,
        'quantity' => '10.0000', 'unit_price' => '20.00', 'line_total' => '200.00',
    ]);
    $fulfillment = SalesOrderFulfillment::query()->create([
        'fulfillment_number' => 'FUL-'.strtoupper(Str::random(8)), 'sales_order_id' => $order->id,
        'status' => 'completed', 'created_by' => $user->id, 'fulfilled_at' => now(),
    ]);
    SalesOrderFulfillmentItem::query()->create(['sales_order_fulfillment_id' => $fulfillment->id, 'sales_order_item_id' => $item->id, 'quantity' => '10.0000']);
    InventoryStock::query()->create(['product_variant_id' => $variant->id, 'location_id' => $location->id, 'quantity' => '0.0000', 'reserved_quantity' => '0.0000']);
    return compact('user', 'location', 'product', 'variant', 'order', 'item');
}

test('sales return restores fulfilled stock and posts a balanced refund journal', function () {
    ['user' => $user, 'location' => $location, 'order' => $order, 'item' => $item, 'variant' => $variant] = salesReturnFixture();
    $cash = FinancialAccount::query()->where('code', '1000')->firstOrFail();
    $return = app(SalesReturnService::class)->create([
        'sales_order_id' => $order->id, 'idempotency_key' => (string) Str::uuid(), 'return_date' => now()->toDateString(),
        'refund_method' => 'cash', 'cash_account_id' => $cash->id, 'items' => [['sales_order_item_id' => $item->id, 'quantity' => '2.0000']],
    ], $user);
    $stock = InventoryStock::query()->where('product_variant_id', $variant->id)->where('location_id', $location->id)->firstOrFail();
    expect($return->status)->toBe('completed')
        ->and((string) $stock->quantity)->toBe('2.0000')
        ->and($return->journalEntry->status)->toBe('posted')
        ->and((string) $return->refund_amount)->toBe('40.00')
        ->and(bcadd((string) $return->journalEntry->lines->sum('debit'), '0', 2))->toBe('40.00')
        ->and(bcadd((string) $return->journalEntry->lines->sum('credit'), '0', 2))->toBe('40.00');
});

test('sales return cannot exceed fulfilled quantity less previous returns', function () {
    ['user' => $user, 'order' => $order, 'item' => $item] = salesReturnFixture();
    $cash = FinancialAccount::query()->where('code', '1000')->firstOrFail();
    $service = app(SalesReturnService::class);
    $service->create([
        'sales_order_id' => $order->id, 'idempotency_key' => (string) Str::uuid(), 'return_date' => now()->toDateString(),
        'refund_method' => 'cash', 'cash_account_id' => $cash->id, 'items' => [['sales_order_item_id' => $item->id, 'quantity' => '7.0000']],
    ], $user);
    expect(fn () => $service->create([
        'sales_order_id' => $order->id, 'idempotency_key' => (string) Str::uuid(), 'return_date' => now()->toDateString(),
        'refund_method' => 'cash', 'cash_account_id' => $cash->id, 'items' => [['sales_order_item_id' => $item->id, 'quantity' => '4.0000']],
    ], $user))->toThrow(ValidationException::class);
});

test('location access scope accepts query builder used by finance aggregates', function () {
    $user = User::factory()->create();
    $visible = inventoryTestLocation('finance-visible-'.strtolower(Str::random(4)));
    $hidden = inventoryTestLocation('finance-hidden-'.strtolower(Str::random(4)));
    $user->locations()->attach($visible->id, ['access_level' => 'view']);
    $rows = app(LocationInventoryAccessService::class)->scopeViewable(DB::table('locations'), $user, 'locations.id')->pluck('id')->all();
    expect($rows)->toContain($visible->id)->not->toContain($hidden->id);
});
