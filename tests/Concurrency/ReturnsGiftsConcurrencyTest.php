<?php

declare(strict_types=1);

namespace Tests\Concurrency;

use App\Models\User;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Store;
use App\Modules\Retail\Actions\GiftCardAction;
use App\Modules\Retail\Actions\RetailReturnAction;
use App\Modules\Retail\Models\GiftCard;
use App\Modules\Retail\Models\RetailReturn;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SaleLine;
use Database\Seeders\CanonicalAuthorizationSeeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

final class ReturnsGiftsConcurrencyTest extends TestCase
{
    use PlatformFixtures;

    private static bool $schemaInitialized = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (! self::$schemaInitialized) {
            if (! filter_var(env('CONFIDENCE_SCHEMA_READY', false), FILTER_VALIDATE_BOOL)) {
                $this->artisan('migrate:fresh', ['--force' => true]);
            }
            $this->seed(CanonicalAuthorizationSeeder::class);
            self::$schemaInitialized = true;
        }
    }

    public function test_two_real_processes_cannot_over_return_the_same_sale_quantity(): void
    {
        $scenario = $this->scenario();
        Auth::login($scenario['admin']);
        $action = app(RetailReturnAction::class);
        $payload = ['source_sale_id' => $scenario['sale']->id, 'lines' => [['sale_line_id' => $scenario['saleLine']->id, 'quantity' => '1', 'condition' => 'sellable', 'disposition' => 'restock']], 'settlement_type' => 'cash_refund', 'reason' => 'Concurrent return'];
        $first = $action->create($scenario['admin'], $payload, $scenario['tag'].'-return-1');
        $second = $action->create($scenario['admin'], $payload, $scenario['tag'].'-return-2');
        foreach ([$first, $second] as $return) { $action->submit($scenario['admin'], $return); $action->approve($scenario['admin'], $return); }

        $results = $this->race('return_complete', [
            ['return_id' => $first->id, 'user_id' => $scenario['admin']->id, 'payment_method_id' => $scenario['cashMethod']->id, 'idempotency_key' => $scenario['tag'].'-complete-1'],
            ['return_id' => $second->id, 'user_id' => $scenario['admin']->id, 'payment_method_id' => $scenario['cashMethod']->id, 'idempotency_key' => $scenario['tag'].'-complete-2'],
        ]);

        self::assertSame(1, count(array_filter($results, static fn (array $result): bool => $result['ok'] === true)));
        self::assertSame(1, count(array_filter($results, static fn (array $result): bool => $result['ok'] === false)));
        self::assertSame(1, RetailReturn::query()->where('status', 'completed')->count());
        self::assertSame(1, DB::table('stock_movements')->where('source_type', RetailReturn::class)->count());
    }

    public function test_two_real_processes_cannot_overspend_a_gift_card(): void
    {
        $scenario = $this->scenario();
        Auth::login($scenario['admin']);
        $card = app(GiftCardAction::class)->issue($scenario['admin'], '50.00', $scenario['branch']->id, $scenario['store']->id, 'manual', 'concurrency', $scenario['tag'].'-card-issue');
        $results = $this->race('gift_redeem', [
            ['card_id' => $card->id, 'user_id' => $scenario['admin']->id, 'amount' => '40.00', 'idempotency_key' => $scenario['tag'].'-card-redeem-1'],
            ['card_id' => $card->id, 'user_id' => $scenario['admin']->id, 'amount' => '40.00', 'idempotency_key' => $scenario['tag'].'-card-redeem-2'],
        ]);

        self::assertSame(1, count(array_filter($results, static fn (array $result): bool => $result['ok'] === true)));
        self::assertSame(1, count(array_filter($results, static fn (array $result): bool => $result['ok'] === false)));
        self::assertSame('10.00', (string) $card->fresh()->balance);
        self::assertSame(2, $card->fresh()->ledger()->count());
    }

    /** @param array<int, array{0: string, 1: array<string, mixed>}> $calls @return array<int, array<string, mixed>> */
    private function race(string $scenario, array $calls): array
    {
        $worker = __DIR__.'/support/returns_gifts_worker.php';
        $env = ['DB_CONNECTION' => 'mysql', 'DB_HOST' => (string) config('database.connections.mysql.host'), 'DB_PORT' => (string) config('database.connections.mysql.port'), 'DB_DATABASE' => (string) config('database.connections.mysql.database'), 'DB_USERNAME' => (string) config('database.connections.mysql.username'), 'DB_PASSWORD' => (string) config('database.connections.mysql.password'), 'APP_ENV' => 'testing', 'APP_DEBUG' => 'false', 'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'QUEUE_CONNECTION' => 'sync', 'MAIL_MAILER' => 'array'];
        $processes = [];
        foreach ($calls as $index => $params) { $process = new Process(['php', $worker, $scenario, json_encode($params, JSON_THROW_ON_ERROR)], base_path(), $env); $process->setTimeout(30); $process->start(); $processes[$index] = $process; }
        $results = [];
        foreach ($processes as $process) { $process->wait(); $results[] = json_decode(trim($process->getOutput()), true, 512, JSON_THROW_ON_ERROR); }
        return $results;
    }

    /** @return array{tag: string, admin: User, company: Company, branch: Branch, store: Store, cashMethod: PaymentMethod, product: Product, sale: Sale, saleLine: SaleLine} */
    private function scenario(): array
    {
        $tag = 'RACE-'.Str::upper(Str::random(10));
        $company = $this->company();
        $branch = $this->branch($tag.'-BR');
        $store = $this->store($branch, $tag.'-ST');
        $admin = $this->administrator($tag.'-admin');
        $cashMethod = PaymentMethod::query()->create(['code' => $tag.'-CASH', 'name_ar' => 'نقدي', 'name_en' => 'Cash', 'type' => 'cash', 'requires_evidence' => false, 'status' => 'active']);
        $category = Category::query()->create(['code' => $tag.'-CAT', 'name_ar' => 'لعبة', 'name_en' => 'Toy', 'status' => 'active']);
        $product = Product::query()->create(['item_code' => $tag.'-PROD', 'name_ar' => 'لعبة', 'name_en' => 'Toy', 'category_id' => $category->id, 'status' => 'active', 'fractional_quantity' => false]);
        DB::table('stock_balances')->insert(['product_id' => $product->id, 'store_id' => $store->id, 'on_hand' => '0', 'reserved' => '0', 'in_transit' => '0', 'average_cost' => '10', 'total_value' => '0', 'version' => 1]);
        $sale = Sale::query()->create(['branch_id' => $branch->id, 'store_id' => $store->id, 'cashier_id' => $admin->id, 'status' => 'approved', 'idempotency_key' => $tag.'-sale', 'subtotal' => '20.00', 'discount_total' => '0.00', 'tax_total' => '0.00', 'total' => '20.00', 'paid_total' => '20.00', 'change_total' => '0.00', 'cash_rounding_amount' => '0.00', 'payable_total' => '20.00', 'currency_code' => 'EGP', 'approved_at' => now(), 'lock_version' => 1]);
        $saleLine = SaleLine::query()->create(['sale_id' => $sale->id, 'product_id' => $product->id, 'line_number' => 1, 'item_code' => $product->item_code, 'name_ar' => $product->name_ar, 'name_en' => $product->name_en, 'quantity' => '1.000000', 'unit_price' => '20.0000', 'gross_amount' => '20.00', 'discount_amount' => '0.00', 'net_amount' => '20.00']);
        $this->documentSequence('retail_return', 'RET-');
        return compact('tag', 'admin', 'company', 'branch', 'store', 'cashMethod', 'product', 'sale', 'saleLine');
    }
}
