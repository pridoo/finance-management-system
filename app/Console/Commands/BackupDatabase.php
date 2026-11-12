<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackup;
use Illuminate\Console\Command;

/**
 * php artisan backup:database          → manual backup
 * php artisan backup:database --auto   → the daily 5 PM backup (see Console\Kernel)
 */
class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--auto : Mark this as the scheduled daily backup}';

    protected $description = 'Save a .sql backup of the database to storage/app/backup';

    public function handle(DatabaseBackup $backup): int
    {
        $name = $backup->create($this->option('auto') ? 'auto' : 'manual');
        $this->info("Backup saved: storage/app/backup/{$name}");

        return self::SUCCESS;
    }
}
