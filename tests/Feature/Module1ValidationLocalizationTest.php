<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Actions\SaveLocalSettingsAction;
use App\Modules\Platform\Models\Company;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class Module1ValidationLocalizationTest extends TestCase
{
    use DatabaseTransactions;

    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(static fn (User $user, string $ability): bool => true);

        Company::factory()->create();

        $this->administrator = User::factory()->create([
            'username' => 'qa-localization-admin',
            'status' => 'active',
            'is_super_admin' => true,
        ]);
    }

    /** @return array<string, array{string, string, string}> */
    public static function localizedPasswordLabels(): array
    {
        return [
            'English' => ['en', 'Password', 'The Password field must be at least 8 characters.'],
            'Arabic' => ['ar', 'كلمة المرور', 'طول كلمة المرور لازم يكون على الأقل 8 حروف.'],
            'Egyptian Arabic' => ['ar-EG', 'كلمة المرور', 'طول كلمة المرور لازم يكون على الأقل 8 حروف.'],
        ];
    }

    #[DataProvider('localizedPasswordLabels')]
    public function test_user_password_validation_uses_a_localized_field_label_in_the_livewire_response(string $locale, string $label, string $expectedMessage): void
    {
        app()->setLocale($locale);
        $this->withSession(['locale' => $locale])->actingAs($this->administrator);

        $component = Livewire::test('platform::admin.authorization-baseline')
            ->set('userForm.name', 'QA User')
            ->set('userForm.username', 'qa-localization-user')
            ->set('userForm.email', 'qa-localization-user@example.test')
            ->set('userForm.password', 'short')
            ->call('saveAuthorization')
            ->assertHasErrors(['userForm.password' => 'min']);

        $message = (string) $component->errors()->first('userForm.password');

        self::assertStringContainsString($label, $message);
        self::assertStringNotContainsString('userForm.password', $message);
        self::assertSame($expectedMessage, $message);
        $component->assertSee($message);
    }

    public function test_edit_user_password_validation_uses_the_optional_password_label(): void
    {
        app()->setLocale('en');
        $this->withSession(['locale' => 'en'])->actingAs($this->administrator);

        $component = Livewire::test('platform::admin.authorization-baseline')
            ->set('editingUserId', $this->administrator->id)
            ->set('userForm.name', $this->administrator->name)
            ->set('userForm.username', $this->administrator->username)
            ->set('userForm.email', $this->administrator->email)
            ->set('userForm.password', 'short')
            ->call('saveAuthorization')
            ->assertHasErrors(['userForm.password' => 'min']);

        $message = (string) $component->errors()->first('userForm.password');

        self::assertSame('The New password (optional) field must be at least 8 characters.', $message);
        self::assertStringNotContainsString('userForm.password', $message);
        $component->assertSee($message);
    }

    /** @return array<string, array{string, string, string}> */
    public static function localizedTaxRateLabels(): array
    {
        return [
            'English' => ['en', 'Tax Rate (%)', 'The Tax Rate (%) field must be at least 0.'],
            'Arabic' => ['ar', 'نسبة الضريبة (%)', 'الـ نسبة الضريبة (%) لازم يكون على الأقل 0.'],
            'Egyptian Arabic' => ['ar-EG', 'نسبة الضريبة (%)', 'الـ نسبة الضريبة (%) لازم يكون على الأقل 0.'],
        ];
    }

    #[DataProvider('localizedTaxRateLabels')]
    public function test_tax_rate_validation_uses_a_localized_field_label_in_the_livewire_response(string $locale, string $label, string $expectedMessage): void
    {
        app()->setLocale($locale);
        $this->withSession(['locale' => $locale])->actingAs($this->administrator);

        $component = Livewire::test('platform::admin.settings')
            ->set('activeTab', 'tax')
            ->set('taxSettingForm.rate', '-1')
            ->call('saveTaxSetting')
            ->assertHasErrors(['taxSettingForm.rate' => 'min']);

        $message = (string) $component->errors()->first('taxSettingForm.rate');

        self::assertStringContainsString($label, $message);
        self::assertStringNotContainsString('taxSettingForm.rate', $message);
        self::assertSame($expectedMessage, $message);
        $component->assertSee($message);
    }

    /** @return array<string, array{string, string}> */
    public static function directTaxRateRequiredMessages(): array
    {
        return [
            'English' => ['en', 'The Tax Rate (%) field is required.'],
            'Arabic' => ['ar', 'حقل نسبة الضريبة (%) ده مطلوب ومهم.'],
        ];
    }

    #[DataProvider('directTaxRateRequiredMessages')]
    public function test_direct_tax_rate_validation_uses_the_same_localized_field_label(string $locale, string $expectedMessage): void
    {
        app()->setLocale($locale);

        try {
            app(SaveLocalSettingsAction::class)->validatedTaxRate(['rate' => '']);
            self::fail('An empty tax rate was accepted by the direct action validator.');
        } catch (ValidationException $exception) {
            self::assertSame([$expectedMessage], $exception->errors()['taxSettingForm.rate'] ?? []);
        }
    }

    public function test_company_english_name_is_trimmed_before_storage(): void
    {
        app()->setLocale('en');
        $this->withSession(['locale' => 'en'])->actingAs($this->administrator);

        Livewire::test('platform::admin.settings')
            ->set('companyForm.name_en', '  QA  Company Name  ')
            ->call('saveCompany')
            ->assertHasNoErrors();

        self::assertSame('QA  Company Name', Company::query()->firstOrFail()->name_en);
    }
}
