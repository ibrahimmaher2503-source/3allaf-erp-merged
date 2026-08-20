<?php

declare(strict_types=1);

namespace Tests\Feature\ClientFeedback;

use App\Modules\Customer\Support\PhoneNormalizer;
use App\Modules\Platform\Actions\SaveBranchAction;
use App\Modules\Platform\Models\PaymentMethod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Livewire\Livewire;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

final class BatchAVerificationTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCanonicalAuthorization();
    }

    public function test_egyptian_phone_forms_validate_and_branch_save_stores_the_canonical_national_number(): void
    {
        self::assertSame('01012345678', PhoneNormalizer::normalize('+20 1012345678'));
        self::assertSame('01012345678', PhoneNormalizer::normalize('٠١٠١٢٣٤٥٦٧٨'));

        $validation = Validator::make(
            ['phone' => '0101234'],
            ['phone' => ['required', PhoneNormalizer::validationRule()]],
        );
        self::assertTrue($validation->fails());
        self::assertStringContainsString('Enter a valid Egyptian phone number', $validation->errors()->first('phone'));

        $this->actingAs($this->administrator('batch-a-phone'));
        $this->company();
        $branch = app(SaveBranchAction::class)->execute([
            'code' => 'BATCH-A-PHONE',
            'name_ar' => 'فرع اختبار الهاتف',
            'name_en' => 'Batch A Phone Branch',
            'phone' => '+20 1012345678',
            'timezone' => 'Africa/Cairo',
            'status' => 'active',
        ]);

        self::assertSame('01012345678', $branch->fresh()->phone);
    }

    public function test_offline_payment_validation_rejects_card_and_accepts_electronic_wallet_semantics(): void
    {
        $this->actingAs($this->administrator('batch-a-payment'));

        Livewire::test('platform::admin.settings')
            ->set('paymentMethodForm.code', 'BATCH-A-CARD')
            ->set('paymentMethodForm.name_ar', 'بطاقة اختبار')
            ->set('paymentMethodForm.name_en', 'Batch A Card')
            ->set('paymentMethodForm.type', 'card')
            ->set('paymentMethodForm.offline_eligible', true)
            ->call('savePaymentMethod')
            ->assertHasErrors(['paymentMethodForm.offline_eligible']);

        Livewire::test('platform::admin.settings')
            ->set('paymentMethodForm.code', 'BATCH-A-WALLET')
            ->set('paymentMethodForm.name_ar', 'محفظة اختبار')
            ->set('paymentMethodForm.name_en', 'Batch A Wallet')
            ->set('paymentMethodForm.type', 'manual_electronic')
            ->set('paymentMethodForm.requires_evidence', true)
            ->set('paymentMethodForm.offline_eligible', true)
            ->call('savePaymentMethod')
            ->assertHasNoErrors();

        $method = PaymentMethod::query()->where('code', 'BATCH-A-WALLET')->firstOrFail();
        self::assertTrue((bool) $method->requires_evidence);
        self::assertTrue((bool) $method->offline_eligible);
        self::assertSame('manual_electronic', $method->type);
    }
}
