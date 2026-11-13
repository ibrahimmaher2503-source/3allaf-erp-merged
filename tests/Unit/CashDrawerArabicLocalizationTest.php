<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class CashDrawerArabicLocalizationTest extends TestCase
{
    public function test_cash_drawer_setup_labels_are_translated_in_both_arabic_locales(): void
    {
        $keys = [
            'Eligible cashiers',
            'Search cashiers',
            'Default Assigned Cashier (Optional)',
            'Unassigned / Any Cashier',
            'Treasury cash account',
            'Select treasury cash account',
            'Status',
            'Active',
            'Operational notes',
            'Local Administrator',
        ];

        foreach (['ar.json', 'ar-EG.json'] as $file) {
            $translations = json_decode(
                file_get_contents(dirname(__DIR__, 2).'/lang/'.$file),
                true,
                flags: JSON_THROW_ON_ERROR,
            );

            foreach ($keys as $key) {
                self::assertArrayHasKey($key, $translations, $file.': '.$key);
                self::assertNotSame($key, $translations[$key], $file.': '.$key);
            }
        }
    }

    public function test_cashier_names_use_translation_when_a_known_system_name_is_available(): void
    {
        $view = file_get_contents(dirname(__DIR__, 2).'/resources/views/platform/admin/drawers.blade.php');

        self::assertStringContainsString("{{ __(\$u->name) }} ({{ \$u->username ?? \$u->email }})", $view);
    }
}
