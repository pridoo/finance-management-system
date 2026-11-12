<?php

namespace App\Http\Middleware;

use App\Services\DatabaseBackup;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Safety net for the 5 PM backup.
 *
 * The real trigger is the Laravel scheduler (Console\Kernel), but that only
 * runs if Windows Task Scheduler / cron calls `php artisan schedule:run`. On a
 * plain XAMPP machine nobody sets that up, so: the first time anyone opens a
 * page at or after 5:00 PM and today's automatic backup is not on disk yet, it
 * is made right then — after the page has been sent.
 */
class RunDailyBackup
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (Carbon::now(DatabaseBackup::TIMEZONE)->hour < 17) {
            return;
        }

        $backup = app(DatabaseBackup::class);
        if ($backup->hasAutoBackupToday()) {
            return;
        }

        // Two pages opening at the same moment must not both write a backup.
        $lock = Cache::lock('daily-db-backup', 300);
        if (! $lock->get()) {
            return;
        }

        try {
            if (! $backup->hasAutoBackupToday()) {
                $backup->create('auto');
            }
        } catch (\Throwable $e) {
            report($e);
        } finally {
            $lock->release();
        }
    }
}
