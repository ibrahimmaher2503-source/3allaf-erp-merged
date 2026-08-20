<?php

namespace Tests\Feature\Platform;

use App\Modules\Platform\Models\AuditLog;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

class TranslationOverridesTest extends TestCase
{
    use DatabaseTransactions;
    use PlatformFixtures;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
    }

    public function test_the_translation_editor_route_requires_the_company_settings_edit_permission(): void
    {
        $this->get('/admin/translations')->assertRedirect('/login');

        $this->actingAs($this->userWith('translation-denied', ['accountant-reviewer']));
        $this->get('/admin/translations')->assertForbidden();

        $this->actingAs($this->administrator('translation-admin'));
        $this->get('/admin/translations')->assertOk();
    }

    public function test_an_administrator_can_override_json_and_grouped_translations_and_reset_them(): void
    {
        $administrator = $this->administrator('translation-save');
        $this->actingAs($administrator);

        Livewire::test('platform::admin.translation-editor')
            ->call('save', '*', 'Dashboard', 'لوحة مخصصة', 'Custom dashboard')
            ->assertHasNoErrors()
            ->call('save', 'offline', 'queue_title', 'طابور مخصص', 'Custom offline queue')
            ->assertHasNoErrors();

        app('translator')->setLoaded([]);
        app()->setLocale('ar');
        $this->assertSame('لوحة مخصصة', __('Dashboard'));
        $this->assertSame('طابور مخصص', __('offline.queue_title'));

        app()->setLocale('en');
        $this->assertSame('Custom dashboard', __('Dashboard'));
        $this->assertSame('Custom offline queue', __('offline.queue_title'));
        $this->assertDatabaseCount('translation_overrides', 4);
        $this->assertSame(4, AuditLog::query()->where('event', 'translation_override_saved')->count());

        Livewire::test('platform::admin.translation-editor')
            ->call('resetOverride', '*', 'Dashboard')
            ->assertHasNoErrors();

        app('translator')->setLoaded([]);
        app()->setLocale('en');
        $this->assertSame('Dashboard', __('Dashboard'));
        $this->assertDatabaseMissing('translation_overrides', ['locale' => 'ar', 'group' => '*', 'translation_key' => 'Dashboard']);
        $this->assertSame(2, AuditLog::query()->where('event', 'translation_override_reset')->count());
    }

    public function test_the_editor_rejects_unknown_keys_and_changed_placeholders(): void
    {
        $this->actingAs($this->administrator('translation-validation'));

        Livewire::test('platform::admin.translation-editor')
            ->call('save', '*', 'not.a.real.key', 'قيمة', 'Value')
            ->assertHasErrors(['translationKey'])
            ->call('save', '*', 'Records: :count', 'السجلات المخصصة: :count', 'Value without variable')
            ->assertHasErrors(['values.en']);

        $this->assertSame(0, DB::table('translation_overrides')->count());
        $this->assertSame(0, AuditLog::query()->whereIn('event', ['translation_override_saved', 'translation_override_reset'])->count());
    }

    public function test_a_bilingual_save_rolls_back_arabic_when_english_validation_fails(): void
    {
        $this->actingAs($this->administrator('translation-atomic'));

        Livewire::test('platform::admin.translation-editor')
            ->call('save', '*', 'Dashboard', 'لوحة تحكم مخصصة', 'Dashboard :count')
            ->assertHasErrors(['values.en']);

        $this->assertDatabaseCount('translation_overrides', 0);
        $this->assertSame(0, AuditLog::query()->whereIn('event', ['translation_override_saved', 'translation_override_reset'])->count());
    }

    public function test_reset_controls_require_confirmation_before_resetting_both_languages(): void
    {
        $this->actingAs($this->administrator('translation-confirm-reset'));

        $html = Livewire::test('platform::admin.translation-editor')->html();

        $this->assertGreaterThanOrEqual(2, substr_count($html, 'wire:confirm="Reset both Arabic and English values to the shipped wording?"'));
    }
}
