<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RunPlatformBackup extends Command
{
    protected $signature = 'platform:backup:run {--only-db} {--only-files}';

    protected $description = 'Run the configured verified platform backup with production safety checks';

    public function handle(): int
    {
        if (! @chdir(base_path())) {
            $this->error('The application base directory is not available for the backup process.');
            Log::critical('Production backup refused because the application base directory is unavailable.');

            return self::FAILURE;
        }

        if (config('app.env') === 'production' && blank(config('backup.backup.password'))) {
            $this->error('BACKUP_ARCHIVE_PASSWORD must be configured before a production backup can run.');
            Log::critical('Production backup refused because archive encryption is not configured.');

            return self::FAILURE;
        }

        $options = [
            '--disable-notifications' => true,
            '--isolated' => true,
        ];

        if ($this->option('only-db')) {
            $options['--only-db'] = true;
        }

        if ($this->option('only-files')) {
            $options['--only-files'] = true;
        }

        $exitCode = Artisan::call('backup:run', $options, $this->output);

        return $exitCode === self::SUCCESS ? self::SUCCESS : self::FAILURE;
    }
}
