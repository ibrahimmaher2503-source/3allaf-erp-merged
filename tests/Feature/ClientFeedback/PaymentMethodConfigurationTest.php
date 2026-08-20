<?php

namespace Tests\Feature\ClientFeedback;

use App\Modules\Platform\Actions\SaveLocalSettingsAction;
use App\Modules\Platform\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

class PaymentMethodConfigurationTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanonicalAuthorization();
        $this->actingAs($this->administrator('cf-payment-config'));
    }

    public function test_cheque_is_a_supported_business_payment_type_but_cannot_be_offline_eligible(): void
    {
        $this->expectException(ValidationException::class);

        app(SaveLocalSettingsAction::class)->savePaymentMethod([
            'code' => 'CHEQUE', 'name_ar' => 'شيك', 'name_en' => 'Cheque',
            'type' => 'cheque', 'offline_eligible' => true, 'status' => 'active',
        ]);
    }

    public function test_cheque_can_be_saved_when_offline_use_is_disabled(): void
    {
        $method = app(SaveLocalSettingsAction::class)->savePaymentMethod([
            'code' => 'CHEQUE', 'name_ar' => 'شيك', 'name_en' => 'Cheque',
            'type' => 'cheque', 'offline_eligible' => false, 'status' => 'active',
        ]);

        self::assertSame('cheque', $method->type);
        self::assertFalse((bool) $method->offline_eligible);
        self::assertTrue(PaymentMethod::query()->whereKey($method->id)->exists());
    }
}
