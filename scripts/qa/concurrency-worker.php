<?php

declare(strict_types=1);
require __DIR__.'/bootstrap.php';
use App\Models\User;
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Inventory\Actions\ReceiveStockTransferAction;
use App\Modules\Platform\Actions\DecideApprovalSource;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Pricing\Actions\ApprovePriceProposalAction;
use App\Modules\Pricing\Models\PriceVersion;
use App\Modules\Retail\Actions\CapturePaymentAction;
use App\Modules\Retail\Actions\OpenShiftAction;
use App\Modules\Retail\Actions\RetailReturnAction;
use App\Modules\Retail\Actions\SubmitBlindShiftCloseAction;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\RetailReturn;
use App\Modules\Retail\Models\Sale;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

$p = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR);
Auth::login(User::findOrFail($p['actor']));
$dir = dirname($argv[1]);
$slot = $p['slot'];
file_put_contents($dir.'/'.$slot.'.ready', (string) DB::selectOne('SELECT CONNECTION_ID() AS id')->id);
$deadline = microtime(true) + 15;
while (! is_file($dir.'/go')) {
    if (microtime(true) > $deadline) {
        throw new RuntimeException('Race start timeout');
    }usleep(10000);
}
file_put_contents($dir.'/'.$slot.'.entered', (string) microtime(true));
try {
    $result = match ($p['op']) {
        'product' => app(SaveProductAction::class)->execute($p['data']),
        'stock' => app(PostInventoryMovement::class)->execute($p['product'], $p['store'], $p['quantity'], 'qa_phase2', null, $p['key']),
        'payment' => app(CapturePaymentAction::class)->execute(Auth::user(), Sale::findOrFail($p['sale']), PaymentMethod::findOrFail($p['method']), $p['amount'], $p['key']),
        'open' => app(OpenShiftAction::class)->execute(Auth::user(), CashDrawer::findOrFail($p['drawer']), '100', $p['key']),
        'return' => app(RetailReturnAction::class)->create(Auth::user(), $p['data'], $p['key']),
        'refund' => app(RetailReturnAction::class)->complete(Auth::user(), RetailReturn::findOrFail($p['return']), $p['key']),
        'approve' => app(DecideApprovalSource::class)->approve(ApprovalRecord::findOrFail($p['approval'])),
        'receive' => app(ReceiveStockTransferAction::class)->execute($p['transfer'], $p['lines'], null, null),
        'price' => app(ApprovePriceProposalAction::class)->execute(PriceVersion::findOrFail($p['version'])),
        'close' => app(SubmitBlindShiftCloseAction::class)->execute(Auth::user(), PosShift::findOrFail($p['shift']), $p['actual'], [], $p['key']),
        default => throw new RuntimeException('Unknown race operation'),
    };
    $id = is_object($result) ? ($result->id ?? $result->submissionId ?? null) : null;
    $out = ['status' => 'success', 'id' => $id];
} catch (InvalidArgumentException|ValidationException $e) {
    $out = ['status' => 'rejected', 'class' => get_class($e), 'message' => $e->getMessage()];
} catch (Throwable $e) {
    $out = ['status' => 'error', 'class' => get_class($e), 'message' => $e->getMessage()];
}
file_put_contents($dir.'/'.$slot.'.result.json', json_encode($out, JSON_THROW_ON_ERROR));
echo json_encode($out,JSON_THROW_ON_ERROR),PHP_EOL;
exit($out['status'] === 'error' ? 1 : 0);
