<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('financial_accounts')->updateOrInsert(['code' => '4050'], [
            'name' => 'Sales Returns & Allowances', 'type' => 'income', 'normal_balance' => 'credit',
            'is_system' => true, 'is_cash_bank' => false, 'is_active' => true,
            'description' => 'Contra-revenue account debited when a sales return is accepted.',
            'updated_at' => now(), 'created_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Preserve the account if it has been used by a posted return journal.
        $used = DB::table('journal_entry_lines')->where('account_id', function ($q): void {
            $q->select('id')->from('financial_accounts')->where('code', '4050')->limit(1);
        })->exists();
        if (! $used) {
            DB::table('financial_accounts')->where('code', '4050')->delete();
        }
    }
};
