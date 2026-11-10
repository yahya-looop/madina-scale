<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ControlledAccountingEntry extends Model
{
    protected $fillable = [
        'journal_entry_id', 'workflow_type', 'fiscal_year', 'location_id', 'status',
        'created_by', 'approved_by', 'posted_by', 'rejected_by', 'approval_note',
        'submitted_at', 'approved_at', 'posted_at', 'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<User, $this> */
    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }
}
