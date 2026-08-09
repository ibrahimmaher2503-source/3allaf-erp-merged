<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Platform\Actions\AuthorizeAttachmentAccess;
use App\Modules\Platform\Actions\DeliverAttachment;
use App\Modules\Platform\Actions\RecordAuditEvent;
use App\Modules\Platform\Actions\StoreAttachment;
use App\Modules\Platform\Actions\ValidateAttachment;
use App\Modules\Platform\Data\AttachmentSourceReference;
use App\Modules\Platform\Enums\AttachmentState;
use App\Modules\Platform\Models\Attachment;
use App\Modules\Platform\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Mockery\MockInterface;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\PlatformFixtures;
use Tests\TestCase;

/**
 * AC-XCUT-04/05/15 and NFR-04: protected attachment foundation.
 *
 * Source-specific business attachment workflows remain outside this test;
 * these assertions cover only the implemented Platform boundary.
 */
final class AttachmentFoundationTest extends TestCase
{
    use PlatformFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCanonicalAuthorization();
        Storage::fake('local');
    }

    public function test_a_valid_linked_attachment_is_private_active_and_audited(): void
    {
        $user = $this->administrator('attachment-uploader');
        $this->actingAs($user);

        $attachment = app(StoreAttachment::class)->execute(
            UploadedFile::fake()->image('product-photo.png', 12, 8),
            'product_image',
            new AttachmentSourceReference(Product::class, 'product-1'),
            fn (User $actor, AttachmentSourceReference $source): bool => $actor->is_super_admin
                && $source->sourceType === Product::class
                && $source->sourceId === 'product-1',
        );

        $this->assertSame(AttachmentState::Active, $attachment->status);
        $this->assertSame('private', $attachment->visibility);
        $this->assertSame('product-photo.png', $attachment->original_filename);
        $this->assertSame('image/png', $attachment->detected_mime_type);
        $this->assertNotEmpty($attachment->request_id);
        Storage::disk('local')->assertExists($attachment->storage_path);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'attachment_stored',
            'source_type' => Attachment::class,
            'source_id' => $attachment->id,
        ]);
    }

    public function test_empty_attachment_is_rejected_without_storage_or_database_side_effects(): void
    {
        $this->actingAs($this->administrator('attachment-empty'));

        try {
            app(StoreAttachment::class)->execute(
                UploadedFile::fake()->create('empty.jpg', 0, 'image/jpeg'),
                'product_image',
            );
            $this->fail('An empty attachment was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }

        $this->assertDatabaseCount('attachments', 0);
        $this->assertDatabaseMissing('audit_logs', ['event' => 'attachment_stored']);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_mime_extension_mismatch_and_script_like_double_extension_are_rejected(): void
    {
        $validator = app(ValidateAttachment::class);

        try {
            $validator->execute(UploadedFile::fake()->create('payload.jpg', 1, 'text/plain'), 'product_image');
            $this->fail('A text payload with a JPG extension was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }

        try {
            $validator->execute(UploadedFile::fake()->image('payload.php.jpg', 12, 8), 'product_image');
            $this->fail('A script-like double extension was accepted.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('file', $exception->errors());
        }
    }

    public function test_attachment_over_configured_size_is_rejected_before_storage(): void
    {
        config()->set('attachments.limits.product_image', 1);

        $this->expectException(ValidationException::class);
        app(ValidateAttachment::class)->execute(
            UploadedFile::fake()->create('too-large.jpg', 2, 'image/jpeg'),
            'product_image',
        );
    }

    public function test_traversal_filename_is_reduced_to_a_basename_and_never_becomes_a_storage_path(): void
    {
        $validated = app(ValidateAttachment::class)->execute(
            UploadedFile::fake()->image('../../private/secret.png', 12, 8),
            'product_image',
        );

        $this->assertSame('secret.png', $validated->originalFilename);
        $this->assertStringNotContainsString('..', $validated->originalFilename);

        $this->actingAs($this->administrator('attachment-traversal'));
        $attachment = app(StoreAttachment::class)->execute(
            UploadedFile::fake()->image('../../private/secret.png', 12, 8),
            'product_image',
        );

        $this->assertStringStartsWith('attachments/product_image/', $attachment->storage_path);
        $this->assertStringNotContainsString('..', $attachment->storage_path);
        $this->assertStringNotContainsString('private', $attachment->storage_path);
        Storage::disk('local')->assertExists($attachment->storage_path);
    }

    public function test_linked_upload_requires_source_authorization_and_in_scope_branch(): void
    {
        $branchA = $this->branch('ATT-A');
        $branchB = $this->branch('ATT-B');
        $user = $this->userWith('attachment-scoped', ['cashier'], false, [$branchA->id]);
        $this->actingAs($user);

        $this->expectException(HttpException::class);
        app(StoreAttachment::class)->execute(
            UploadedFile::fake()->image('scoped.png', 12, 8),
            'product_image',
            new AttachmentSourceReference(Product::class, 'foreign-product', $branchB->id),
            fn (): bool => true,
        );

        $this->assertDatabaseCount('attachments', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_linked_upload_denied_by_source_authorizer_has_no_orphan_file(): void
    {
        $this->actingAs($this->administrator('attachment-source-denied'));

        try {
            app(StoreAttachment::class)->execute(
                UploadedFile::fake()->image('source-denied.png', 12, 8),
                'product_image',
                new AttachmentSourceReference(Product::class, 'not-authorized'),
                fn (): bool => false,
            );
            $this->fail('A source-authorizer denial was ignored.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('attachments', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_protected_attachment_storage_rejects_a_public_disk(): void
    {
        config()->set('attachments.disk', 'public');
        $this->actingAs($this->administrator('attachment-public-disk'));

        $this->expectException(LogicException::class);
        app(StoreAttachment::class)->execute(
            UploadedFile::fake()->image('public.png', 12, 8),
            'product_image',
        );
    }

    public function test_failed_database_transaction_removes_the_stored_file_and_attachment_row(): void
    {
        $this->actingAs($this->administrator('attachment-rollback'));
        $this->mock(RecordAuditEvent::class, function (MockInterface $mock): void {
            $mock->shouldReceive('execute')->once()->andThrow(new LogicException('forced audit failure'));
        });

        try {
            app(StoreAttachment::class)->execute(
                UploadedFile::fake()->image('rollback.png', 12, 8),
                'product_image',
                new AttachmentSourceReference(Product::class, 'product-rollback'),
                fn (): bool => true,
            );
            $this->fail('The forced audit failure did not roll back the attachment.');
        } catch (LogicException $exception) {
            $this->assertSame('forced audit failure', $exception->getMessage());
        }

        $this->assertDatabaseCount('attachments', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_delivery_requires_active_unexpired_in_scope_source_authorized_attachment_and_redacts_filename_path(): void
    {
        $branch = $this->branch('ATT-DELIVER');
        $user = $this->userWith('attachment-delivery', ['cashier'], false, [$branch->id]);
        $this->actingAs($user);
        $path = 'attachments/product_image/2026/08/private-file.png';
        Storage::disk('local')->put($path, 'attachment-body');
        $attachment = $this->makeAttachment($user, $branch->id, $path, '../../private/file.png');

        $response = app(DeliverAttachment::class)->execute(
            $attachment,
            fn (User $actor, Attachment $source): bool => $actor->id === $user->id
                && $source->source_type === Product::class
                && $source->source_id === 'product-1',
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('image/png', $response->headers->get('Content-Type'));
        $this->assertStringNotContainsString('..', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('file.png', (string) $response->headers->get('Content-Disposition'));
        $this->assertSame(1, AuditLog::query()->where('event', 'attachment_accessed')->count());

        ob_start();
        $response->sendContent();
        $body = ob_get_clean();
        $this->assertSame('attachment-body', $body);
    }

    public function test_delivery_denies_out_of_scope_or_expired_attachment_without_audit(): void
    {
        $branchA = $this->branch('ATT-SCOPE-A');
        $branchB = $this->branch('ATT-SCOPE-B');
        $user = $this->userWith('attachment-delivery-scope', ['cashier'], false, [$branchA->id]);
        $this->actingAs($user);
        $path = 'attachments/product_image/2026/08/foreign.png';
        Storage::disk('local')->put($path, 'foreign');
        $attachment = $this->makeAttachment($user, $branchB->id, $path, 'foreign.png');

        try {
            app(AuthorizeAttachmentAccess::class)->execute($attachment, fn (): bool => true);
            $this->fail('An out-of-scope attachment was authorized.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $attachment->mutate(['expires_at' => now()->subMinute()]);

        try {
            app(AuthorizeAttachmentAccess::class)->execute($attachment, fn (): bool => true);
            $this->fail('An expired attachment was authorized.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_attachment_model_rejects_direct_update_and_delete(): void
    {
        $user = $this->administrator('attachment-immutable');
        $attachment = $this->makeAttachment($user, null, 'attachments/immutable.png', 'immutable.png');

        try {
            $attachment->update(['original_filename' => 'changed.png']);
            $this->fail('A direct attachment update was accepted.');
        } catch (LogicException $exception) {
            $this->assertStringContainsString('named action', $exception->getMessage());
        }

        $this->expectException(LogicException::class);
        $attachment->delete();
    }

    private function makeAttachment(User $user, ?int $branchId, string $path, string $filename): Attachment
    {
        return Attachment::query()->create([
            'id' => (string) Str::uuid(),
            'source_type' => Product::class,
            'source_id' => 'product-1',
            'purpose' => 'product_image',
            'original_filename' => $filename,
            'storage_filename' => basename($path),
            'storage_disk' => 'local',
            'storage_path' => $path,
            'mime_type' => 'image/png',
            'detected_mime_type' => 'image/png',
            'extension' => 'png',
            'size_bytes' => 15,
            'sha256' => hash('sha256', $filename),
            'uploaded_by' => $user->id,
            'branch_id' => $branchId,
            'visibility' => 'private',
            'status' => AttachmentState::Active,
            'request_id' => 'ATTACHMENT-REQUEST-0001',
        ]);
    }
}
