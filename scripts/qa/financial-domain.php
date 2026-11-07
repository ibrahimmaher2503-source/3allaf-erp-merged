<?php

declare(strict_types=1);
require __DIR__.'/bootstrap.php';
require __DIR__.'/fixture.php';
require __DIR__.'/phase2-fixture.php';
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockTransfer;
use App\Modules\Platform\Models\ApprovalRecord;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\TaxSetting;
use App\Modules\Pricing\Actions\CreatePriceProposalAction;
use App\Modules\Pricing\Actions\SubmitPriceProposalAction;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Pricing\Services\EffectivePriceResolver;
use App\Modules\Purchasing\Actions\SavePurchaseDistributionAction;
use App\Modules\Purchasing\Actions\SavePurchaseInvoiceAction;
use App\Modules\Purchasing\Actions\SubmitPurchaseInvoiceAction;
use App\Modules\Purchasing\Models\PurchaseInvoice;
use App\Modules\Retail\Actions\CapturePaymentAction;
use App\Modules\Retail\Actions\RetailReturnAction;
use App\Modules\Retail\Actions\RetailSaleAction;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\RetailReturn;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\ShiftClosingSubmission;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

$f = qaFixture();
$journal = getenv('QA_STORAGE').'/q2-completed.json';
$done = is_file($journal) ? json_decode(file_get_contents($journal), true) : [];
$cases = [];
$cases['shift-open'] = function () use ($f) {
    $base = ['op' => 'open', 'drawer' => $f['drawer']->id];
    $x = q2Race($f, 'shift-open', 'SELECT id FROM cash_drawers WHERE id=? FOR UPDATE', [$f['drawer']->id], [$base + ['key' => 'q2-open-a'], $base + ['key' => 'q2-open-b']]);
    q2Outcomes($x, 1, 1, 'shift-open');
    qaAssert(DB::table('active_pos_shift_assignments')->where('cash_drawer_id', $f['drawer']->id)->count() === 1, 'one active drawer/cashier assignment');
    qaAssert(PosShift::where('cash_drawer_id', $f['drawer']->id)->count() === 1, 'one shift persisted');
};
$cases['item-code'] = function () use ($f) {
    q2Product($f, 'sequence-seed');
    $supplier = Supplier::where('code', 'SUP-2345')->firstOrFail();
    $data = ['name_ar' => 'لعبة اختبار', 'category_id' => $f['category']->id, 'preferred_supplier_id' => $supplier->id, 'average_cost' => '10', 'sale_price' => '25'];
    $baseline = getenv('QA_STORAGE').'/q2-item-code-baseline';
    if (! is_file($baseline)) {
        file_put_contents($baseline, (string) DB::table('global_product_code_sequences')->where('sequence_name', 'product_identity:2345')->value('next_number'));
    }
    $before = (int) file_get_contents($baseline);
    $jobs = [];
    foreach (['A', 'B'] as $tag) {
        $jobs[] = ['op' => 'product', 'data' => $data + ['name_en' => 'Concurrent '.$tag, 'model_number' => 'Q2-CODE-R2-'.$tag]];
    }
    $x = q2Race($f, 'item-code', 'SELECT id FROM global_product_code_sequences WHERE sequence_name=? FOR UPDATE', ['product_identity:2345'], $jobs);
    q2Outcomes($x, 2, 0, 'item-code');
    $codes = Product::whereIn('model_number', ['Q2-CODE-R2-A', 'Q2-CODE-R2-B'])->pluck('item_code')->all();
    qaAssert(count(array_unique($codes)) === 2, 'two distinct concurrent item codes');
    qaAssert(DB::table('barcodes')->whereIn('barcode', $codes)->count() === 2, 'each item has one primary identity barcode');
    qaAssert((int) DB::table('global_product_code_sequences')->where('sequence_name', 'product_identity:2345')->value('next_number') === $before + 2, 'failed allocation attempt rolls back its sequence increment');
    qaAssert(DB::table('audit_logs')->where('event', 'create_product_card')->whereIn('source_id', Product::whereIn('model_number', ['Q2-CODE-R2-A', 'Q2-CODE-R2-B'])->pluck('id'))->count() === 2, 'each concurrent product audited exactly once');
};
$cases['stock-decrement'] = function () use ($f) {
    $p = q2Product($f, 'stock-race');
    q2Stock($f, $p, '1', 'stock-race');
    $base = ['op' => 'stock', 'product' => $p->id, 'store' => $f['store']->id, 'quantity' => '-1'];
    $x = q2Race($f, 'stock-decrement', 'SELECT id FROM stock_balances WHERE product_id=? AND store_id=? FOR UPDATE', [$p->id, $f['store']->id], [$base + ['key' => 'q2-decrement-a'], $base + ['key' => 'q2-decrement-b']]);
    q2Outcomes($x, 1, 1, 'stock-decrement');
    $balance = StockBalance::where('product_id', $p->id)->where('store_id', $f['store']->id)->firstOrFail();
    qaMoney($balance->on_hand, '0', 'no negative concurrent stock', 6);
    qaMoney($balance->total_value, '0', 'consumed cost removes value');
    qaAssert(DB::table('stock_movements')->where('product_id', $p->id)->where('quantity', '<', 0)->count() === 1, 'one stock consumption posted');
};
$cases['stock-replay'] = function () use ($f) {
    $p = q2Product($f, 'stock-replay');
    q2Stock($f, $p, '2', 'stock-replay');
    $job = ['op' => 'stock', 'product' => $p->id, 'store' => $f['store']->id, 'quantity' => '-1', 'key' => 'q2-stock-replay'];
    $x = q2Race($f, 'stock-replay', 'SELECT id FROM stock_balances WHERE product_id=? AND store_id=? FOR UPDATE', [$p->id, $f['store']->id], [$job, $job]);
    q2Outcomes($x, 2, 0, 'stock-replay');
    qaAssert($x[0]['id'] === $x[1]['id'], 'stock retries return same immutable movement');
    qaMoney(StockBalance::where('product_id', $p->id)->where('store_id', $f['store']->id)->value('on_hand'), '1', 'stock replay decrements once', 6);
};
$cases['payment-limits'] = function () use ($f) {
    $method = PaymentMethod::firstOrCreate(['code' => 'Q2-CARD'], ['name_ar' => 'دفع اختبار', 'name_en' => 'QA Electronic', 'type' => 'card', 'status' => 'active', 'requires_evidence' => false, 'offline_eligible' => false]);
    $shift = PosShift::firstOrFail();
    $sale = Sale::firstOrCreate(['idempotency_key' => 'q2-payment-fixture'], ['branch_id' => $f['branch']->id, 'store_id' => $f['store']->id, 'cash_drawer_id' => $f['drawer']->id, 'shift_id' => $shift->id, 'cashier_id' => $f['admin']->id, 'status' => 'draft', 'subtotal' => '50', 'total' => '50', 'payable_total' => '50', 'currency_code' => 'EGP', 'request_fingerprint' => hash('sha256', 'q2-payment-fixture')]);
    $base = ['op' => 'payment', 'sale' => $sale->id, 'method' => $method->id, 'amount' => '30'];
    $x = q2Race($f, 'payment-limits', 'SELECT id FROM sales WHERE id=? FOR UPDATE', [$sale->id], [$base + ['key' => 'q2-pay-a'], $base + ['key' => 'q2-pay-b']]);
    q2Outcomes($x, 1, 1, 'payment-limits');
    qaMoney($sale->payments()->sum('amount'), '30', 'partial capture remains within payable');
    qaAssert($sale->fresh()->status === 'draft', 'partial capture does not approve a sale');
    $job = $base;
    $job['amount'] = '20';
    $job['key'] = 'q2-pay-replay';
    $x = q2Race($f, 'payment-replay', 'SELECT id FROM sales WHERE id=? FOR UPDATE', [$sale->id], [$job, $job]);
    q2Outcomes($x, 2, 0, 'payment-replay');
    qaAssert($x[0]['id'] === $x[1]['id'], 'payment capture replay yields same evidence');
    qaMoney($sale->payments()->sum('amount'), '50', 'exact full capture once');
    qaAssert($sale->payments()->count() === 2, 'two distinct partial payment records');
    $payment = $sale->payments()->firstOrFail();
    qaReject(fn () => $payment->update(['amount' => '1']), 'captured tender is immutable', LogicException::class);
    qaReject(fn () => app(CapturePaymentAction::class)->execute($f['admin'], $sale, $method, '1', 'q2-pay-excess'), 'settled amount blocks extra capture', InvalidArgumentException::class);
};
$cases['partial-return-snapshots'] = function () use ($f) {
    $p = q2Product($f, 'partial-return');
    q2Stock($f, $p, '4', 'partial-return');
    q2Price($f, $p);
    $method = PaymentMethod::where('code', 'Q2-CARD')->firstOrFail();
    $sale = Sale::where('idempotency_key', 'q2-partial-sale')->first() ?? app(RetailSaleAction::class)->create($f['admin'], $f['store'], [['product_id' => $p->id, 'quantity' => '2']], 'q2-partial-sale', false, [['method' => $method, 'amount' => '30'], ['method' => $f['method'], 'amount' => '20', 'tendered' => '30']]);
    $sale->load('lines', 'payments');
    qaMoney($sale->total, '50', 'split sale total');
    qaMoney($sale->payments->where('method_type', 'cash')->sum('amount'), '20', 'cash settles residual only');
    qaMoney($sale->payments->sum('change_amount'), '10', 'cash-only change exact');
    qaMoney($sale->lines->first()->consumed_cost, '20', 'sale snapshots original inventory cost');
    $hash = $sale->sourceHash();
    $line = $sale->lines->first();
    $lineSnapshot = $line->getAttributes();
    $paymentHashes = $sale->payments->mapWithKeys(fn ($x) => [$x->id => $x->sourceHash()])->all();
    app(SaveProductAction::class)->execute(['name_ar' => $p->name_ar, 'name_en' => $p->name_en, 'model_number' => $p->model_number, 'category_id' => $p->category_id, 'average_cost' => '99.1234', 'sale_price' => '30'], $p->id, $p->lock_version);
    $r = app(RetailReturnAction::class);
    $cash = $sale->payments->firstWhere('method_type', 'cash');
    $card = $sale->payments->firstWhere('method_type', 'card');
    for ($n = 1; $n <= 2; $n++) {
        $ret = RetailReturn::where('idempotency_key', 'q2-partial-return-'.$n)->first() ?? $r->create($f['admin'], ['source_sale_id' => $sale->id, 'reason' => 'QA partial return', 'settlement_type' => 'original_tender', 'lines' => [['sale_line_id' => $line->id, 'quantity' => '1', 'condition' => 'sellable', 'disposition' => 'restock']]], 'q2-partial-return-'.$n);
        if ($ret->status === 'draft') {
            $ret = $r->submit($f['admin'], $ret);
        }if ($ret->status === 'submitted') {
            $ret = $r->approve($f['admin'], $ret);
        }
        $ret = $r->complete($f['admin'], $ret, 'q2-partial-refund-'.$n, allocations: [['payment_method_id' => $cash->payment_method_id, 'original_payment_id' => $cash->id, 'amount' => '10'], ['payment_method_id' => $card->payment_method_id, 'original_payment_id' => $card->id, 'amount' => '15', 'safe_reference' => 'Q2-PARTIAL-'.$n]]);
        qaMoney($ret->settlement_value, '25', 'partial refund '.$n.' exact original price');
        qaMoney($ret->settlements->sum('amount'), '25', 'partial refund '.$n.' exact settlement portions');
        qaMoney(StockBalance::where('product_id', $p->id)->where('store_id', $f['store']->id)->value('on_hand'), (string) (2 + $n), 'partial return '.$n.' restocks one', 6);
    }
    qaMoney(StockBalance::where('product_id', $p->id)->where('store_id', $f['store']->id)->value('total_value'), '40', 'returns preserve historical cost rather than new product cost');
    qaAssert($sale->fresh()->sourceHash() === $hash && $line->fresh()->getAttributes() === $lineSnapshot, 'sale/tax/price/cost historical snapshot immutable');
    foreach ($sale->payments as $payment) {
        qaAssert($payment->fresh()->sourceHash() === $paymentHashes[$payment->id], 'original payment history unchanged');
    }
    qaReject(fn () => $r->create($f['admin'], ['source_sale_id' => $sale->id, 'reason' => 'QA excessive partial', 'lines' => [['sale_line_id' => $line->id, 'quantity' => '1']]], 'q2-partial-over'), 'partial refunds exhaust quantity entitlement', ValidationException::class);
};
$cases['tax-snapshot'] = function () use ($f) {
    $tax = TaxSetting::firstOrCreate(['company_id' => $f['company']->id, 'code' => 'Q2-TAX'], ['name_ar' => 'ضريبة اختبار', 'name_en' => 'QA Tax', 'rate' => '14', 'is_default' => true, 'is_tax_inclusive' => true, 'treatment' => 'standard', 'status' => 'active']);
    $p = q2Product($f, 'tax-boundary', '10', '0.04');
    q2Stock($f, $p, '1', 'tax-boundary');
    q2Price($f, $p, '0.04');
    $method = PaymentMethod::where('code', 'Q2-CARD')->firstOrFail();
    $sale = Sale::where('idempotency_key', 'q2-tax-sale')->first() ?? app(RetailSaleAction::class)->create($f['admin'], $f['store'], [['product_id' => $p->id, 'quantity' => '1']], 'q2-tax-sale', false, [['method' => $method, 'amount' => '0.04']], ['tax_applicable' => true]);
    qaMoney($sale->tax_total, '0', 'real 14% inclusive tax rounds only at final boundary', 2);
    qaMoney($sale->total, '0.04', 'inclusive payable unchanged', 2);
    qaMoney($sale->tax_rate_snapshot, '14', 'approved tax rate snapshot', 2);
    qaAssert($sale->tax_inclusive_snapshot === true, 'approved inclusive treatment snapshot');
    $hash = $sale->sourceHash();
    $tax->update(['rate' => '15', 'is_tax_inclusive' => false]);
    qaAssert($sale->fresh()->sourceHash() === $hash, 'later tax configuration cannot rewrite approved sale');
};
$cases['purchase-approval-receive'] = function () use ($f) {
    $p = q2Product($f, 'purchase');
    $s = Supplier::where('code', 'SUP-2345')->firstOrFail();
    $i = PurchaseInvoice::where('supplier_reference', 'Q2-RECEIVE')->first();
    if (! $i) {
        $i = app(SavePurchaseInvoiceAction::class)->execute(['supplier_id' => $s->id, 'store_id' => $f['receiving']->id, 'invoice_date' => today()->toDateString(), 'currency_code' => 'EGP', 'supplier_reference' => 'Q2-RECEIVE'], [['product_id' => $p->id, 'quantity' => '2', 'unit_cost' => '12.1234', 'base_consumer_price' => '30', 'discount_value' => '0', 'tax_rate' => '0']]);
    }
    if ($i->status === 'draft') {
        $i = app(SubmitPurchaseInvoiceAction::class)->execute($i->id, $i->lock_version);
    }
    if ($i->status === 'awaiting_distribution' || $i->status === 'submitted') {
        $i = app(SavePurchaseDistributionAction::class)->execute($i->id, [$i->lines->first()->id => [$f['store']->id => '2']], $i->lock_version);
    }
    $approval = ApprovalRecord::where('source_type', 'purchase_invoices')->where('source_id', (string) $i->id)->firstOrFail();
    $job = ['op' => 'approve', 'approval' => $approval->id];
    $x = q2Race($f, 'purchase-approval', 'SELECT id FROM purchase_invoices WHERE id=? FOR UPDATE', [$i->id], [$job, $job]);
    q2Outcomes($x, 1, 1, 'purchase approval double submit');
    qaMoney(DB::table('stock_movements')->where('product_id', $p->id)->where('movement_type', 'purchase_receipt')->sum('quantity'), '2', 'purchase approval receives once', 6);
    qaAssert(DB::table('stock_movements')->where('product_id', $p->id)->where('movement_type', 'purchase_receipt')->count() === 1, 'one financial receipt effect');
    $line = $i->fresh('lines')->lines->first();
    qaMoney($line->previous_product_cost, '10', 'approval preserves previous product cost');
    qaMoney($p->fresh()->average_cost, '12.12', 'product-card cost persists at existing schema two-place precision', 2);
    qaMoney($line->unit_cost, '12.1234', 'purchase retains four-place historical unit cost');
    qaMoney($line->line_total, '24.2468', 'approved purchasing total exact');
    $t = StockTransfer::where('purchase_invoice_id', $i->id)->firstOrFail()->load('lines');
    $job = ['op' => 'receive', 'transfer' => $t->id, 'lines' => $t->lines->mapWithKeys(fn ($x) => [$x->id => (string) $x->quantity_dispatched])->all()];
    $x = q2Race($f, 'purchase-receive', 'SELECT id FROM stock_transfers WHERE id=? FOR UPDATE', [$t->id], [$job, $job]);
    q2Outcomes($x, 1, 1, 'purchase destination receiving');
    qaMoney(StockBalance::where('product_id', $p->id)->where('store_id', $f['store']->id)->value('on_hand'), '2', 'destination receives exactly once', 6);
    qaMoney(StockBalance::where('product_id', $p->id)->where('store_id', $f['store']->id)->value('total_value'), '24.2468', 'destination exact received cost');
};
$cases['price-activation'] = function () use ($f) {
    $p = q2Product($f, 'price-activation');
    q2Price($f, $p);
    $jobs = [];
    $saved = getenv('QA_STORAGE').'/q2-price-jobs.json';
    if (is_file($saved)) {
        $jobs = json_decode(file_get_contents($saved), true, 512, JSON_THROW_ON_ERROR);
    } else {
        foreach (['26', '27'] as $amount) {
            $v = app(CreatePriceProposalAction::class)->execute($p, $f['store'], 'PL-0000', 'قائمة اختبار', 'QA Base', $amount, 'product_card', 'Q2-ACTIVE', null, null, 'QA concurrent activation');
            $v = app(SubmitPriceProposalAction::class)->execute($v);
            $jobs[] = ['op' => 'price', 'version' => $v->id];
        }
        file_put_contents($saved, json_encode($jobs, JSON_THROW_ON_ERROR));
    }
    $x = q2Race($f, 'price-activation', 'SELECT id FROM price_lines WHERE active_key=? FOR UPDATE', [$p->id.':'.$f['store']->id], $jobs);
    q2Outcomes($x, 2, 0, 'serialized price activation');
    qaAssert(PriceLine::where('product_id', $p->id)->where('store_id', $f['store']->id)->whereNotNull('active_key')->count() === 1, 'one effective price after concurrent activations');
    $active = app(EffectivePriceResolver::class)->resolve($p->id, $f['store']->id);
    qaAssert($active !== null && (bccomp((string) $active->amount, '26', 4) === 0 || bccomp((string) $active->amount, '27', 4) === 0), 'effective price is one approved winner');
};
$cases['return-limit-refund-replay'] = function () use ($f) {
    $p = q2Product($f, 'return-race');
    q2Stock($f, $p, '2', 'return-race');
    $sale = q2Sale($f, $p, '2', 'q2-race-sale');
    $data = ['source_sale_id' => $sale->id, 'reason' => 'QA quota contention', 'settlement_type' => 'original_tender', 'lines' => [['sale_line_id' => $sale->lines->first()->id, 'quantity' => '2', 'condition' => 'sellable', 'disposition' => 'restock']]];
    $x = q2Race($f, 'return-limit', 'SELECT id FROM sales WHERE id=? FOR UPDATE', [$sale->id], [['op' => 'return', 'data' => $data, 'key' => 'q2-return-a'], ['op' => 'return', 'data' => $data, 'key' => 'q2-return-b']]);
    q2Outcomes($x, 1, 1, 'last refundable quantity reservation');
    $ret = RetailReturn::where('source_sale_id', $sale->id)->firstOrFail();
    qaAssert(RetailReturn::where('source_sale_id', $sale->id)->count() === 1, 'one quota reservation');
    $r = app(RetailReturnAction::class);
    if ($ret->status === 'draft') {
        $ret = $r->submit($f['admin'], $ret);
    }if ($ret->status === 'submitted') {
        $ret = $r->approve($f['admin'], $ret);
    }$job = ['op' => 'refund', 'return' => $ret->id, 'key' => 'q2-refund-replay'];
    $x = q2Race($f, 'refund-replay', 'SELECT id FROM retail_returns WHERE id=? FOR UPDATE', [$ret->id], [$job, $job]);
    q2Outcomes($x, 2, 0, 'refund same-token replay');
    qaAssert($x[0]['id'] === $x[1]['id'], 'refund replay returns same document');
    qaMoney($ret->settlements()->sum('amount'), '50', 'one exact refund settlement');
    qaAssert($ret->settlements()->count() === 1, 'one refund ledger row');
    qaMoney(StockBalance::where('product_id', $p->id)->where('store_id', $f['store']->id)->value('on_hand'), '2', 'refund restocks exactly once', 6);
};
$cases['shift-close-approval'] = function () use ($f) {
    $shift = PosShift::firstOrFail();
    $base = ['op' => 'close', 'shift' => $shift->id, 'actual' => '100'];
    $x = q2Race($f, 'shift-close', 'SELECT id FROM pos_shifts WHERE id=? FOR UPDATE', [$shift->id], [$base + ['key' => 'q2-close-a'], $base + ['key' => 'q2-close-b']]);
    q2Outcomes($x, 1, 1, 'blind shift close');
    $submission = ShiftClosingSubmission::where('shift_id', $shift->id)->firstOrFail();
    qaMoney($submission->expected_cash, '100', 'expected cash deducts source-linked partial/full refunds', 2);
    qaMoney($submission->cash_variance, '0', 'cash variance exact', 2);
    qaMoney($submission->expected_by_method['Q2-CARD'], '0.04', 'electronic expectation includes only approved sales net refunds', 2);
    qaMoney($submission->total_variance, '-0.04', 'uncounted electronic amount produces exact shortage', 2);
    qaAssert(ShiftClosingSubmission::where('shift_id', $shift->id)->count() === 1, 'one immutable blind count');
    qaReject(fn () => $submission->update(['actual_cash' => '999']), 'close submission history immutable', LogicException::class);
    $approval = ApprovalRecord::findOrFail($shift->fresh()->variance_approval_record_id);
    $job = ['op' => 'approve', 'approval' => $approval->id];
    $x = q2Race($f, 'close-approval', 'SELECT id FROM pos_shifts WHERE id=? FOR UPDATE', [$shift->id], [$job, $job]);
    q2Outcomes($x,1,1,'shift closure approval double submit');
    qaAssert($shift->fresh()->status->value === 'closed','reviewer closes once');
    qaAssert(DB::table('active_pos_shift_assignments')->where('shift_id',$shift->id)->count() === 0,'terminal closure releases active assignment');
    qaAssert($approval->fresh()->approval_state->value === 'approved','one approval financial decision');
};
foreach ($cases as $name => $case) {
    if (isset($done[$name])) {
        echo 'PRESERVED PASS '.$name,PHP_EOL;

        continue;
    }
    if (getenv('QA_PHASE2_CASE') && getenv('QA_PHASE2_CASE') !== $name) {
        continue;
    }
    $start = microtime(true);
    $case();
    $done[$name] = ['status' => 'PASS', 'seconds' => round(microtime(true) - $start,3)];
    file_put_contents($journal,json_encode($done,JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
}
require __DIR__.'/phase2-invariants.php';
$invariants = qaPhase2Invariants(DB::connection()->getPdo());
echo 'PASS five new Phase 2 ledger/assignment invariants',PHP_EOL;
$out = getenv('QA_RUN_DIR') ?: dirname(__DIR__,2).'/.ai/qa/runs/phase2';
@mkdir($out,0700,true);
file_put_contents($out.'/financial-domain.json',json_encode(['database' => getenv('DB_DATABASE'), 'completed' => $done, 'new_invariants' => $invariants],JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
foreach (glob(getenv('QA_STORAGE').'/q2-races/*/evidence.json') as $source) {
    copy($source,$out.'/race-'.basename(dirname($source)).'.json');
}
echo 'PASS Phase 2 financial/domain/concurrency cases: '.count($done),PHP_EOL;
