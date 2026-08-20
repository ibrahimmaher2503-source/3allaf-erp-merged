<?php

declare(strict_types=1);

namespace Tests\Feature\ClientFeedback;

use App\Modules\Customer\Actions\CreateCustomerAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Tests\Support\CustomerLoyaltyFixtures;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/** Master request §40 — duplicate customer email must warn/fail closed, never merge. */
final class CustomerDuplicateEmailTest extends TestCase
{
    use CustomerLoyaltyFixtures;
    use PlatformFixtures;
    use RefreshDatabase;

    public function test_create_rejects_duplicate_email_without_creating_or_merging_a_profile(): void
    {
        $branch = $this->branch('CF40-EMAIL-BR');
        $store = $this->store($branch, 'CF40-EMAIL-ST');
        $administrator = $this->administrator('cf40-email-admin');
        $this->configureCustomerLoyaltyPolicies($administrator);

        $action = app(CreateCustomerAction::class);
        $payload = fn (string $phone, string $name): array => [
            'idempotency_key' => (string) Str::uuid(),
            'phone' => $phone,
            'name_ar' => $name,
            'name_en' => 'Email Duplicate '.$name,
            'email' => 'same@example.test',
            'consents' => [['purpose' => 'loyalty', 'status' => 'granted', 'source' => 'profile_create']],
        ];

        $first = $action->execute($administrator, $store, $payload('01040000001', 'عميل أول'));
        try {
            $action->execute($administrator, $store, $payload('01040000002', 'عميل ثان'));
            self::fail('A duplicate email must not create a second profile.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('email address', $exception->getMessage());
        }

        self::assertSame(1, $first->newQuery()->where('email', 'same@example.test')->count());
        self::assertSame(0, $first->newQuery()->where('phone_normalized', '01040000002')->count());
    }
}
