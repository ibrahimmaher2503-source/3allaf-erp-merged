<?php

declare(strict_types=1);

namespace Tests\Feature\ClientFeedback;

use Tests\TestCase;

final class ArabicCatalogTranslationTest extends TestCase
{
    public function test_catalog_labels_are_arabic_in_the_arabic_locale(): void
    {
        app()->setLocale('ar');

        foreach ([
            'All genders' => 'كل الأنواع',
            'All product types' => 'كل أنواع المنتجات',
            'Filter colour' => 'تصفية اللون',
            'Filter character' => 'تصفية الشخصية',
            'Full product card' => 'بطاقة المنتج الكاملة',
            'Product catalog' => 'كتالوج المنتجات',
            'Product Masters' => 'المنتجات الرئيسية',
            'View details' => 'عرض التفاصيل',
        ] as $source => $expected) {
            self::assertSame($expected, __($source));
        }
    }
}
