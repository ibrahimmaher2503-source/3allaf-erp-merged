<?php

namespace Tests\Feature\Audit;

use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * TSK-009 — Audit foundation: the `/admin/audit` Livewire screen.
 *
 * @group tsk-009
 */
class AuditScreenTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
    }

    private function event(string $event, ?int $branchId = null, ?int $storeId = null, string $category = 'master_data'): AuditLog
    {
        return app(RecordAuditEvent::class)->execute(
            category: $category,
            event: $event,
            branchId: $branchId,
            storeId: $storeId,
        );
    }

    /**
     * Row-level assertion. Event names also appear inside the filter <option>
     * lists, so list membership is asserted through each row's action hook.
     */
    private function rowMarker(AuditLog $record): string
    {
        return 'showAudit('.$record->id.')"';
    }

    public function test_the_audit_screen_is_permission_guarded(): void
    {
        $this->get('/admin/audit')->assertRedirect('/login');

        $this->actingAs($this->userWith('tsk009-screen-none'));
        $this->get('/admin/audit')->assertForbidden();
        Livewire::test('platform::system.audit-log')->assertForbidden();

        $this->actingAs($this->userWith('tsk009-screen-reviewer', ['accountant-reviewer']));
        $this->get('/admin/audit')->assertOk();
    }

    public function test_the_authorized_list_loads_and_shows_visible_events(): void
    {
        $this->actingAs($this->administrator('tsk009-screen-admin'));
        $branch = $this->branch('SCR-BR');
        AuditLog::query()->delete();

        $this->event('screen_probe_one', $branch->id);
        $this->event('screen_probe_two', $branch->id);

        Livewire::test('platform::system.audit-log')
            ->assertOk()
            ->assertSee('screen_probe_one')
            ->assertSee('screen_probe_two');
    }

    public function test_an_empty_scope_renders_the_empty_state(): void
    {
        $this->actingAs($this->administrator('tsk009-empty-setup'));
        $foreign = $this->branch('EMPTY-BR');
        AuditLog::query()->delete();
        $this->event('hidden_event', $foreign->id);

        $this->actingAs($this->userWith('tsk009-empty-reviewer', ['accountant-reviewer']));

        Livewire::test('platform::system.audit-log')
            ->assertSee('No audit events found')
            ->assertDontSee('hidden_event');
    }

    public function test_filters_narrow_the_visible_events(): void
    {
        $this->actingAs($this->administrator('tsk009-filters'));
        $branch = $this->branch('FLT-BR');
        AuditLog::query()->delete();

        $first = $this->event('filter_alpha', $branch->id);
        $this->event('filter_beta', $branch->id, null, 'workflow');

        $second = AuditLog::query()->where('event', 'filter_beta')->sole();

        Livewire::test('platform::system.audit-log')
            ->set('event', 'filter_alpha')
            ->assertSeeHtml($this->rowMarker($first))
            ->assertDontSeeHtml($this->rowMarker($second))
            ->set('event', '')
            ->set('category', 'workflow')
            ->assertSeeHtml($this->rowMarker($second))
            ->assertDontSeeHtml($this->rowMarker($first))
            ->set('category', '')
            ->set('search', $first->request_id)
            ->assertSeeHtml($this->rowMarker($first))
            ->assertDontSeeHtml($this->rowMarker($second));
    }

    public function test_a_filter_cannot_broaden_the_visible_scope(): void
    {
        $this->actingAs($this->administrator('tsk009-broaden-setup'));
        $assigned = $this->branch('BRD-IN');
        $foreign = $this->branch('BRD-OUT');
        AuditLog::query()->delete();
        $this->event('scoped_event', $assigned->id);
        $foreignEvent = $this->event('foreign_event', $foreign->id);

        $reviewer = $this->userWith('tsk009-broaden', ['accountant-reviewer'], false, [$assigned->id]);
        $this->actingAs($reviewer);

        Livewire::test('platform::system.audit-log')
            ->set('branchId', (string) $foreign->id)
            ->assertDontSeeHtml($this->rowMarker($foreignEvent))
            ->assertSee('No audit events found')
            ->set('branchId', '')
            ->set('search', $foreignEvent->request_id)
            ->assertDontSeeHtml($this->rowMarker($foreignEvent))
            ->assertSee('No audit events found');
    }

    public function test_pagination_returns_a_second_page_of_visible_events(): void
    {
        $this->actingAs($this->administrator('tsk009-pagination'));
        $branch = $this->branch('PAG-BR');
        AuditLog::query()->delete();

        $records = [];

        for ($index = 1; $index <= 25; $index++) {
            $records[$index] = $this->event(sprintf('paged_event_%02d', $index), $branch->id);
        }

        $component = Livewire::test('platform::system.audit-log');
        $component->assertSeeHtml($this->rowMarker($records[25]))
            ->assertDontSeeHtml($this->rowMarker($records[1]));

        $component->call('gotoPage', 2)
            ->assertSeeHtml($this->rowMarker($records[1]))
            ->assertDontSeeHtml($this->rowMarker($records[25]));
    }

    public function test_the_detail_modal_enforces_scope(): void
    {
        $this->actingAs($this->administrator('tsk009-detail-setup'));
        $assigned = $this->branch('DTL-IN');
        $foreign = $this->branch('DTL-OUT');
        AuditLog::query()->delete();
        $mine = $this->event('detail_visible', $assigned->id);
        $theirs = $this->event('detail_hidden', $foreign->id);

        $reviewer = $this->userWith('tsk009-detail', ['accountant-reviewer'], false, [$assigned->id]);
        $this->actingAs($reviewer);

        Livewire::test('platform::system.audit-log')
            ->call('showAudit', $mine->id)
            ->assertSet('selectedAuditId', $mine->id)
            ->assertSee($mine->event_id);

        Livewire::test('platform::system.audit-log')
            ->call('showAudit', $theirs->id)
            ->assertForbidden();
    }

    public function test_the_detail_modal_presents_redacted_values(): void
    {
        $this->actingAs($this->administrator('tsk009-detail-redaction'));
        AuditLog::query()->delete();

        $record = app(RecordAuditEvent::class)->execute(
            category: 'authorization',
            event: 'detail_redaction',
            before: ['password' => 'never-render-me'],
            after: ['nested' => ['access_token' => 'never-render-me-either']],
        );

        Livewire::test('platform::system.audit-log')
            ->call('showAudit', $record->id)
            ->assertDontSee('never-render-me')
            ->assertDontSee('never-render-me-either')
            ->assertSee('[redacted]');
    }

    public function test_closing_the_detail_modal_clears_the_selection(): void
    {
        $this->actingAs($this->administrator('tsk009-detail-close'));
        AuditLog::query()->delete();
        $record = $this->event('closeable_event');

        Livewire::test('platform::system.audit-log')
            ->call('showAudit', $record->id)
            ->assertSet('selectedAuditId', $record->id)
            ->call('closeAudit')
            ->assertSet('selectedAuditId', null);
    }

    public function test_the_mobile_card_layout_renders_the_same_scoped_server_state(): void
    {
        $this->actingAs($this->administrator('tsk009-mobile'));
        $branch = $this->branch('MOB-BR');
        AuditLog::query()->delete();
        $this->event('mobile_event', $branch->id);

        $html = Livewire::test('platform::system.audit-log')->html();

        // The compact card list and the desktop table are both server rendered.
        $this->assertStringContainsString('sm:hidden', $html);
        $this->assertStringContainsString('hidden sm:block', $html);
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'mobile_event'));
    }
}
