<?php

declare(strict_types=1);

namespace Tests\Feature\Retail;

use App\Models\User;
use App\Modules\Retail\Models\PosFinancialSettingVersion;
use App\Modules\Retail\Services\DiscountPolicy;
use App\Modules\Retail\Support\PosFinancialSettingRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * Requirements: POS-05. Policy: docs/48 §4, POSF-04 (DEC-066).
 * Test cases: TC-POS-040..046.
 *
 * `docs/48` §4 is explicit that a stack prevented only by a disabled button is
 * not enforcement, so these exercise the service directly rather than the UI.
 */
final class DiscountNonStackingTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    public function test_a_second_competing_discount_replaces_rather_than_stacks(): void
    {
        $policy = new DiscountPolicy;

        $resolution = $policy->resolveApplication(DiscountPolicy::TYPE_LINE, DiscountPolicy::TYPE_CUSTOMER_GROUP);

        self::assertSame('replace', $resolution['action'], 'POSF-04 resolved to replacement; discounts are never summed.');
        self::assertSame(DiscountPolicy::TYPE_LINE, $resolution['replaces']);
    }

    public function test_a_first_discount_applies_normally(): void
    {
        $resolution = (new DiscountPolicy)->resolveApplication(null, DiscountPolicy::TYPE_LINE);

        self::assertSame('apply', $resolution['action']);
        self::assertNull($resolution['replaces']);
    }

    public function test_an_invoice_discount_replaces_a_line_discount_instead_of_stacking(): void
    {
        $resolution = (new DiscountPolicy)->resolveApplication(DiscountPolicy::TYPE_LINE, DiscountPolicy::TYPE_INVOICE);

        self::assertSame('replace', $resolution['action']);
        self::assertSame(DiscountPolicy::TYPE_LINE, $resolution['replaces']);
    }

    public function test_replacing_a_discount_without_a_reason_is_rejected(): void
    {
        $this->configureLimit('100');
        $actor = $this->actor();

        $this->expectException(InvalidArgumentException::class);

        (new DiscountPolicy)->buildLineDiscount(
            $actor,
            discountAmount: '5.00',
            baseAmount: '100.00',
            newType: DiscountPolicy::TYPE_CUSTOMER_GROUP,
            existingType: DiscountPolicy::TYPE_LINE,
            reason: null,
        );
    }

    public function test_a_replacement_records_the_actor_and_reason(): void
    {
        $this->configureLimit('100');
        $actor = $this->actor();

        $result = (new DiscountPolicy)->buildLineDiscount(
            $actor,
            discountAmount: '5.00',
            baseAmount: '100.00',
            newType: DiscountPolicy::TYPE_CUSTOMER_GROUP,
            existingType: DiscountPolicy::TYPE_LINE,
            reason: 'Customer presented a loyalty card',
        );

        self::assertSame('5.00', $result['discount_amount']);
        self::assertSame(DiscountPolicy::TYPE_CUSTOMER_GROUP, $result['discount_type']);
        self::assertSame($actor->id, $result['discount_applied_by']);
        self::assertSame($actor->id, $result['discount_replaced_by']);
        self::assertNotNull($result['discount_replaced_at']);
        self::assertSame('Customer presented a loyalty card', $result['discount_reason']);
    }

    /**
     * The approval limit is an owner value. While unset we cannot silently
     * permit an arbitrary discount, so any non-zero discount needs approval.
     */
    public function test_any_discount_requires_approval_while_the_limit_is_unset(): void
    {
        self::assertNull(PosFinancialSettingRegistry::value(PosFinancialSettingRegistry::DISCOUNT_APPROVAL_LIMIT));

        $policy = new DiscountPolicy;

        self::assertTrue($policy->requiresApproval('0.01', '100.00'));
        self::assertFalse($policy->requiresApproval('0.00', '100.00'), 'No discount means nothing to approve.');
    }

    public function test_a_discount_within_the_configured_limit_does_not_require_approval(): void
    {
        $this->configureLimit('10');
        $policy = new DiscountPolicy;

        self::assertFalse($policy->requiresApproval('10.00', '100.00'), '10% is within a 10% limit.');
        self::assertTrue($policy->requiresApproval('10.01', '100.00'), 'Just above the limit must require approval.');
    }

    public function test_an_over_limit_discount_is_blocked_without_approval(): void
    {
        $this->configureLimit('10');
        $actor = $this->actor();

        $this->expectException(InvalidArgumentException::class);

        (new DiscountPolicy)->buildLineDiscount(
            $actor,
            discountAmount: '50.00',
            baseAmount: '100.00',
            newType: DiscountPolicy::TYPE_LINE,
            approved: false,
        );
    }

    public function test_an_over_limit_discount_is_permitted_once_approved(): void
    {
        $this->configureLimit('10');
        $actor = $this->actor();

        $result = (new DiscountPolicy)->buildLineDiscount(
            $actor,
            discountAmount: '50.00',
            baseAmount: '100.00',
            newType: DiscountPolicy::TYPE_LINE,
            approved: true,
        );

        self::assertSame('50.00', $result['discount_amount']);
    }

    private function configureLimit(string $percent): void
    {
        PosFinancialSettingVersion::query()->create([
            'key' => PosFinancialSettingRegistry::DISCOUNT_APPROVAL_LIMIT,
            'value' => $percent,
            'value_type' => 'text',
            'version' => 1,
        ]);
    }

    private function actor(): User
    {
        $this->seedCanonicalAuthorization();

        return $this->userWith('discount-actor', ['cashier']);
    }
}
