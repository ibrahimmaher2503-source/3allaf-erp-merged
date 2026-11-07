<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Modules\Catalog\Exceptions\StaleCatalogRecordException;
use App\Support\UserSafeError;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

final class UserSafeErrorTest extends TestCase
{
    public function test_validation_messages_are_visible_to_the_user(): void
    {
        $exception = ValidationException::withMessages([
            'approval' => ['The refund creator cannot approve their own refund.'],
        ]);

        self::assertSame(
            'The refund creator cannot approve their own refund.',
            UserSafeError::message($exception),
        );
    }

    public function test_domain_argument_messages_are_visible_to_the_user(): void
    {
        self::assertSame(
            'Correct linked distribution transfers before reversing this invoice.',
            UserSafeError::message(new InvalidArgumentException('Correct linked distribution transfers before reversing this invoice.')),
        );
    }

    public function test_supplier_stale_record_messages_are_visible_to_the_user(): void
    {
        self::assertSame(
            'This supplier master record changed in another session. Reload it before saving.',
            UserSafeError::message(new StaleCatalogRecordException('This supplier master record changed in another session. Reload it before saving.')),
        );
    }

    public function test_unexpected_messages_keep_the_generic_fallback(): void
    {
        self::assertSame(
            'Unable to complete the request. Please review the data and try again.',
            UserSafeError::message(new RuntimeException('internal details')),
        );
    }
}
