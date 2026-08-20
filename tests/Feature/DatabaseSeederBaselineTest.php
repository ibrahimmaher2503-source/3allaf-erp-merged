<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Customer\Models\Customer;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Platform\Models\Branch;
use App\Modules\Platform\Models\BranchSellingStore;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\DocumentSequence;
use App\Modules\Platform\Models\PaymentMethod;
use App\Modules\Platform\Models\Role;
use App\Modules\Platform\Models\Store;
use App\Modules\Platform\Models\TaxSetting;
use App\Modules\Retail\Models\Sale;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use LogicException;
use Tests\TestCase;

class DatabaseSeederBaselineTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_setup_seed_opt_in_invokes_the_fail_closed_production_setup_loader(): void
    {
        config()->set('production-seeding.setup_data.enabled', true);
        config()->set('production-seeding.setup_data.path', 'C:\\does-not-exist\\owner-approved-setup.json');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('PRODUCTION_SETUP_DATA_PATH must reference a readable JSON file.');

        app(DatabaseSeeder::class)->run();
    }

    public function test_the_normal_seeder_installs_a_usable_production_baseline_without_seed_environment_inputs(): void
    {
        $this->app->detectEnvironment(fn (): string => 'production');

        app(DatabaseSeeder::class)->run();
        $this->app->detectEnvironment(fn (): string => 'testing');

        $administrator = User::query()->where('username', 'admin')->firstOrFail();

        $this->assertSame('admin@instaparty.online', $administrator->email);
        $this->assertTrue($administrator->is_super_admin);
        $this->assertTrue(Hash::check('ToyJoy!Bootstrap2026', $administrator->password));
        $this->assertTrue($administrator->roles()->where('code', 'system-administrator')->exists());
        $this->assertSame(9, Role::query()->where('status', 'active')->count());
        $this->assertGreaterThan(0, $administrator->roles()->firstOrFail()->permissions()->where('status', 'active')->count());
        $this->post('/login', ['username' => 'admin', 'password' => 'ToyJoy!Bootstrap2026'])
            ->assertRedirect(config('fortify.home'));
        $this->assertAuthenticatedAs($administrator);

        $this->assertDatabaseHas('companies', ['code' => 'TOY-JOY', 'status' => 'active']);
        $this->assertDatabaseHas('branches', ['code' => 'MAIN', 'status' => 'active']);
        $this->assertDatabaseHas('stores', ['code' => 'MAIN-SALES', 'type' => 'selling', 'status' => 'active']);
        $this->assertDatabaseHas('stores', ['code' => 'MAIN-WAREHOUSE', 'type' => 'warehouse', 'status' => 'active']);
        $this->assertSame(1, BranchSellingStore::query()->where('status', 'active')->count());
        $this->assertDatabaseHas('payment_methods', ['code' => 'CASH', 'status' => 'active']);
        $this->assertDatabaseHas('tax_settings', ['code' => 'ZERO', 'rate' => '0.00', 'status' => 'active']);
        $this->assertGreaterThan(0, DocumentSequence::query()->where('status', 'active')->count());
        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(0, Customer::query()->count());
        $this->assertSame(0, StockMovement::query()->count());
    }

    public function test_rerunning_the_normal_seeder_preserves_the_admin_password_and_does_not_duplicate_baseline_records(): void
    {
        app(DatabaseSeeder::class)->run();

        $administrator = User::query()->where('username', 'admin')->firstOrFail();
        $administrator->forceFill(['password' => Hash::make('Changed-admin-password-2026')])->save();
        $company = Company::query()->where('code', 'TOY-JOY')->firstOrFail();
        $company->update(['name_en' => 'Owner configured company']);
        $sequence = DocumentSequence::query()->where('document_type', 'retail_sale')->firstOrFail();
        $sequence->advanceCounter(42);

        app(DatabaseSeeder::class)->run();

        $administrator->refresh();

        $this->assertTrue(Hash::check('Changed-admin-password-2026', $administrator->password));
        $this->assertSame(1, User::query()->where('username', 'admin')->count());
        $this->assertSame(1, Company::query()->where('code', 'TOY-JOY')->count());
        $this->assertSame(1, Branch::query()->where('code', 'MAIN')->count());
        $this->assertSame(1, Store::query()->where('code', 'MAIN-SALES')->count());
        $this->assertSame(1, Store::query()->where('code', 'MAIN-WAREHOUSE')->count());
        $this->assertSame(1, PaymentMethod::query()->where('code', 'CASH')->count());
        $this->assertSame(1, TaxSetting::query()->where('code', 'ZERO')->count());
        $this->assertSame('Owner configured company', $company->fresh()->name_en);
        $this->assertSame(42, $sequence->fresh()->next_value);
        $this->assertSame(0, Sale::query()->count());
        $this->assertSame(0, Customer::query()->count());
        $this->assertSame(0, StockMovement::query()->count());
    }
}
