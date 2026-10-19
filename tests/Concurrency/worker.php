<?php

use App\Models\InventoryTransfer;
use App\Models\User;
use App\Services\InventoryReservationService;
use App\Services\InventoryService;
use App\Services\InventoryTransferService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Validation\ValidationException;

require dirname(__DIR__, 2) . '/vendor/autoload.php';
$app = require dirname(__DIR__, 2) . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$encoded = $argv[1] ?? '';
$payload = json_decode((string) base64_decode($encoded, true), true);
if (! is_array($payload)) {
    fwrite(STDERR, "Invalid worker payload.\n");
    exit(64);
}

// A filesystem barrier makes the parent start multiple independent PHP/DB
// processes together, rather than merely issuing sequential service calls.
$barrier = $payload['barrier'] ?? null;
if (is_string($barrier) && $barrier !== '') {
    $deadline = microtime(true) + 20;
    while (! is_file($barrier) && microtime(true) < $deadline) {
        usleep(10_000);
    }
    if (! is_file($barrier)) {
        fwrite(STDERR, "Timed out waiting for concurrency barrier.\n");
        exit(65);
    }
}

try {
    $actor = User::query()->findOrFail((int) $payload['actor_id']);
    $result = match ($payload['operation'] ?? '') {
        'dispatch' => app(InventoryTransferService::class)->dispatch($payload['data'], $actor),
        'receive' => app(InventoryTransferService::class)->receive(
            InventoryTransfer::query()->findOrFail((int) $payload['transfer_id']),
            $payload['data'] ?? [],
            $actor,
        ),
        'issue' => app(InventoryService::class)->issue($payload['data'], $actor),
        'reserve' => app(InventoryReservationService::class)->reserve($payload['data'], $actor),
        default => throw new InvalidArgumentException('Unsupported concurrency operation.'),
    };

    echo json_encode(['ok' => true, 'id' => $result->id ?? null], JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(0);
} catch (ValidationException $exception) {
    echo json_encode(['ok' => false, 'kind' => 'validation', 'errors' => $exception->errors()], JSON_THROW_ON_ERROR) . PHP_EOL;
    exit(2);
} catch (Throwable $exception) {
    fwrite(STDERR, get_class($exception) . ': ' . $exception->getMessage() . PHP_EOL);
    exit(1);
}
