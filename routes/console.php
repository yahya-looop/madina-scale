<?php

use App\Jobs\ProcessInventoryTransferNotificationOutbox;
use App\Models\CmsContentSection;
use App\Models\CmsMedia;
use App\Models\CmsPage;
use App\Models\CmsPageRevision;
use App\Models\InventoryTransferNotificationOutbox;
use App\Models\ProductMedia;
use App\Services\InventoryReservationService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('inventory:expire-reservations', function (InventoryReservationService $reservations): void {
    $reservations->expireDue();
    $this->info('Expired inventory reservations reconciled.');
})->purpose('Release stock held by expired inventory reservations');

Artisan::command('inventory:dispatch-transfer-notifications {--limit=100}', function (): void {
    $limit = max(1, min(500, (int) $this->option('limit')));
    $staleBefore = now()->subMinutes(5);
    $ids = InventoryTransferNotificationOutbox::query()
        ->where(function ($query) use ($staleBefore): void {
            $query->where(function ($pending): void {
                $pending->where('status', 'pending')
                    ->where(fn ($due) => $due->whereNull('available_at')->orWhere('available_at', '<=', now()));
            })->orWhere(function ($stale) use ($staleBefore): void {
                $stale->where('status', 'processing')->where('updated_at', '<=', $staleBefore);
            });
        })
        ->orderBy('id')
        ->limit($limit)
        ->pluck('id');

    foreach ($ids as $id) {
        try {
            ProcessInventoryTransferNotificationOutbox::dispatch((int) $id);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    $this->info($ids->count().' inventory transfer notification(s) queued.');
})->purpose('Dispatch pending inventory transfer notification outbox records');

Schedule::command('inventory:expire-reservations')->everyMinute()->withoutOverlapping();
Schedule::command('inventory:dispatch-transfer-notifications')->everyMinute()->withoutOverlapping();

Artisan::command('media:audit', function (): void {
    $disk = Storage::disk('public');

    $extractMediaPaths = function (mixed $value) use (&$extractMediaPaths): array {
        if (is_string($value)) {
            $path = ltrim(parse_url($value, PHP_URL_PATH) ?: $value, '/');
            $path = preg_replace('#^storage/#', '', $path);

            return is_string($path)
                && (str_starts_with($path, 'cms/media/') || str_starts_with($path, 'products/'))
                ? [$path]
                : [];
        }

        if (! is_array($value)) {
            return [];
        }

        $paths = [];
        foreach ($value as $item) {
            $paths = array_merge($paths, $extractMediaPaths($item));
        }

        return $paths;
    };

    $trackedPaths = collect()
        ->merge(CmsMedia::query()->pluck('path'))
        ->merge(CmsPage::query()->whereNotNull('featured_image')->pluck('featured_image'))
        ->merge(ProductMedia::query()
            ->whereNull('deleted_at')
            ->pluck('path'))
        ->merge(CmsPageRevision::query()->get(['snapshot'])
            ->flatMap(fn ($revision) => $extractMediaPaths($revision->snapshot)))
        ->merge(CmsContentSection::query()->get(['content'])
            ->flatMap(fn ($section) => $extractMediaPaths($section->content)))
        ->filter(fn ($path): bool => is_string($path) && $path !== '')
        ->unique()
        ->values();

    $managedFiles = collect($disk->allFiles('cms/media'))
        ->merge($disk->allFiles('products'))
        ->unique()
        ->values();

    $trackedLookup = array_fill_keys($trackedPaths->all(), true);

    $missingFiles = $trackedPaths
        ->filter(fn (string $path): bool => ! $disk->exists($path))
        ->values();

    $untrackedFiles = $managedFiles
        ->filter(fn (string $path): bool => ! isset($trackedLookup[$path]))
        ->values();

    $this->info('Public media storage audit (read-only)');
    $this->line('Tracked database paths: '.$trackedPaths->count());
    $this->line('Managed storage files: '.$managedFiles->count());
    $this->line('Database paths with missing files: '.$missingFiles->count());
    $this->line('Untracked file candidates: '.$untrackedFiles->count());

    if ($missingFiles->isNotEmpty()) {
        $this->warn('Database paths with missing files:');
        foreach ($missingFiles as $path) {
            $this->line('  - '.$path);
        }
    }

    if ($untrackedFiles->isNotEmpty()) {
        $this->warn('Untracked file candidates (review references before any deletion):');
        foreach ($untrackedFiles as $path) {
            $this->line('  - '.$path);
        }
    }

    if ($missingFiles->isEmpty() && $untrackedFiles->isEmpty()) {
        $this->info('No media inconsistencies found in the scanned directories.');
    }
})->purpose('Read-only audit of CMS and product public media files; never deletes data');

Artisan::command('inventory:reconcile', function (): int {
    $summary = DB::selectOne(<<<'SQL'
        WITH latest AS (
            SELECT DISTINCT ON (location_id, product_id, product_variant_id)
                id,
                location_id,
                product_id,
                product_variant_id,
                balance_after
            FROM stock_movements
            ORDER BY location_id, product_id, product_variant_id, id DESC
        ),
        stock_drift AS (
            SELECT stock.id
            FROM inventory_stocks AS stock
            LEFT JOIN latest
                ON latest.location_id = stock.location_id
                AND latest.product_id IS NOT DISTINCT FROM stock.product_id
                AND latest.product_variant_id IS NOT DISTINCT FROM stock.product_variant_id
            WHERE
                (latest.id IS NULL AND stock.quantity <> 0)
                OR (latest.id IS NOT NULL AND stock.quantity <> latest.balance_after)
        ),
        orphan_ledger AS (
            SELECT latest.id
            FROM latest
            LEFT JOIN inventory_stocks AS stock
                ON stock.location_id = latest.location_id
                AND stock.product_id IS NOT DISTINCT FROM latest.product_id
                AND stock.product_variant_id IS NOT DISTINCT FROM latest.product_variant_id
            WHERE stock.id IS NULL
        ),
        ordered_movements AS (
            SELECT
                id,
                balance_before,
                LAG(balance_after) OVER (
                    PARTITION BY location_id, product_id, product_variant_id
                    ORDER BY id
                ) AS previous_balance
            FROM stock_movements
        ),
        broken_chains AS (
            SELECT id
            FROM ordered_movements
            WHERE balance_before <> COALESCE(previous_balance, 0)
        )
        SELECT
            (SELECT COUNT(*) FROM stock_drift) AS stock_drift_count,
            (SELECT COUNT(*) FROM orphan_ledger) AS orphan_ledger_count,
            (SELECT COUNT(*) FROM broken_chains) AS broken_chain_count
    SQL);

    $stockDrift = (int) ($summary->stock_drift_count ?? 0);
    $orphanLedger = (int) ($summary->orphan_ledger_count ?? 0);
    $brokenChains = (int) ($summary->broken_chain_count ?? 0);
    $total = $stockDrift + $orphanLedger + $brokenChains;

    $this->info('Inventory ledger reconciliation (read-only)');
    $this->line('Stock balance mismatches: '.$stockDrift);
    $this->line('Ledger balances without matching stock rows: '.$orphanLedger);
    $this->line('Broken movement balance chains: '.$brokenChains);

    if ($total > 0) {
        $this->error('Inventory inconsistencies detected. No records were changed.');

        return 1;
    }

    $this->info('Inventory stock balances and movement ledger are consistent.');

    return 0;
})->purpose('Read-only reconciliation of inventory balances against immutable movement history');
