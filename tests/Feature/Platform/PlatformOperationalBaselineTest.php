<?php

namespace Tests\Feature\Platform;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * TSK-001 — Platform and operational baseline.
 *
 * Covers only implemented behavior: the request-ID middleware, safe error
 * rendering, health-route authorization, and runtime configuration. Production
 * backup, monitoring, and restore integrations are not implemented and are not
 * simulated here.
 *
 * @group tsk-001
 */
class PlatformOperationalBaselineTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
    }

    public function test_a_server_generated_uuid_request_id_is_returned_on_a_normal_response(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $requestId = $response->headers->get('X-Request-ID');

        $this->assertNotNull($requestId, 'TSK-001 requires an X-Request-ID response header.');
        $this->assertTrue(Str::isUuid($requestId), 'An absent client request ID must be replaced by a server UUID.');
    }

    public function test_each_request_receives_a_distinct_generated_request_id(): void
    {
        $first = $this->get('/')->headers->get('X-Request-ID');
        $second = $this->get('/')->headers->get('X-Request-ID');

        $this->assertNotSame($first, $second);
    }

    public function test_a_valid_client_correlation_id_is_preserved_and_an_invalid_one_is_replaced(): void
    {
        $valid = 'CORRELATION-0123456789';

        $this->withHeader('X-Request-ID', $valid)
            ->get('/')
            ->assertHeader('X-Request-ID', $valid);

        $rejected = $this->withHeader('X-Request-ID', 'short')
            ->get('/')
            ->headers->get('X-Request-ID');

        $this->assertNotSame('short', $rejected);
        $this->assertTrue(Str::isUuid($rejected));

        $injected = $this->withHeader('X-Request-ID', "line\nbreak-injection-attempt")
            ->get('/')
            ->headers->get('X-Request-ID');

        $this->assertTrue(Str::isUuid($injected), 'A malformed request ID must not be echoed back into the response header.');
    }

    public function test_the_correlation_id_header_is_also_accepted(): void
    {
        $this->withHeader('X-Correlation-ID', 'CORRELATION-9876543210')
            ->get('/')
            ->assertHeader('X-Request-ID', 'CORRELATION-9876543210');
    }

    public function test_a_not_found_response_renders_the_safe_bilingual_error_page_with_a_request_id(): void
    {
        config(['app.debug' => false]);

        $response = $this->get('/this-route-does-not-exist');

        $response->assertNotFound();
        $response->assertSee('(404)', false);
        $this->assertNotNull($response->headers->get('X-Request-ID'));
    }

    public function test_a_forbidden_response_renders_the_safe_error_page(): void
    {
        config(['app.debug' => false]);

        $response = $this->get('/forbidden');

        $response->assertForbidden();
        $response->assertSee('(403)', false);
        $this->assertNotNull($response->headers->get('X-Request-ID'));
    }

    public function test_an_unexpected_server_error_renders_the_safe_error_page_without_leaking_internals(): void
    {
        config(['app.debug' => false]);

        Route::get('/tsk-001-explosion', function (): never {
            throw new RuntimeException('SECRET-INTERNAL-DETAIL base64:leaked-key');
        })->middleware('web');

        $response = $this->get('/tsk-001-explosion');

        $response->assertStatus(500);
        $response->assertDontSee('SECRET-INTERNAL-DETAIL', false);
        $response->assertDontSee('base64:', false);
        $response->assertDontSee('RuntimeException', false);
        $response->assertDontSee('vendor/laravel', false);
        $this->assertNotNull($response->headers->get('X-Request-ID'));
    }

    public function test_a_json_error_response_does_not_expose_a_stack_trace_or_secrets(): void
    {
        config(['app.debug' => false]);

        Route::get('/api/tsk-001-explosion', function (): never {
            throw new RuntimeException('SECRET-INTERNAL-DETAIL');
        })->middleware('web');

        $response = $this->getJson('/api/tsk-001-explosion');

        $response->assertStatus(500);
        $payload = $response->json();

        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('trace', $payload);
        $this->assertArrayNotHasKey('file', $payload);
        $this->assertArrayNotHasKey('line', $payload);
        $this->assertStringNotContainsString('SECRET-INTERNAL-DETAIL', $response->getContent());
        $this->assertStringNotContainsString(config('app.key'), $response->getContent());
    }

    public function test_a_json_not_found_response_is_json_and_safe(): void
    {
        config(['app.debug' => false]);

        $response = $this->getJson('/api/definitely-missing');

        $response->assertNotFound();
        $response->assertHeader('content-type', 'application/json');
        $this->assertArrayNotHasKey('trace', (array) $response->json());
    }

    public function test_the_maintenance_view_renders_bilingual_safe_content(): void
    {
        // The real maintenance driver is not toggled: `artisan down` writes into
        // the shared local application state. The rendered response is verified
        // instead; live maintenance mode remains manual verification.
        $this->assertTrue(view()->exists('errors.503'));

        Route::get('/tsk-001-maintenance-preview', fn () => response()->view('errors.503', [], 503))->middleware('web');

        $response = $this->get('/tsk-001-maintenance-preview');

        $response->assertStatus(503);
        $response->assertSee('(503)', false);
        $response->assertSee('النظام قيد الصيانة المؤقتة', false);
        $this->assertNotNull($response->headers->get('X-Request-ID'));
    }

    public function test_error_pages_do_not_leak_environment_secrets(): void
    {
        config(['app.debug' => false]);

        $response = $this->get('/this-route-does-not-exist');
        $content = $response->getContent();

        $this->assertStringNotContainsString(config('app.key'), $content);
        $this->assertStringNotContainsString('APP_KEY', $content);
        $this->assertStringNotContainsString('DB_DATABASE', $content);
    }

    public function test_the_framework_health_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_the_platform_health_screen_requires_authentication_and_permission(): void
    {
        $this->get('/admin/system/health')->assertRedirect('/login');

        $this->actingAs($this->userWith('tsk001-no-access'));
        $this->get('/admin/system/health')->assertForbidden();

        $this->actingAs($this->userWith('tsk001-reviewer', ['accountant-reviewer']));
        $this->get('/admin/system/health')->assertOk();
    }

    public function test_backup_status_route_is_authenticated_and_reports_verification_state(): void
    {
        $this->get('/admin/system/backups')->assertRedirect('/login');

        $this->actingAs($this->userWith('tsk001-reviewer', ['accountant-reviewer']));
        $response = $this->getJson('/admin/system/backups');

        $response->assertOk();
        $response->assertJsonStructure(['name', 'verify_backup', 'encrypted', 'destinations']);
        $this->assertTrue((bool) $response->json('verify_backup'));
    }

    public function test_runtime_queue_cache_and_session_configuration_is_resolvable(): void
    {
        $this->assertNotNull(config('queue.default'));
        $this->assertNotNull(config('cache.default'));
        $this->assertNotNull(config('session.driver'));
        $this->assertContains('ar', config('app.supported_locales', []));
        $this->assertContains('en', config('app.supported_locales', []));
    }
}
