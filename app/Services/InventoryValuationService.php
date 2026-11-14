<?php

namespace App\Services;

use App\Models\InventoryStock;
use App\Models\InventoryValuationMovement;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Moving weighted-average inventory costing with an append-only valuation ledger. */
class InventoryValuationService
{
    /** @param array<string, mixed> $metadata */
    public function apply(
        InventoryStock $stock,
        StockMovement $movement,
        string $unitCost = '0',
        ?User $actor = null,
        ?string $idempotencyKey = null,
        array $metadata = [],
    ): InventoryValuationMovement {
        $key = $idempotencyKey ?? (string) Str::uuid();
        $existing = InventoryValuationMovement::query()->where('idempotency_key', $key)->first();
        if ($existing) {
            if ((int) $existing->stock_movement_id !== (int) $movement->id) {
                throw ValidationException::withMessages(['idempotency_key' => 'Valuation key is already linked to another stock movement.']);
            }
            return $existing;
        }

        if ($stock->valuation_status !== 'valued' || $stock->inventory_value === null) {
            throw ValidationException::withMessages([
                'valuation' => 'This stock has no verified opening valuation. Complete its opening valuation before moving stock.',
            ]);
        }

        $qtyDelta = (string) $movement->quantity_delta;
        $qtyBefore = (string) $movement->balance_before;
        $qtyAfter = (string) $movement->balance_after;
        $valueBefore = (string) $stock->inventory_value;
        $average = (string) ($stock->average_unit_cost ?? '0');

        if (bccomp($qtyDelta, '0', 4) > 0) {
            if (bccomp($unitCost, '0', 6) < 0) {
                throw ValidationException::withMessages(['unit_cost' => 'Receipt unit cost cannot be negative.']);
            }
            $valueDelta = bcdiv(bcmul($qtyDelta, $unitCost, 8), '1', 2);
            $valueAfter = bcadd($valueBefore, $valueDelta, 2);
            $newAverage = bccomp($qtyAfter, '0', 4) > 0
                ? bcdiv(bcmul($valueAfter, '1', 8), $qtyAfter, 6)
                : '0.000000';
            $ledgerUnitCost = $unitCost;
        } else {
            $issueQty = bcmul($qtyDelta, '-1', 4);
            $valueDelta = bcsub('0', bcdiv(bcmul($issueQty, $average, 8), '1', 2), 2);
            $valueAfter = bcadd($valueBefore, $valueDelta, 2);
            if (bccomp($valueAfter, '0', 2) < 0) {
                throw ValidationException::withMessages(['valuation' => 'Issue would make inventory carrying value negative. Reconcile opening valuation first.']);
            }
            if (bccomp($qtyAfter, '0', 4) === 0) {
                $valueAfter = '0.00';
                $newAverage = '0.000000';
            } else {
                $newAverage = $average;
            }
            $ledgerUnitCost = $average;
        }

        $entry = InventoryValuationMovement::query()->create([
            'inventory_stock_id' => $stock->id,
            'stock_movement_id' => $movement->id,
            'movement_type' => $qtyDelta[0] === '-' ? 'issue' : ($movement->movement_type === 'opening' ? 'opening' : 'receipt'),
            'quantity_delta' => $qtyDelta,
            'unit_cost' => $ledgerUnitCost,
            'value_delta' => $valueDelta,
            'quantity_before' => $qtyBefore,
            'quantity_after' => $qtyAfter,
            'value_before' => $valueBefore,
            'value_after' => $valueAfter,
            'reference_type' => $movement->reference_type,
            'reference_id' => $movement->reference_id,
            'idempotency_key' => $key,
            'created_by' => $actor?->id,
            'metadata' => $metadata,
        ]);

        $stock->inventory_value = $valueAfter;
        $stock->average_unit_cost = $newAverage;
        $stock->valuation_status = 'valued';
        $stock->save();

        return $entry;
    }

    /** Set verified opening value for existing positive stock; never infer it. */
    public function setOpeningValue(InventoryStock $stock, string $totalValue, User $actor, string $reason): InventoryValuationMovement
    {
        return DB::transaction(function () use ($stock, $totalValue, $actor, $reason): InventoryValuationMovement {
            /** @var InventoryStock $locked */
            $locked = InventoryStock::query()->whereKey($stock->id)->lockForUpdate()->firstOrFail();
            if ($locked->valuation_status === 'valued' || bccomp((string) $locked->quantity, '0', 4) <= 0) {
                throw ValidationException::withMessages(['valuation' => 'Opening valuation applies only to positive stock awaiting valuation.']);
            }
            if (bccomp($totalValue, '0', 2) <= 0 || trim($reason) === '') {
                throw ValidationException::withMessages(['total_value' => 'Enter a positive verified value and an audit reason.']);
            }
            $average = bcdiv($totalValue, (string) $locked->quantity, 6);
            $entry = InventoryValuationMovement::query()->create([
                'inventory_stock_id' => $locked->id, 'movement_type' => 'opening',
                'quantity_delta' => '0.0000', 'unit_cost' => $average,
                'value_delta' => $totalValue, 'quantity_before' => $locked->quantity,
                'quantity_after' => $locked->quantity, 'value_before' => '0.00',
                'value_after' => $totalValue, 'reference_type' => 'opening_valuation',
                'reference_id' => (string) $locked->id, 'idempotency_key' => (string) Str::uuid(),
                'created_by' => $actor->id, 'metadata' => ['reason' => $reason],
            ]);
            $locked->inventory_value = $totalValue;
            $locked->average_unit_cost = $average;
            $locked->valuation_status = 'valued';
            $locked->save();
            return $entry;
        }, 3);
    }
}
