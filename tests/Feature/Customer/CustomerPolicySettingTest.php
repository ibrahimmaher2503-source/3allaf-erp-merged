<?php

declare(strict_types=1);

namespace Tests\Feature\Customer;

use App\Modules\Customer\Actions\SaveCustomerPolicySettingAction;
use App\Modules\Customer\Models\CustomerPolicySettingVersion;
use App\Modules\Customer\Support\CustomerPolicySettingRegistry;
use App\Modules\Platform\Models\AuditLog;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * TSK-027 local/dev boundary: policy versions are persisted and audited, but
 * customer, loyalty, wallet, and settlement mutations remain deferred.
 */
final class CustomerPolicySettingTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
    }

    public function test_authorized_policy_save_creates_an_audited_first_version(): void
    {
        $administrator = $this->administrator('customer-policy-admin');
        $this->actingAs($administrator);

        $setting = app(SaveCustomerPolicySettingAction::class)->execute(
            'loyalty.retail_rule',
            '  earn one point per EGP  ',
            '  Local policy reference  ',
        );

        $this->assertSame('loyalty.retail_rule', $setting->key);
        $this->assertSame('earn one point per EGP', $setting->value);
        $this->assertSame('Local policy reference', $setting->notes);
        $this->assertSame(1, $setting->version);
        $this->assertSame($administrator->id, $setting->created_by);
        $this->assertDatabaseHas('customer_policy_setting_versions', [
            'key' => 'loyalty.retail_rule',
            'version' => 1,
            'value' => 'earn one point per EGP',
        ]);

        $audit = AuditLog::query()->where('event', 'create_customer_policy_setting_version')->sole();
        $this->assertSame('customer_policy_settings', $audit->category);
        $this->assertSame(CustomerPolicySettingVersion::class, $audit->source_type);
        $this->assertSame((string) $setting->id, $audit->source_id);
        $this->assertSame('owner_approval_required', $audit->metadata['approval_state']);
        $this->assertSame('loyalty.retail_rule', $audit->metadata['setting_key']);
    }

    public function test_subsequent_policy_save_increments_only_that_setting_version(): void
    {
        $this->actingAs($this->administrator('customer-policy-versioning'));
        $action = app(SaveCustomerPolicySettingAction::class);

        $first = $action->execute('loyalty.expiry_policy', '30 days', null);
        $second = $action->execute('loyalty.expiry_policy', '60 days', null);
        $other = $action->execute('loyalty.party_rule', 'party rule', null);

        $this->assertSame(1, $first->version);
        $this->assertSame(2, $second->version);
        $this->assertSame(1, $other->version);
        $this->assertSame(3, CustomerPolicySettingVersion::query()->count());
        $this->assertSame(3, AuditLog::query()->where('event', 'create_customer_policy_setting_version')->count());
    }

    public function test_unknown_policy_key_is_rejected_without_a_row_or_audit(): void
    {
        $this->actingAs($this->administrator('customer-policy-invalid'));

        try {
            app(SaveCustomerPolicySettingAction::class)->execute('customer.not-registered', 'x', null);
            $this->fail('An unknown policy key was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('key', $exception->errors());
        }

        $this->assertDatabaseCount('customer_policy_setting_versions', 0);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'create_customer_policy_setting_version']);
    }

    public function test_policy_save_requires_company_settings_edit_permission(): void
    {
        $user = $this->userWith('customer-policy-denied', ['accountant-reviewer']);
        $this->actingAs($user);

        $this->assertFalse(Gate::allows('company_settings.edit'));
        $this->expectException(AuthorizationException::class);

        app(SaveCustomerPolicySettingAction::class)->execute('loyalty.retail_rule', 'x', null);
    }

    public function test_policy_versions_are_append_only(): void
    {
        $this->actingAs($this->administrator('customer-policy-immutable'));
        $setting = app(SaveCustomerPolicySettingAction::class)->execute('customer.history.visibility', 'retail', null);

        $this->expectException(LogicException::class);
        $setting->update(['value' => 'party']);
    }

    public function test_registry_exposes_documented_keys_for_the_local_slice(): void
    {
        $this->assertArrayHasKey('customer.phone_normalization', CustomerPolicySettingRegistry::all());
        $this->assertArrayHasKey('loyalty.retail_rule', CustomerPolicySettingRegistry::all());
        $this->assertArrayHasKey('party.final_readiness', CustomerPolicySettingRegistry::all());
        $this->assertArrayHasKey('quotation.conversion', CustomerPolicySettingRegistry::all());
        $this->assertArrayHasKey('report.export', CustomerPolicySettingRegistry::all());
        $this->assertArrayHasKey('export.formula_safety', CustomerPolicySettingRegistry::all());
    }
}
