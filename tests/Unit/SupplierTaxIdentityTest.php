<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Catalog\Support\SupplierTaxIdentity;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class SupplierTaxIdentityTest extends TestCase
{
    public function test_egyptian_tax_ids_are_optional_and_normalized_to_nine_digits(): void
    {
        self::assertNull(SupplierTaxIdentity::normalizeTaxNumber('', 'EG'));
        self::assertSame('123456789', SupplierTaxIdentity::normalizeTaxNumber('١٢٣-٤٥٦-٧٨٩', 'EG'));
    }

    public function test_egyptian_tax_ids_must_have_exactly_nine_digits_when_present(): void
    {
        $this->expectException(InvalidArgumentException::class);

        SupplierTaxIdentity::normalizeTaxNumber('12345678', 'EG');
    }

    public function test_foreign_tax_ids_remain_free_form_when_present(): void
    {
        self::assertSame('VAT-DE 123', SupplierTaxIdentity::normalizeTaxNumber(' VAT-DE 123 ', 'DE'));
    }
}
