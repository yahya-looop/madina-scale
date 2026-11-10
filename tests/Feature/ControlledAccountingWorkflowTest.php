<?php

use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use App\Models\Location;
use App\Models\Role;
use App\Models\User;
use App\Services\FinancialLedgerService;
use Database\Seeders\SystemAuthorizationSeeder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    app(SystemAuthorizationSeeder::class)->run();
    $developerRole = Role::query()->where('slug', 'developer')->where('is_system', true)->firstOrFail();

    $this->maker = User::factory()->create();
    $this->approver = User::factory()->create();
    $this->maker->roles()->syncWithoutDetaching([$developerRole->id]);
    $this->approver->roles()->syncWithoutDetaching([$developerRole->id]);

    $this->location = Location::query()->create([
        'name' => 'Controlled Finance Branch',
        'code' => 'CTRL-'.strtoupper(Str::random(6)),
        'type' => 'branch',
        'is_active' => true,
    ]);
});

function controlledAccountingPayload(int $locationId, string $date, bool $includeLocation = true): array
{
    $cash = FinancialAccount::query()->where('code', '1000')->firstOrFail();
    $equity = FinancialAccount::query()->where('code', '3000')->firstOrFail();

    return [
        'entry_date' => $date,
        'fiscal_year' => (int) substr($date, 0, 4),
        ...($includeLocation ? ['location_id' => $locationId] : []),
        'description' => 'Controlled opening / adjustment '.Str::random(6),
        'reference' => 'CTRL-'.Str::upper(Str::random(6)),
        'lines' => [
            ['account_id' => $cash->id, 'debit' => '1000.00', 'credit' => '0.00'],
            ['account_id' => $equity->id, 'debit' => '0.00', 'credit' => '1000.00'],
        ],
    ];
}

it('creates, approves and posts an opening balance with maker checker separation', function (): void {
    $payload = controlledAccountingPayload($this->location->id, now()->toDateString());

    $created = $this->actingAs($this->maker)
        ->postJson('/api/finance/opening-balances', $payload)
        ->assertCreated()
        ->assertJsonPath('data.workflow_type', 'opening_balance')
        ->assertJsonPath('data.status', 'submitted')
        ->assertJsonPath('data.location_id', $this->location->id);

    $entryId = $created->json('data.id');
    $journal = JournalEntry::query()->findOrFail($created->json('data.journal_entry_id'));
    expect(fn () => app(FinancialLedgerService::class)->postControlled($journal, $this->approver, 'opening_balance'))
        ->toThrow(ValidationException::class);

    $this->actingAs($this->maker)
        ->postJson("/api/finance/controlled-accounting/{$entryId}/approve")
        ->assertForbidden();

    $this->actingAs($this->approver)
        ->postJson("/api/finance/controlled-accounting/{$entryId}/approve", ['note' => 'Opening balance checked.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $this->actingAs($this->maker)
        ->postJson("/api/finance/controlled-accounting/{$entryId}/post")
        ->assertForbidden();

    $this->actingAs($this->approver)
        ->postJson("/api/finance/controlled-accounting/{$entryId}/post")
        ->assertOk()
        ->assertJsonPath('data.status', 'posted');

    $this->assertDatabaseHas('journal_entries', [
        'id' => $created->json('data.journal_entry_id'),
        'source' => 'controlled_opening_balance',
        'location_id' => $this->location->id,
        'status' => 'posted',
    ]);
});

it('creates and posts a company-wide adjustment without a location', function (): void {
    $payload = controlledAccountingPayload($this->location->id, now()->toDateString(), false);

    $created = $this->actingAs($this->maker)
        ->postJson('/api/finance/company-adjustments', $payload)
        ->assertCreated()
        ->assertJsonPath('data.workflow_type', 'company_adjustment')
        ->assertJsonPath('data.location_id', null)
        ->assertJsonPath('data.status', 'submitted');

    $entryId = $created->json('data.id');

    $this->actingAs($this->approver)
        ->postJson("/api/finance/controlled-accounting/{$entryId}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $this->actingAs($this->approver)
        ->postJson("/api/finance/controlled-accounting/{$entryId}/post")
        ->assertOk()
        ->assertJsonPath('data.status', 'posted');

    $journalId = $created->json('data.journal_entry_id');
    $this->assertDatabaseHas('journal_entries', [
        'id' => $journalId,
        'source' => 'controlled_company_adjustment',
        'location_id' => null,
        'status' => 'posted',
    ]);
    $this->assertDatabaseMissing('journal_entry_lines', [
        'journal_entry_id' => $journalId,
        'location_id' => $this->location->id,
    ]);
});

it('prevents operational posting of controlled journals and prevents duplicate opening balances', function (): void {
    $payload = controlledAccountingPayload($this->location->id, now()->toDateString());
    $created = $this->actingAs($this->maker)->postJson('/api/finance/opening-balances', $payload)->assertCreated();
    $journal = JournalEntry::query()->findOrFail($created->json('data.journal_entry_id'));

    expect(fn () => app(FinancialLedgerService::class)->post($journal, $this->approver))
        ->toThrow(ValidationException::class);

    $this->actingAs($this->maker)
        ->postJson('/api/finance/opening-balances', $payload)
        ->assertUnprocessable();
});

it('rejects unbalanced controlled accounting entries before submission', function (): void {
    $payload = controlledAccountingPayload($this->location->id, now()->toDateString());
    $payload['lines'][1]['credit'] = '999.99';

    $this->actingAs($this->maker)
        ->postJson('/api/finance/opening-balances', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('lines');
});

it('allows an independent approver to reject a submitted opening balance and permits a corrected resubmission', function (): void {
    $payload = controlledAccountingPayload($this->location->id, now()->toDateString());

    $created = $this->actingAs($this->maker)
        ->postJson('/api/finance/opening-balances', $payload)
        ->assertCreated();
    $entryId = $created->json('data.id');

    $this->actingAs($this->approver)
        ->postJson("/api/finance/controlled-accounting/{$entryId}/reject", ['note' => 'Please reconcile the source balance.'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');

    $this->assertDatabaseHas('journal_entries', [
        'id' => $created->json('data.journal_entry_id'),
        'status' => 'draft',
    ]);

    $this->actingAs($this->maker)
        ->postJson('/api/finance/opening-balances', $payload)
        ->assertCreated();
});

it('does not allow users without company adjustment permission to create company-wide entries', function (): void {
    $ordinaryUser = User::factory()->create();

    $this->actingAs($ordinaryUser)
        ->postJson('/api/finance/company-adjustments', controlledAccountingPayload($this->location->id, now()->toDateString(), false))
        ->assertForbidden();
});
