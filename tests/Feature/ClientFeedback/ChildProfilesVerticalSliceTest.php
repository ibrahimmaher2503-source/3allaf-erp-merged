<?php

declare(strict_types=1);

namespace Tests\Feature\ClientFeedback;

use App\Modules\Customer\Models\CustomerChild;
use App\Modules\Customer\Actions\CreateCustomerAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CustomerLoyaltyFixtures;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/** Master request §43 — customer child profiles, local-only vertical slice. */
final class ChildProfilesVerticalSliceTest extends TestCase
{
    use CustomerLoyaltyFixtures;
    use PlatformFixtures;
    use RefreshDatabase;

    public function test_it_creates_multiple_children_with_optional_english_and_supports_edit_and_deactivate(): void
    {
        $branch = $this->branch('CF43-BR');
        $store = $this->store($branch, 'CF43-ST');
        $administrator = $this->administrator('cf43-admin');
        $this->configureCustomerLoyaltyPolicies($administrator);
        $customer = $this->createTestCustomer($administrator, $store, '01043000001');
        $this->actingAs($administrator);

        $this->post(route('customers.children.store', $customer), [
            'name_ar' => 'طفل أول',
            'birth_date' => '2020-01-02',
        ])->assertRedirect()->assertSessionHas('success', 'Child profile recorded.');

        $first = CustomerChild::query()->where('customer_id', $customer->id)->sole();
        self::assertNull($first->name_en);
        self::assertSame('active', $first->status);

        $this->post(route('customers.children.store', $customer), ['name_ar' => 'طفل ثان'])->assertRedirect();
        self::assertSame(2, CustomerChild::query()->where('customer_id', $customer->id)->count());

        $this->patch(route('customers.children.update', [$customer, $first]), [
            'name_ar' => 'طفل أول محدث',
            'name_en' => 'First Child',
            'birth_date' => '2020-01-03',
        ])->assertRedirect()->assertSessionHas('success', 'Child profile updated.');
        self::assertSame('First Child', $first->fresh()->name_en);
        self::assertSame(2, $first->fresh()->lock_version);

        $this->post(route('customers.children.deactivate', [$customer, $first]))
            ->assertRedirect()
            ->assertSessionHas('success', 'Child profile deactivated.');
        self::assertSame('inactive', $first->fresh()->status);
    }

    public function test_arabic_name_is_required_and_sensitive_permission_protects_routes(): void
    {
        $branch = $this->branch('CF43-VALID-BR');
        $store = $this->store($branch, 'CF43-VALID-ST');
        $administrator = $this->administrator('cf43-valid-admin');
        $cashier = $this->userWith('cf43-valid-cashier', ['cashier'], branchIds: [$branch->id], storeIds: [$store->id]);
        $this->configureCustomerLoyaltyPolicies($administrator);
        $customer = $this->createTestCustomer($administrator, $store, '01043000002');

        $this->actingAs($administrator)
            ->from(route('customers.show', $customer))
            ->post(route('customers.children.store', $customer), ['name_en' => 'Only English'])
            ->assertRedirect(route('customers.show', $customer))
            ->assertSessionHasErrors('name_ar');

        $this->actingAs($cashier)
            ->post(route('customers.children.store', $customer), ['name_ar' => 'غير مصرح'])
            ->assertForbidden();
    }

    public function test_child_update_and_deactivate_cannot_cross_customer_boundary(): void
    {
        $branch = $this->branch('CF43-IDOR-BR');
        $store = $this->store($branch, 'CF43-IDOR-ST');
        $administrator = $this->administrator('cf43-idor-admin');
        $this->configureCustomerLoyaltyPolicies($administrator);
        $owner = $this->createTestCustomer($administrator, $store, '01043000003');
        $other = app(CreateCustomerAction::class)->execute($administrator, $store, [
            'idempotency_key' => (string) Str::uuid(), 'phone' => '01043000004',
            'name_ar' => 'عميل آخر', 'name_en' => 'Other Customer', 'email' => 'cf43-idor-other@example.test',
            'consents' => [['purpose' => 'loyalty', 'status' => 'granted', 'source' => 'profile_create']],
        ]);
        $this->actingAs($administrator)->post(route('customers.children.store', $owner), ['name_ar' => 'طفل محمي']);
        $child = CustomerChild::query()->where('customer_id', $owner->id)->sole();

        $this->actingAs($administrator)->patch(route('customers.children.update', [$other, $child]), ['name_ar' => 'محاولة'])->assertNotFound();
        $this->actingAs($administrator)->post(route('customers.children.deactivate', [$other, $child]))->assertNotFound();
        self::assertSame('active', $child->fresh()->status);
    }
}
