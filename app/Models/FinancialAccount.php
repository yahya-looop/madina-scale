<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FinancialAccount extends Model
{
    protected $fillable = [
        'parent_id', 'code', 'name', 'type', 'normal_balance',
        'is_system', 'is_cash_bank', 'is_active', 'description',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_cash_bank' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<FinancialAccount, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<FinancialAccount, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /** @return HasMany<JournalEntryLine, $this> */
    public function journalLines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class, 'account_id');
    }
}
