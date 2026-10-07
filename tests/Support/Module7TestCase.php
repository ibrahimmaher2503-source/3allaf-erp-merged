<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Customer\Models\Customer;
use App\Modules\Customer\Models\CustomerPolicySettingVersion;
use App\Modules\Inventory\Actions\PostInventoryMovement;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\CashDrawer;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Support\RequestPermissionLookup;
use App\Modules\Pricing\Actions\ApprovePriceProposalAction;
use App\Modules\Pricing\Actions\CreatePriceProposalAction;
use App\Modules\Pricing\Actions\SubmitPriceProposalAction;
use App\Modules\Retail\Actions\GiftReceiptAction;
use App\Modules\Retail\Actions\OpenShiftAction;
use App\Modules\Retail\Actions\RetailReturnAction;
use App\Modules\Retail\Actions\RetailSaleAction;
use App\Modules\Retail\Models\GiftReceipt;
use App\Modules\Retail\Models\PosShift;
use App\Modules\Retail\Models\RetailReturn;
use App\Modules\Retail\Models\Sale;
use App\Modules\Retail\Models\SaleLine;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

abstract class Module7TestCase extends TestCase
{
    use DatabaseTransactions;

    private const QA_DATABASE = 'qa_rajh_m7_returns_20261007';

    private const QA_STORAGE_PATH = 'C:\\Users\\N\\AppData\\Local\\Temp\\rajeh-erp-qa\\qa_rajh_m7_returns_20261007';

    protected Company $company;

    protected Branch $branch;

    protected Store $store;

    protected Store $damagedStore;

    protected CashDrawer $drawer;

    protected User $cashier;

    protected User $reviewer;

    protected PosShift $shift;

    protected Product $productA;

    protected Product $productB;

    protected Customer $customer;

    protected PaymentMethod $paymentMethod;

    protected Sale $sale1;

    protected Sale $sale2;

    protected GiftReceipt $giftReceipt;

    public function createApplication(): Application
    {
        $appEnvironment = getenv('APP_ENV');
        $expectedEnvironment = in_array($appEnvironment, ['local', 'testing'], true);
        $expectedConnection = getenv('DB_CONNECTION') === 'mysql'
            && getenv('DB_HOST') === '127.0.0.1'
            && getenv('DB_PORT') === '3306'
            && getenv('DB_DATABASE') === self::QA_DATABASE
            && getenv('DB_USERNAME') === 'root'
            && (getenv('DB_PASSWORD') === '' || getenv('DB_PASSWORD') === false);
        $expectedStorage = realpath(self::QA_STORAGE_PATH);
        $configuredStorage = realpath((string) getenv('QA_STORAGE_PATH'));

        if (! $expectedEnvironment || ! $expectedConnection || $expectedStorage === false
            || $configuredStorage === false || strcasecmp($configuredStorage, $expectedStorage) !== 0) {
            throw new RuntimeException('Module 7 tests require APP_ENV=local/testing, the dedicated local MySQL QA schema, and its isolated QA storage path.');
        }

        $app = require __DIR__.'/../../bootstrap/app.php';
        $app->useStoragePath($expectedStorage);
        $app->make(Kernel::class)->bootstrap();

        $mysql = $app['config']->get('database.connections.mysql', []);
        if (! $app->environment(['local', 'testing'])
            || $app['config']->get('database.default') !== 'mysql'
            || ($mysql['host'] ?? null) !== '127.0.0.1'
            || (string) ($mysql['port'] ?? '') !== '3306'
            || ($mysql['database'] ?? null) !== self::QA_DATABASE
            || ($mysql['username'] ?? null) !== 'root'
            || ($mysql['password'] ?? null) !== '') {
            throw new RuntimeException('Module 7 tests refused a database or environment outside the dedicated local QA target.');
        }

        $app->setLocale('en');

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::factory()->create();
        $this->branch = Branch::factory()->for($this->company)->create([
            'code' => 'DOKKI-'.strtoupper(Str::random(8)),
            'name_ar' => 'فرع الدقي',
            'name_en' => 'Dokki',
            'timezone' => 'Africa/Cairo',
        ]);
        $this->store = Store::factory()->for($this->branch)->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'type' => 'selling',
            'name_ar' => 'متجر الدقي',
            'name_en' => 'Dokki Selling Store',
        ]);
        $this->damagedStore = Store::factory()->for($this->branch)->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'type' => 'damaged',
            'name_ar' => 'مخزن التالف بالدقي',
            'name_en' => 'Dokki Damaged Store',
        ]);

        $this->cashier = $this->userWithPermissions([
            'gift_receipts.issue',
            'gift_receipts.print',
            'loyalty.earn',
            'pos_sales.create',
            'pos_sales.payment_create',
            'pricing_labels.create',
            'pricing_labels.submit',
            'returns.create',
            'returns.complete',
            'returns.submit',
            'returns.view',
            'shifts_cash_movements.create',
        ]);
        $this->reviewer = $this->userWithPermissions([
            'pricing_labels.approve',
            'returns.approve',
            'returns.complete',
            'returns.create',
            'returns.submit',
            'returns.view',
        ]);

        $this->drawer = CashDrawer::factory()->create([
            'company_id' => $this->company->id,
            'branch_id' => $this->branch->id,
            'store_id' => $this->store->id,
            'assigned_user_id' => $this->cashier->id,
        ]);
        $this->paymentMethod = PaymentMethod::factory()->create([
            'code' => 'M7-CARD-'.strtoupper(Str::random(8)),
            'name_ar' => 'بطاقة اختبار',
            'name_en' => 'Module 7 QA card',
            'type' => 'card',
            'requires_evidence' => false,
        ]);

        $this->productA = Product::factory()->create([
            'item_code' => 'M7-A-'.strtoupper(Str::random(8)),
            'name_ar' => 'منتج المرتجعات أ',
            'name_en' => 'Module 7 Product A',
            'sale_price' => '123.45',
            'model_number' => 'M7-A-'.strtoupper(Str::random(8)),
        ]);
        $this->productB = Product::factory()->create([
            'item_code' => 'M7-B-'.strtoupper(Str::random(8)),
            'name_ar' => 'منتج المرتجعات ب',
            'name_en' => 'Module 7 Product B',
            'sale_price' => '56.78',
            'model_number' => 'M7-B-'.strtoupper(Str::random(8)),
        ]);
        foreach ([$this->productA, $this->productB] as $product) {
            $product->barcodes()->create([
                'barcode' => 'M7-'.strtoupper(Str::random(12)),
                'source' => 'local',
                'status' => 'active',
                'is_primary' => true,
            ]);
        }

        $this->actingAs($this->cashier)->withoutVite();
        $this->withSession([]);
        request()->setLaravelSession(app('session')->driver());

        $this->approveProductPrice($this->productA, '123.45');
        $this->approveProductPrice($this->productB, '56.78');

        $this->shift = app(OpenShiftAction::class)->execute($this->cashier, $this->drawer, '0.00', (string) Str::uuid());
        foreach ([$this->productA, $this->productB] as $product) {
            app(PostInventoryMovement::class)->execute(
                (int) $product->id,
                (int) $this->store->id,
                '20',
                'opening_inventory',
                '10.0000',
                (string) Str::uuid(),
                requireFirstMovement: true,
            );
        }

        $phone = '2010'.sprintf('%08d', random_int(0, 99999999));
        $this->customer = Customer::query()->create([
            'idempotency_key' => (string) Str::uuid(),
            'name_ar' => 'عميل اختبار المرتجعات',
            'name_en' => 'Module 7 QA Customer',
            'phone_normalized' => $phone,
            'phone_display' => '0'.substr($phone, 2),
            'status' => 'active',
            'created_by' => $this->cashier->id,
            'created_branch_id' => $this->branch->id,
            'created_store_id' => $this->store->id,
        ]);
        $this->createLoyaltyPolicyVersions();

        $this->sale1 = $this->createCompletedSale([
            ['product_id' => $this->productA->id, 'quantity' => '5'],
        ], '617.25');
        $this->sale2 = $this->createCompletedSale([
            ['product_id' => $this->productA->id, 'quantity' => '1'],
            ['product_id' => $this->productB->id, 'quantity' => '2'],
        ], '237.01');
        $this->giftReceipt = app(GiftReceiptAction::class)->issue($this->cashier, $this->sale1, (string) Str::uuid());
    }

    /** @param list<string> $codes */
    protected function userWithPermissions(array $codes): User
    {
        $user = User::factory()->create(['status' => 'active']);
        $this->grantPermissions($user, $codes);

        foreach ([$this->store, $this->damagedStore] as $store) {
            DB::table('user_store_scopes')->insert([
                'user_id' => $user->id,
                'store_id' => $store->id,
                'status' => 'active',
            ]);
        }

        return $user;
    }

    /** @param list<string> $codes */
    protected function grantPermissions(User $user, array $codes): void
    {
        $role = Role::query()->create([
            'code' => 'M7-'.strtoupper(Str::random(10)),
            'name_ar' => 'صلاحيات اختبار الوحدة السابعة',
            'name_en' => 'Module 7 QA role',
            'status' => 'active',
        ]);
        $permissionIds = [];
        foreach (array_values(array_unique($codes)) as $code) {
            [$module, $action] = explode('.', $code, 2);
            $permission = Permission::query()->firstOrCreate(
                ['code' => $code],
                ['module' => $module, 'action' => $action, 'status' => 'active'],
            );
            $permissionIds[] = $permission->id;
        }
        $role->permissions()->syncWithoutDetaching($permissionIds);
        $user->roles()->syncWithoutDetaching([$role->id]);
        $user->unsetRelation('roles');
        app()->forgetInstance(RequestPermissionLookup::class);
        request()->attributes->remove('permission_lookup.'.$user->id);
    }

    /** @param array<string, mixed> $overrides */
    protected function createReturn(Sale $sale, array $overrides = [], ?User $actor = null): RetailReturn
    {
        $actor ??= $this->cashier;
        $this->actingAs($actor);

        return app(RetailReturnAction::class)->create(
            $actor,
            array_replace($this->returnData($sale), $overrides),
            (string) Str::uuid(),
        );
    }

    /** @return array<string, mixed> */
    protected function returnData(
        Sale $sale,
        int $lineIndex = 0,
        int $quantity = 1,
        string $inspection = 'sellable',
        string $disposition = 'restock',
        string $settlement = 'cash_refund',
    ): array {
        $sale->loadMissing('lines');
        $line = $sale->lines->values()->get($lineIndex);
        if (! $line instanceof SaleLine) {
            throw new RuntimeException('The requested sale fixture line does not exist.');
        }
        if ($quantity < 1) {
            throw new RuntimeException('A return fixture quantity must be positive.');
        }

        $damagedDisposition = $disposition === 'quarantine' || in_array($inspection, ['damaged', 'non_sellable'], true);

        return [
            'source_sale_id' => $sale->id,
            'source_gift_receipt_id' => null,
            'settlement_type' => $settlement,
            'reason' => 'Module 7 local QA fixture return',
            'exchange_lines' => [],
            'lines' => [[
                'sale_line_id' => $line->id,
                'quantity' => (string) $quantity,
                'condition' => $inspection,
                'disposition' => $disposition,
                'return_store_id' => $damagedDisposition ? $this->damagedStore->id : $sale->store_id,
                'inspection_notes' => 'Inspected in the local Module 7 QA fixture.',
            ]],
        ];
    }

    /** @param list<array{product_id: int, quantity: string}> $lines */
    private function createCompletedSale(array $lines, string $amount): Sale
    {
        return app(RetailSaleAction::class)->create(
            $this->cashier,
            $this->store,
            $lines,
            (string) Str::uuid(),
            false,
            [['method' => $this->paymentMethod, 'amount' => $amount]],
            [],
            $this->customer,
        );
    }

    private function createLoyaltyPolicyVersions(): void
    {
        foreach ([
            'loyalty.retail_rule' => ['earn_points_per_currency' => '0.0001', 'redeem_currency_per_point' => '1'],
            'loyalty.expiry_policy' => ['days' => 365],
            'loyalty.rounding_policy' => ['earn' => 'floor', 'redeem' => 'floor'],
            'loyalty.ledger_integrity' => ['enabled' => true],
        ] as $key => $value) {
            CustomerPolicySettingVersion::query()->firstOrCreate(['key' => $key, 'version' => 1], [
                'value' => json_encode($value, JSON_THROW_ON_ERROR),
                'value_type' => 'json',
                'version' => 1,
                'created_by' => $this->cashier->id,
            ]);
        }
    }

    private function approveProductPrice(Product $product, string $amount): void
    {
        $this->actingAs($this->cashier);
        $version = app(CreatePriceProposalAction::class)->execute(
            $product,
            $this->store,
            'M7-RETURNS-'.strtoupper(Str::random(8)),
            'قائمة أسعار اختبار المرتجعات',
            'Module 7 QA Return Prices',
            $amount,
            'product_card',
            null,
            null,
            null,
            'Approved local Module 7 QA fixture price.',
        );
        $version = app(SubmitPriceProposalAction::class)->execute($version);

        $this->actingAs($this->reviewer);
        app(ApprovePriceProposalAction::class)->execute($version);
        $this->actingAs($this->cashier);
    }
}
