<?php

namespace Tests\Feature\Platform;

use Illuminate\Support\Facades\File;
use Tests\TestCase;
use ZipArchive;

/** @group tsk-001 */
class BackupOperationalTest extends TestCase
{
    public function test_restore_extracts_a_database_archive_only_into_an_isolated_target(): void
    {
        $root = storage_path('framework/testing/tsk001-backup');
        File::deleteDirectory($root);
        File::ensureDirectoryExists($root);
        $archive = $root.'/verified-backup.zip';
        $target = sys_get_temp_dir().DIRECTORY_SEPARATOR.'toyjoy-restore-'.uniqid();

        $zip = new ZipArchive;
        $this->assertSame(true, $zip->open($archive, ZipArchive::CREATE));
        $zip->addFromString('database.sql', "-- isolated restore fixture\n");
        $zip->addFromString('storage/app/private/attachment.txt', 'protected fixture');
        $zip->close();

        $this->artisan('platform:backup:restore', [
            'archive' => $archive,
            'target' => $target,
        ])->assertExitCode(0);

        $this->assertFileExists($target.'/database.sql');
        $this->assertSame('protected fixture', File::get($target.'/storage/app/private/attachment.txt'));

        File::deleteDirectory($root);
    }

    public function test_restore_rejects_a_live_application_target(): void
    {
        $archive = storage_path('framework/testing/tsk001-invalid.zip');
        File::ensureDirectoryExists(dirname($archive));
        $zip = new ZipArchive;
        $zip->open($archive, ZipArchive::CREATE);
        $zip->addFromString('database.sql', '-- fixture');
        $zip->close();

        $this->artisan('platform:backup:restore', [
            'archive' => $archive,
            'target' => base_path(),
        ])->assertExitCode(1);

        File::delete($archive);
    }

    public function test_restore_accepts_an_encrypted_archive_with_the_configured_password(): void
    {
        $root = storage_path('framework/testing/tsk001-encrypted-backup');
        File::deleteDirectory($root);
        File::ensureDirectoryExists($root);
        $archive = $root.'/encrypted.zip';
        $target = sys_get_temp_dir().DIRECTORY_SEPARATOR.'toyjoy-restore-'.uniqid();

        $zip = new ZipArchive;
        $this->assertSame(true, $zip->open($archive, ZipArchive::CREATE));
        $this->assertTrue($zip->addFromString('database.sql', '-- encrypted fixture'));
        $zip->setPassword('test-backup-password');
        $this->assertSame(true, $zip->setEncryptionName('database.sql', ZipArchive::EM_AES_256));
        $zip->close();

        config(['backup.backup.password' => 'test-backup-password']);

        $this->artisan('platform:backup:restore', [
            'archive' => $archive,
            'target' => $target,
        ])->assertExitCode(0);

        $this->assertFileExists($target.'/database.sql');
        File::deleteDirectory($root);
        File::deleteDirectory($target);
    }

    public function test_restore_rejects_a_nonexistent_target_inside_the_live_application(): void
    {
        $archive = storage_path('framework/testing/tsk001-target-guard.zip');
        File::ensureDirectoryExists(dirname($archive));
        $zip = new ZipArchive;
        $zip->open($archive, ZipArchive::CREATE);
        $zip->addFromString('database.sql', '-- fixture');
        $zip->close();

        $this->artisan('platform:backup:restore', [
            'archive' => $archive,
            'target' => base_path('storage/framework/testing/nonexistent-restore-target'),
        ])->assertExitCode(1);

        File::delete($archive);
    }

    public function test_production_backup_refuses_to_run_without_archive_encryption(): void
    {
        config([
            'app.env' => 'production',
            'backup.backup.password' => null,
        ]);

        $this->artisan('platform:backup:run', ['--only-files' => true])
            ->assertExitCode(1);
    }
}
