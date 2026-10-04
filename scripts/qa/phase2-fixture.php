<?php

declare(strict_types=1);
use App\Modules\Catalog\Actions\SaveProductAction;
use App\Modules\Catalog\Actions\SaveSupplierAction;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\Supplier;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Pricing\Actions\ApprovePriceProposalAction;
use App\Modules\Pricing\Actions\CreatePriceProposalAction;
use App\Modules\Pricing\Actions\SubmitPriceProposalAction;
use App\Modules\Pricing\Models\PriceLine;
use App\Modules\Retail\Actions\RetailSaleAction;
use App\Modules\Retail\Models\Sale;
use Illuminate\Support\Facades\DB;

function q2Product(array $f, string $tag, string $cost = '10', string $price = '25'): Product
{
    if ($p = Product::where('model_number', 'Q2-'.$tag)->first()) {
        return $p;
    }
    $s = Supplier::where('code', 'SUP-2345')->first() ?? app(SaveSupplierAction::class)->execute(['code' => 'SUP-2345', 'name_ar' => 'مورد الجودة', 'name_en' => 'Phase 2 QA Supplier', 'status' => 'active', 'settlement_method' => 'cash']);

    return app(SaveProductAction::class)->execute(['name_ar' => 'لعبة اختبار', 'name_en' => 'Phase 2 '.$tag, 'model_number' => 'Q2-'.$tag, 'category_id' => $f['category']->id, 'preferred_supplier_id' => $s->id, 'average_cost' => $cost, 'sale_price' => $price]);
}
function q2Price(array $f, Product $p, string $price = '25'): void
{
    if (PriceLine::where('product_id', $p->id)->where('store_id', $f['store']->id)->whereNotNull('active_key')->exists()) {
        return;
    }
    $v = app(CreatePriceProposalAction::class)->execute($p, $f['store'], 'PL-0000', 'قائمة اختبار', 'QA Base', $price, 'product_card', 'Q2', null, null, 'QA Phase 2');
    $v = app(SubmitPriceProposalAction::class)->execute($v);
    app(ApprovePriceProposalAction::class)->execute($v);
}
function q2Stock(array $f, Product $p, string $quantity, string $tag): void
{
    app(PostInventoryMovement::class)->execute($p->id, $f['store']->id, $quantity, 'qa_phase2', '10', 'q2-stock-'.$tag);
}
function q2Sale(array $f, Product $p, string $quantity, string $key): Sale
{
    if ($sale = Sale::where('idempotency_key', $key)->first()) {
        return $sale->load('lines', 'payments');
    }
    q2Price($f, $p);
    $amount = bcmul($quantity, '25', 2);

    return app(RetailSaleAction::class)->create($f['admin'], $f['store'], [['product_id' => $p->id, 'quantity' => $quantity]], $key, false, [['method' => $f['method'], 'amount' => $amount, 'tendered' => $amount]]);
}
function q2Race(array $f, string $label, string $lockSql, array $bind, array $jobs): array
{
    $work = getenv('QA_STORAGE').'/q2-races/'.$label;
    @mkdir($work, 0700, true);
    $signature = hash('sha256', json_encode([$lockSql, $bind, $jobs], JSON_THROW_ON_ERROR));
    if (is_file($work.'/evidence.json')) {
        $prior = json_decode(file_get_contents($work.'/evidence.json'), true, 512, JSON_THROW_ON_ERROR);
        if (($prior['signature'] ?? null) === $signature) {
            echo 'PRESERVED PASS race '.$label,PHP_EOL;

            return $prior['outcomes'];
        }
    }
    foreach (glob($work.'/*') as $old) {
        unlink($old);
    }
    $processes = [];
    $connectionIds = [];
    $deadline = microtime(true) + 15;
    $monitor = new PDO('mysql:unix_socket='.getenv('DB_SOCKET').';dbname='.getenv('DB_DATABASE'), 'root', '');
    preg_match('/FROM\s+([a-z_]+)/i', $lockSql, $tableMatch);
    $lockTable = $tableMatch[1];
    DB::beginTransaction();
    try {
        DB::select($lockSql, $bind);
        foreach ($jobs as $i => $job) {
            $payload = $job + ['actor' => $f['admin']->id, 'slot' => (string) $i];
            $path = $work.'/'.$i.'.job.json';
            file_put_contents($path, json_encode($payload, JSON_THROW_ON_ERROR));
            $processes[$i] = proc_open([PHP_BINARY, __DIR__.'/concurrency-worker.php', $path], [0 => ['file', '/dev/null', 'r'], 1 => ['file', $work.'/'.$i.'.stdout.log', 'w'], 2 => ['file', $work.'/'.$i.'.stderr.log', 'w']], $pipes, dirname(__DIR__, 2));
            if (! is_resource($processes[$i])) {
                throw new RuntimeException('Could not launch independent race worker');
            }
        }
        foreach ($jobs as $i => $job) {
            while (! is_file($work.'/'.$i.'.ready')) {
                if (microtime(true) > $deadline) {
                    throw new RuntimeException('Worker ready timeout');
                }usleep(10000);
            }
            $connectionIds[] = (int) file_get_contents($work.'/'.$i.'.ready');
        }
        touch($work.'/go');
        $waitDeadline = microtime(true) + 5;
        $waiting = 0;
        $requests = [];
        do {
            $waiting = (int) $monitor->query("SELECT COUNT(*) FROM information_schema.INNODB_TRX WHERE trx_state='LOCK WAIT' AND trx_mysql_thread_id IN (".implode(',', $connectionIds).')')->fetchColumn();
            if ($waiting === count($jobs)) {
                break;
            }
            $requests = array_values(array_filter($monitor->query('SHOW PROCESSLIST')->fetchAll(PDO::FETCH_ASSOC), fn ($x) => in_array((int) $x['Id'], $connectionIds, true) && $x['Command'] === 'Execute' && (int) $x['Time'] >= 1 && (stripos((string) $x['Info'], $lockTable) !== false || ($jobs[0]['op'] === 'refund' && $x['State'] === 'User lock' && stripos((string) $x['Info'], 'GET_LOCK') !== false))));
            if (count($requests) === count($jobs)) {
                $waiting = count($requests);
                break;
            }
            usleep(20000);
        } while (microtime(true) < $waitDeadline);
        if ($waiting !== count($jobs)) {
            file_put_contents($work.'/wait-diagnostics.json', json_encode(['connections' => $connectionIds, 'transactions' => DB::select('SELECT trx_mysql_thread_id,trx_state,trx_query FROM information_schema.INNODB_TRX'), 'processes' => DB::select('SHOW PROCESSLIST'), 'entered' => glob($work.'/*.entered')], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            throw new RuntimeException($label.': both workers must demonstrably contend on the held database lock');
        }
        DB::commit();
        $results = [];
        foreach ($processes as $i => $process) {
            proc_close($process);
            if (! is_file($work.'/'.$i.'.result.json')) {
                throw new RuntimeException('Worker failed before recording outcome: '.file_get_contents($work.'/'.$i.'.stderr.log'));
            }
            $results[] = json_decode(file_get_contents($work.'/'.$i.'.result.json'), true, 512, JSON_THROW_ON_ERROR);
        }
        foreach ($results as $result) {
            qaAssert($result['status'] !== 'error', $label.' no unexpected worker failure: '.($result['message'] ?? ''));
        }
        file_put_contents($work.'/evidence.json', json_encode(['signature' => $signature, 'workers' => 2, 'independent_connections' => $connectionIds, 'observed_blocked_lock_requests' => $waiting, 'blocking_queries' => $requests, 'outcomes' => $results], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        echo 'PASS '.$label.' independent connections / two observed blocked locking requests',PHP_EOL;

        return $results;
    } finally {
        if (DB::transactionLevel() > 0) {
            DB::rollBack();
        }
        touch($work.'/go');
        foreach ($processes as $process) {
            if (is_resource($process)) {
                proc_terminate($process);
                proc_close($process);
            }
        }
    }
}
function q2Outcomes(array $results, int $wins, int $losers, string $label): void
{
    qaAssert(count(array_filter($results,fn ($x) => $x['status'] === 'success')) === $wins && count(array_filter($results,fn ($x) => $x['status'] === 'rejected')) === $losers,$label.' winner/safe loser counts');
}
