<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_accounts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('financial_accounts')->restrictOnDelete();
            $table->string('code', 24)->unique();
            $table->string('name', 160);
            $table->string('type', 24); // asset, liability, equity, income, expense
            $table->string('normal_balance', 6); // debit or credit
            $table->boolean('is_system')->default(false);
            $table->boolean('is_cash_bank')->default(false);
            $table->boolean('is_active')->default(true);
            $table->text('description')->nullable();
            $table->timestampsTz();
            $table->index(['type', 'is_active']);
            $table->index(['is_cash_bank', 'is_active']);
        });

        Schema::create('journal_entries', function (Blueprint $table): void {
            $table->id();
            $table->string('entry_number', 40)->unique();
            $table->date('entry_date');
            $table->string('description', 500);
            $table->string('status', 16)->default('draft'); // draft, posted, reversed
            $table->string('source', 32)->default('manual');
            $table->string('reference', 120)->nullable();
            $table->foreignId('location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('journal_entries')->restrictOnDelete();
            $table->string('idempotency_key', 100)->nullable()->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('posted_at')->nullable();
            $table->timestampsTz();
            $table->index(['entry_date', 'status']);
            $table->index(['location_id', 'entry_date']);
        });

        Schema::create('journal_entry_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('journal_entry_id')->constrained('journal_entries')->cascadeOnDelete();
            $table->foreignId('account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->foreignId('location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->string('description', 255)->nullable();
            $table->decimal('debit', 16, 2)->default(0);
            $table->decimal('credit', 16, 2)->default(0);
            $table->timestampsTz();
            $table->index(['account_id', 'location_id']);
            $table->index(['customer_id', 'account_id']);
        });

        Schema::create('financial_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('transaction_number', 40)->unique();
            $table->string('type', 24); // income, expense, customer_receipt
            $table->date('transaction_date');
            $table->decimal('amount', 16, 2);
            $table->string('payment_method', 24); // cash, bank, mobile_banking, card, other
            $table->foreignId('cash_account_id')->constrained('financial_accounts')->restrictOnDelete();
            $table->foreignId('category_account_id')->nullable()->constrained('financial_accounts')->restrictOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('customers')->restrictOnDelete();
            $table->foreignId('journal_entry_id')->unique()->constrained('journal_entries')->restrictOnDelete();
            $table->string('reference', 120)->nullable();
            $table->string('description', 500);
            $table->string('idempotency_key', 100)->unique();
            $table->char('payload_hash', 64);
            $table->foreignId('location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['type', 'transaction_date']);
            $table->index(['location_id', 'transaction_date']);
            $table->index(['customer_id', 'transaction_date']);
        });

        $now = now();
        $accounts = [
            ['code'=>'1000','name'=>'Cash on Hand','type'=>'asset','normal_balance'=>'debit','cash'=>true],
            ['code'=>'1010','name'=>'Bank Account','type'=>'asset','normal_balance'=>'debit','cash'=>true],
            ['code'=>'1020','name'=>'Mobile Banking','type'=>'asset','normal_balance'=>'debit','cash'=>true],
            ['code'=>'1100','name'=>'Accounts Receivable','type'=>'asset','normal_balance'=>'debit','cash'=>false],
            ['code'=>'2000','name'=>'Accounts Payable','type'=>'liability','normal_balance'=>'credit','cash'=>false],
            ['code'=>'2100','name'=>'Customer Advances','type'=>'liability','normal_balance'=>'credit','cash'=>false],
            ['code'=>'3000','name'=>'Owner Equity','type'=>'equity','normal_balance'=>'credit','cash'=>false],
            ['code'=>'4000','name'=>'Sales Revenue','type'=>'income','normal_balance'=>'credit','cash'=>false],
            ['code'=>'4100','name'=>'Other Operating Income','type'=>'income','normal_balance'=>'credit','cash'=>false],
            ['code'=>'5000','name'=>'Cost of Goods Sold','type'=>'expense','normal_balance'=>'debit','cash'=>false],
            ['code'=>'6000','name'=>'Operating Expenses','type'=>'expense','normal_balance'=>'debit','cash'=>false],
            ['code'=>'6100','name'=>'Bank & Payment Charges','type'=>'expense','normal_balance'=>'debit','cash'=>false],
        ];
        foreach ($accounts as $account) {
            DB::table('financial_accounts')->updateOrInsert(
                ['code' => $account['code']],
                [
                    'name' => $account['name'],
                    'type' => $account['type'],
                    'normal_balance' => $account['normal_balance'],
                    'is_system' => true,
                    'is_cash_bank' => $account['cash'],
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_transactions');
        Schema::dropIfExists('journal_entry_lines');
        Schema::dropIfExists('journal_entries');
        Schema::dropIfExists('financial_accounts');
    }
};
