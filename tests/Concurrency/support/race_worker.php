<?php

declare(strict_types=1);

/**
 * Standalone worker process for real-OS-process concurrency proofs.
 *
 * This deliberately does NOT run inside PHPUnit/Pest: PHPUnit is a single
 * process, and a single PHP process cannot hold two overlapping, uncommitted
 * transactions against the same row at once. Two genuinely concurrent
 * requests (the thing QA-014 / the Critical-concurrency gate needs proof of)
 * require two real OS processes racing against the same MariaDB connection.
 * The orchestrating PHPUnit test (../*.php) launches two of these via
 * Symfony\Process and inspects the resulting DB state afterward.
 *
 * Usage: php race_worker.php <scenario> '<json params>'
 * Always exits 0 and prints one JSON line to STDOUT — business-logic
 * failures (validation, insufficient stock, etc.) are captured as data,
 * not treated as a fatal process error, so the orchestrating test can
 * assert on outcomes for BOTH racers.
 */

use App\Models\User;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Platform\Actions\DecideApprovalSource;
use App\Modules\Platform\Actions\RequestApproval;
use App\Modules\Platform\Actions\SaveBranchSellingStoreMappingAction;
use App\Modules\Platform\Data\ApprovalRequestData;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Store;
use App\Modules\Pricing\Actions\ApprovePriceProposalAction;
use App\Modules\Pricing\Models\PriceVersion;
use App\Modules\Purchasing\Actions\AllocatePurchaseOrderNumberAction;
use App\Modules\Retail\Actions\OpenShiftAction;
use App\Modules\Retail\Actions\RetailSaleAction;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Auth;

require __DIR__.'/../../../vendor/autoload.php';

/** @var Application $app */
$app = require __DIR__.'/../../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$scenario = $argv[1] ?? null;
$params = json_decode($argv[2] ?? '{}', true, 512, JSON_THROW_ON_ERROR);

try {
    $result = match ($scenario) {
        'movement' => raceMovement($params),
        'po_number' => racePoNumber($params),
        'price_approve' => racePriceApprove($params),
        'sale' => raceSale($params),
        'branch_mapping' => raceBranchMapping($params),
        'shift_open' => raceShiftOpen($params),
        'approval_request' => raceApprovalRequest($params),
        'shift_decision' => raceShiftDecision($params),
        default => throw new InvalidArgumentException("Unknown race scenario: {$scenario}"),
    };
    fwrite(STDOUT, json_encode(['ok' => true, 'result' => $result]).PHP_EOL);
} catch (Throwable $e) {
    fwrite(STDOUT, json_encode([
        'ok' => false,
        'exception' => get_class($e),
        'message' => $e->getMessage(),
    ]).PHP_EOL);
}

exit(0);

function raceMovement(array $p): array
{
    if (isset($p['user_id'])) {
        Auth::setUser(User::query()->findOrFail($p['user_id']));
    }

    $movement = app(PostInventoryMovement::class)->execute(
        (int) $p['product_id'],
        (int) $p['store_id'],
        (string) $p['quantity'],
        (string) $p['movement_type'],
        isset($p['unit_cost']) ? (string) $p['unit_cost'] : null,
        (string) $p['idempotency_key'],
        $p['source_type'] ?? null,
        isset($p['source_id']) ? (int) $p['source_id'] : null,
        isset($p['source_line_id']) ? (int) $p['source_line_id'] : null,
        (bool) ($p['allow_negative'] ?? false),
    );

    return [
        'movement_id' => $movement->id,
        'quantity' => (string) $movement->quantity,
        'idempotency_key' => $movement->idempotency_key,
    ];
}

/**
 * docs/32 §6/§16 - two cashiers racing to open a shift on the same drawer.
 * Exactly one must win; the loser must be rejected, not silently create a
 * second active shift on the same drawer.
 */
function raceShiftOpen(array $p): array
{
    $user = User::query()->findOrFail((int) $p['user_id']);
    Auth::setUser($user);

    $shift = app(OpenShiftAction::class)->execute(
        $user,
        CashDrawer::query()->findOrFail((int) $p['cash_drawer_id']),
        (string) $p['opening_float'],
        (string) $p['idempotency_key'],
    );

    return ['shift_id' => $shift->id, 'cashier_id' => (int) $shift->cashier_id];
}

function racePoNumber(array $p): array
{
    if (isset($p['user_id'])) {
        Auth::setUser(User::query()->findOrFail($p['user_id']));
    }

    $number = app(AllocatePurchaseOrderNumberAction::class)->execute();

    return ['number' => $number];
}

function raceApprovalRequest(array $p): array
{
    Auth::setUser(User::query()->findOrFail((int) $p['user_id']));
    $approval = app(RequestApproval::class)->execute(new ApprovalRequestData(
        sourceType: (string) $p['source_type'],
        sourceId: (string) $p['source_id'],
        sourceVersion: (string) $p['source_version'],
        requestedAction: (string) $p['requested_action'],
        requestPermission: (string) $p['request_permission'],
        branchId: (int) $p['branch_id'],
        storeId: isset($p['store_id']) ? (int) $p['store_id'] : null,
        idempotencyKey: (string) $p['idempotency_key'],
    ));

    return ['approval_id' => $approval->id, 'uuid' => $approval->uuid, 'state' => $approval->approval_state->value];
}

/** A real central-inbox decision made by an isolated PHP process. */
function raceShiftDecision(array $p): array
{
    Auth::setUser(User::query()->findOrFail((int) $p['user_id']));
    $approval = ApprovalRecord::query()->findOrFail((int) $p['approval_id']);

    if (($p['decision'] ?? 'approve') === 'recount') {
        app(DecideApprovalSource::class)->reject($approval, (string) ($p['reason'] ?? 'Concurrent recount verification.'));
    } else {
        app(DecideApprovalSource::class)->approve($approval);
    }

    return [
        'approval_id' => $approval->id,
        'state' => $approval->fresh()->approval_state->value,
    ];
}

function racePriceApprove(array $p): array
{
    Auth::setUser(User::query()->findOrFail($p['user_id']));
    $version = PriceVersion::query()->findOrFail($p['version_id']);
    $approved = app(ApprovePriceProposalAction::class)->execute($version);

    return [
        'version_id' => $approved->id,
        'state' => $approved->state->value,
    ];
}

function raceSale(array $p): array
{
    $cashier = User::query()->findOrFail($p['user_id']);
    $store = Store::query()->findOrFail($p['store_id']);

    $sale = app(RetailSaleAction::class)->create(
        $cashier,
        $store,
        $p['lines'],
        (string) $p['idempotency_key'],
        (bool) ($p['suspend'] ?? false),
    );

    return [
        'sale_id' => $sale->id,
        'status' => $sale->status,
        'document_number' => $sale->document_number,
    ];
}

function raceBranchMapping(array $p): array
{
    Auth::setUser(User::query()->findOrFail($p['user_id']));
    $mapping = app(SaveBranchSellingStoreMappingAction::class)->execute(
        (int) $p['branch_id'],
        (int) $p['store_id'],
        'Concurrency race fixture.',
    );

    return ['mapping_id' => $mapping->id, 'store_id' => $mapping->store_id, 'status' => $mapping->status];
}
