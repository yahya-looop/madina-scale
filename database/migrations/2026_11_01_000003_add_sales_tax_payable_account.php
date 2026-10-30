<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('financial_accounts')->updateOrInsert(['code' => '2200'], [
            'name' => 'Sales Tax Payable', 'type' => 'liability', 'normal_balance' => 'credit',
            'is_system' => true, 'is_cash_bank' => false, 'is_active' => true,
            'description' => 'Tax liability account debited for tax reversals on accepted sales returns.',
            'updated_at' => now(), 'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        $used = DB::table('journal_entry_lines')->where('account_id', function ($q): void {
            $q->select('id')->from('financial_accounts')->where('code', '2200')->limit(1);
        })->exists();
        if (! $used) DB::table('financial_accounts')->where('code', '2200')->delete();
    }
};
