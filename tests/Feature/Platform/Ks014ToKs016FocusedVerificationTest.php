<?php

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\AuditLog;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Focused KS-014..016 verification. These checks deliberately use the
 * disposable MySQL test schema; no local demonstration or developer data is
 * used as test evidence.
 *
 * @group ks014-016
 */
class Ks014ToKs016FocusedVerificationTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
    }

    public function test_viewer_redaction_bounded_csv_formula_safety_and_current_permission_are_enforced(): void
    {
        config(['audit.export_max_rows' => 4]);
        $branch = $this->branch('KS014-016');
        $administrator = $this->administrator('ks014-export-admin');
        $viewer = $this->restrictedAuditViewer($branch->id, true);
        $this->actingAs($administrator);

        foreach (['=SYNTH-015', '+SYNTH-015', '-SYNTH-015', '@SYNTH-015'] as $event) {
            app(RecordAuditEvent::class)->execute(
                category: 'security',
                event: $event,
                branchId: $branch->id,
                after: [
                    'customer_phone' => 'SYNTH-PHONE-014',
                    'unit_cost' => 'SYNTH-COST-014',
                    'wallet_balance' => 'SYNTH-WALLET-014',
                    'status' => 'approved',
                ],
            );
        }

        $this->actingAs($viewer);
        $response = $this->get(route('admin.audit.export'));
        $response->assertOk()
            ->assertDownload()
            ->assertHeader('X-Content-Type-Options', 'nosniff');
        self::assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $csv = $response->streamedContent();

        foreach (['=SYNTH-015', '+SYNTH-015', '-SYNTH-015', '@SYNTH-015'] as $value) {
            self::assertStringContainsString("'{$value}", $csv);
        }
        foreach (['SYNTH-PHONE-014', 'SYNTH-COST-014', 'SYNTH-WALLET-014'] as $value) {
            self::assertStringNotContainsString($value, $csv);
        }
        self::assertStringContainsString('[redacted:customer_permission]', $csv);
        self::assertStringContainsString('[redacted:cost_permission]', $csv);
        self::assertStringContainsString('[redacted:wallet_permission]', $csv);
        self::assertSame(1, AuditLog::query()->where('event', 'audit_log_exported')->count());

        // The source is retained exactly as recorded; export redaction does not
        // mutate append-only evidence.
        self::assertSame('SYNTH-PHONE-014', AuditLog::query()->where('event', '=SYNTH-015')->sole()->after_values['customer_phone']);

        config(['audit.export_max_rows' => 3]);
        $this->getJson(route('admin.audit.export'))->assertUnprocessable()->assertJsonValidationErrors('export');
        self::assertSame(1, AuditLog::query()->where('event', 'audit_log_exported')->count());

        config(['audit.export_max_rows' => null]);
        $this->getJson(route('admin.audit.export'))->assertUnprocessable()->assertJsonValidationErrors('export');

        // Revocation after the screen/export capability was previously granted
        // is checked by the next request, not by stale client state.
        $viewer->roles()->sync([]);
        $viewer = $viewer->fresh();
        $this->actingAs($viewer)->get('/admin/audit')->assertForbidden();
        $this->get(route('admin.audit.export'))->assertForbidden();
    }

    public function test_a_safe_fault_response_contains_a_request_id_and_rolls_back_the_staged_business_write(): void
    {
        config(['app.debug' => false]);
        $companyCode = 'KS016-'.str()->upper(str()->random(12));

        Route::get('/ks016-transaction-fault', function () use ($companyCode): never {
            DB::transaction(function () use ($companyCode): never {
                Company::query()->create([
                    'code' => $companyCode,
                    'name_ar' => 'KS016 fixture',
                    'name_en' => 'KS016 fixture',
                    'currency_code' => 'EGP',
                    'currency_symbol' => 'EGP',
                    'timezone' => 'UTC',
                    'locale_default' => 'en',
                    'status' => 'active',
                ]);

                throw new RuntimeException('KS016-SYNTHETIC-SECRET /var/private/ks016.sql');
            });
        })->middleware('web');

        $response = $this->get('/ks016-transaction-fault');

        $response->assertStatus(500)
            ->assertDontSee('KS016-SYNTHETIC-SECRET', false)
            ->assertDontSee('/var/private/ks016.sql', false)
            ->assertDontSee('RuntimeException', false);
        self::assertNotNull($response->headers->get('X-Request-ID'));
        self::assertDatabaseMissing('companies', ['code' => $companyCode]);
    }

    public function test_safe_unmatched_error_page_uses_the_persisted_rtl_locale_cookie(): void
    {
        $response = $this->withCookie('locale', 'ar')->get('/ks016-rtl-missing');

        $response->assertNotFound()
            ->assertSee('dir="rtl"', false)
            ->assertSee('الصفحة غير موجودة', false);
        self::assertNotNull($response->headers->get('X-Request-ID'));
    }

    private function restrictedAuditViewer(int $branchId, bool $withExport): User
    {
        $role = Role::query()->create([
            'code' => 'ks014-audit-viewer',
            'name_ar' => 'KS014 Audit viewer',
            'name_en' => 'KS014 Audit viewer',
            'status' => 'active',
        ]);
        $permissions = ['audit_logs.view'];
        if ($withExport) {
            $permissions[] = 'audit_logs.export';
        }
        $role->permissions()->sync(Permission::query()->whereIn('code', $permissions)->pluck('id'));

        return $this->userWith('ks014-audit-viewer', [$role->code], false, [$branchId]);
    }
}
