<?php

declare(strict_types=1);

namespace Tests\Feature\ClientFeedback;

use App\Modules\Customer\Actions\CreateCustomerAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CustomerLoyaltyFixtures;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/** Master request §39 — structured names keep legacy snapshots in sync. */
final class StructuredCustomerNameTest extends TestCase
{
    use CustomerLoyaltyFixtures;
    use PlatformFixtures;
    use RefreshDatabase;

    public function test_arabic_structured_names_are_required_and_english_names_are_optional(): void
    {
        $branch = $this->branch('CF39-NAME-BR');
        $store = $this->store($branch, 'CF39-NAME-ST');
        $administrator = $this->administrator('cf39-name-admin');
        $this->configureCustomerLoyaltyPolicies($administrator);

        $customer = app(CreateCustomerAction::class)->execute($administrator, $store, [
            'idempotency_key' => (string) Str::uuid(),
            'phone' => '01040000021',
            'first_name_ar' => 'ليلى',
            'last_name_ar' => 'علي',
            'consents' => [['purpose' => 'loyalty', 'status' => 'granted', 'source' => 'profile_create']],
        ]);

        self::assertSame('ليلى', $customer->first_name_ar);
        self::assertSame('علي', $customer->last_name_ar);
        self::assertSame('ليلى علي', $customer->name_ar);
        self::assertSame('ليلى علي', $customer->name_en, 'Legacy English snapshot falls back to Arabic when English is omitted.');
        self::assertNull($customer->first_name_en);
        self::assertNull($customer->last_name_en);
    }
}
