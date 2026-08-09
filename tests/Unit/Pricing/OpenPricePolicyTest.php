<?php

declare(strict_types=1);

namespace Tests\Unit\Pricing;

use App\Modules\Pricing\Services\OpenPricePolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Requirements: PRC-08, NFR-04. Test cases: TC-PRC-012, TC-OFF-005.
 */
final class OpenPricePolicyTest extends TestCase
{
    #[DataProvider('blockedRequests')]
    public function test_open_price_is_blocked_at_each_security_and_policy_boundary(
        string $requested,
        ?string $minimum,
        ?string $maximum,
        bool $permitted,
        ?string $reason,
        bool $offline,
    ): void {
        $result = (new OpenPricePolicy)->validate('100.00', $requested, $minimum, $maximum, $permitted, $reason, $offline);

        self::assertFalse($result['allowed']);
        self::assertNotSame('', $result['reason']);
    }

    /** @return array<string, array{string, ?string, ?string, bool, ?string, bool}> */
    public static function blockedRequests(): array
    {
        return [
            'missing permission' => ['100', '90', '110', false, 'reason', false],
            'offline' => ['100', '90', '110', true, 'reason', true],
            'bounds pending' => ['100', null, null, true, 'reason', false],
            'below minimum' => ['89.99', '90', '110', true, 'reason', false],
            'above maximum' => ['110.01', '90', '110', true, 'reason', false],
            'missing reason' => ['100', '90', '110', true, null, false],
        ];
    }

    public function test_boundary_values_are_allowed_with_permission_and_reason(): void
    {
        $policy = new OpenPricePolicy;

        self::assertTrue($policy->validate('100', '90', '90', '110', true, 'manager override')['allowed']);
        self::assertTrue($policy->validate('100', '110', '90', '110', true, 'manager override')['allowed']);
    }
}
