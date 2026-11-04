<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('supplier_code', 40)->unique();
            $table->string('name', 180);
            $table->string('legal_name', 180)->nullable();
            $table->string('email', 180)->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('tax_number', 80)->nullable();
            $table->string('contact_person', 120)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 100)->nullable();
            $table->string('country', 100)->nullable();
            $table->string('payment_terms', 120)->nullable();
            $table->unsignedSmallInteger('credit_days')->default(0);
            $table->string('status', 16)->default('active');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['status', 'name']);
        });

        Schema::create('purchase_requisitions', function (Blueprint $table): void {
            $table->id();
            $table->string('requisition_number', 40)->unique();
            $table->foreignId('location_id')->constrained('locations')->restrictOnDelete();
            $table->string('status', 20)->default('draft');
            $table->text('purpose')->nullable();
            $table->date('required_by')->nullable();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->foreignId('converted_purchase_order_id')->nullable();
            $table->timestampsTz();
            $table->index(['location_id', 'status', 'created_at']);
        });

        Schema::create('purchase_requisition_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_requisition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->text('notes')->nullable();
            $table->timestampsTz();
        });

        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->string('po_number', 40)->unique();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_requisition_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('status', 24)->default('draft');
            $table->date('order_date');
            $table->date('expected_at')->nullable();
            $table->string('currency', 3)->default('BDT');
            $table->decimal('subtotal', 16, 2)->default(0);
            $table->decimal('discount_total', 16, 2)->default(0);
            $table->decimal('tax_total', 16, 2)->default(0);
            $table->decimal('grand_total', 16, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();
            $table->timestampsTz();
            $table->index(['supplier_id', 'status']);
            $table->index(['location_id', 'order_date']);
        });

        Schema::table('purchase_requisitions', function (Blueprint $table): void {
            $table->foreign('converted_purchase_order_id')->references('id')->on('purchase_orders')->nullOnDelete();
        });

        Schema::create('purchase_order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('description', 255)->nullable();
            $table->decimal('quantity', 18, 4);
            $table->decimal('received_quantity', 18, 4)->default(0);
            $table->decimal('accepted_quantity', 18, 4)->default(0);
            $table->decimal('unit_cost', 16, 2);
            $table->decimal('discount_amount', 16, 2)->default(0);
            $table->decimal('tax_amount', 16, 2)->default(0);
            $table->decimal('line_total', 16, 2);
            $table->timestampsTz();
        });

        Schema::create('goods_receipts', function (Blueprint $table): void {
            $table->id();
            $table->string('grn_number', 40)->unique();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->date('received_date');
            $table->string('status', 16)->default('posted');
            $table->string('idempotency_key', 100)->unique();
            $table->char('payload_hash', 64);
            $table->foreignId('journal_entry_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->string('delivery_note', 120)->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('received_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->index(['purchase_order_id', 'received_date']);
        });

        Schema::create('goods_receipt_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('goods_receipt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained()->restrictOnDelete();
            $table->decimal('received_quantity', 18, 4);
            $table->decimal('accepted_quantity', 18, 4);
            $table->decimal('rejected_quantity', 18, 4)->default(0);
            $table->string('rejection_reason', 255)->nullable();
            $table->foreignId('stock_movement_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestampsTz();
        });

        Schema::create('supplier_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->string('invoice_number', 100);
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('subtotal', 16, 2);
            $table->decimal('tax_total', 16, 2)->default(0);
            $table->decimal('discount_total', 16, 2)->default(0);
            $table->decimal('grand_total', 16, 2);
            $table->decimal('paid_total', 16, 2)->default(0);
            $table->string('status', 20)->default('draft');
            $table->foreignId('journal_entry_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('posted_at')->nullable();
            $table->timestampsTz();
            $table->unique(['supplier_id', 'invoice_number']);
            $table->index(['supplier_id', 'status', 'due_date']);
        });

        Schema::create('supplier_invoice_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_invoice_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 16, 2);
            $table->decimal('line_total', 16, 2);
            $table->timestampsTz();
            $table->unique(['supplier_invoice_id', 'purchase_order_item_id']);
        });

        Schema::create('supplier_payments', function (Blueprint $table): void {
            $table->id();
            $table->string('payment_number', 40)->unique();
            $table->foreignId('supplier_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->date('payment_date');
            $table->decimal('amount', 16, 2);
            $table->string('payment_method', 24);
            $table->foreignId('cash_account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->unique()->constrained()->restrictOnDelete();
            $table->string('reference', 120)->nullable();
            $table->string('idempotency_key', 100)->unique();
            $table->char('payload_hash', 64);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
            $table->index(['supplier_id', 'payment_date']);
        });

        Schema::create('supplier_returns', function (Blueprint $table): void {
            $table->id();
            $table->string('return_number', 40)->unique();
            $table->foreignId('supplier_invoice_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->date('return_date');
            $table->decimal('total_amount', 16, 2);
            $table->string('status', 16)->default('posted');
            $table->string('idempotency_key', 100)->unique();
            $table->char('payload_hash', 64);
            $table->text('reason');
            $table->foreignId('journal_entry_id')->nullable()->unique()->constrained()->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestampsTz();
        });

        Schema::create('supplier_return_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchase_order_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 16, 2);
            $table->decimal('line_total', 16, 2);
            $table->timestampsTz();
        });

        Schema::table('journal_entry_lines', function (Blueprint $table): void {
            $table->foreignId('supplier_id')->nullable()->after('customer_id')->constrained()->restrictOnDelete();
            $table->index(['supplier_id', 'account_id']);
        });

        DB::statement('ALTER TABLE purchase_requisition_items ADD CONSTRAINT purchase_requisition_items_quantity_check CHECK (quantity > 0)');
        DB::statement('ALTER TABLE purchase_requisition_items ADD CONSTRAINT purchase_requisition_items_owner_check CHECK ((product_id IS NOT NULL AND product_variant_id IS NULL) OR (product_id IS NULL AND product_variant_id IS NOT NULL))');
        DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_quantity_check CHECK (quantity > 0 AND received_quantity >= 0 AND accepted_quantity >= 0 AND accepted_quantity <= quantity AND unit_cost > 0 AND line_total > 0)');
        DB::statement('ALTER TABLE purchase_order_items ADD CONSTRAINT purchase_order_items_owner_check CHECK ((product_id IS NOT NULL AND product_variant_id IS NULL) OR (product_id IS NULL AND product_variant_id IS NOT NULL))');
        DB::statement('ALTER TABLE goods_receipt_items ADD CONSTRAINT goods_receipt_items_quantity_check CHECK (received_quantity > 0 AND accepted_quantity >= 0 AND rejected_quantity >= 0 AND accepted_quantity + rejected_quantity = received_quantity)');
        DB::statement('CREATE UNIQUE INDEX goods_receipt_items_line_unique ON goods_receipt_items (goods_receipt_id, purchase_order_item_id)');
        DB::statement('ALTER TABLE supplier_invoices ADD CONSTRAINT supplier_invoices_amount_check CHECK (subtotal >= 0 AND tax_total >= 0 AND discount_total >= 0 AND grand_total > 0 AND paid_total >= 0 AND paid_total <= grand_total)');
        DB::statement('ALTER TABLE supplier_invoice_items ADD CONSTRAINT supplier_invoice_items_quantity_check CHECK (quantity > 0 AND unit_cost > 0 AND line_total > 0)');
        DB::statement('ALTER TABLE supplier_payments ADD CONSTRAINT supplier_payments_amount_check CHECK (amount > 0)');
        DB::statement('ALTER TABLE supplier_return_items ADD CONSTRAINT supplier_return_items_quantity_check CHECK (quantity > 0 AND line_total > 0)');

        $now = now();
        DB::table('financial_accounts')->updateOrInsert(
            ['code' => '1200'],
            [
                'name' => 'Inventory Asset',
                'type' => 'asset',
                'normal_balance' => 'debit',
                'is_system' => true,
                'is_cash_bank' => false,
                'is_active' => true,
                'description' => 'Inventory asset recognised through supplier invoices and supplier returns.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('financial_accounts')->updateOrInsert(
            ['code' => '2050'],
            [
                'name' => 'Goods Received Not Invoiced',
                'type' => 'liability',
                'normal_balance' => 'credit',
                'is_system' => true,
                'is_cash_bank' => false,
                'is_active' => true,
                'description' => 'Accrual account for accepted goods received before supplier invoice posting.',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        Schema::table('journal_entry_lines', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('supplier_id');
        });
        Schema::table('purchase_requisitions', function (Blueprint $table): void {
            $table->dropForeign(['converted_purchase_order_id']);
        });
        foreach ([
            'supplier_return_items', 'supplier_returns', 'supplier_payments',
            'supplier_invoice_items', 'supplier_invoices', 'goods_receipt_items', 'goods_receipts',
            'purchase_order_items', 'purchase_orders',
            'purchase_requisition_items', 'purchase_requisitions', 'suppliers',
        ] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
