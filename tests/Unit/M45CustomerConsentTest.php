<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class M45CustomerConsentTest extends TestCase
{
    public function test_customer_creation_defaults_every_configured_purpose_to_denied(): void
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/routes/customers.php');

        self::assertStringContainsString("'status' => 'denied'", $source);
        self::assertStringContainsString("CustomerPolicy::allowedPurposes('customer.consent.purpose')", $source);
        self::assertStringNotContainsString("'status' => 'granted',\n                    'source' => 'profile_create'", $source);
    }

    public function test_profile_exposes_independent_consent_states_and_auditable_history(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/pages/customers/show.blade.php');
        $action = file_get_contents(dirname(__DIR__, 2).'/app/Modules/Customer/Actions/RecordCustomerConsentAction.php');

        foreach (['granted', 'withdrawn', 'denied'] as $status) {
            self::assertStringContainsString("value=\"$status\"", $view);
        }
        foreach (['purpose', 'captured_at', 'captured_by', 'source'] as $field) {
            self::assertStringContainsString($field, $view.$action);
        }
        self::assertStringContainsString("'purpose'", $action);
    }

    public function test_transactional_customer_creation_does_not_require_marketing_consent(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2).'/routes/customers.php');

        self::assertStringContainsString("'source' => 'profile_create'", $routes);
        self::assertStringNotContainsString("'purpose' => 'marketing'", $routes);
    }
}
