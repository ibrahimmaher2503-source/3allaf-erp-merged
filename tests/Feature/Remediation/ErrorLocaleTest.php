<?php

declare(strict_types=1);

namespace Tests\Feature\Remediation;

use Tests\TestCase;

final class ErrorLocaleTest extends TestCase
{
    public function test_forbidden_and_not_found_surfaces_follow_the_active_english_locale(): void
    {
        app()->setLocale('en');

        $forbidden = response()->view('errors.403', [], 403)->getContent();
        $notFound = response()->view('errors.404', [], 404)->getContent();

        self::assertStringContainsString('Access denied', $forbidden);
        self::assertStringContainsString('You do not have permission', $forbidden);
        self::assertStringContainsString('Page not found', $notFound);
        self::assertStringContainsString('The page you requested', $notFound);
    }
}
