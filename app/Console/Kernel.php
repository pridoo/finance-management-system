<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Daily database backup at 5:00 PM Philippine time, saved as .sql in
        // storage/app/backup. Needs `php artisan schedule:run` every minute
        // (Windows Task Scheduler / cron); RunDailyBackup covers it otherwise.
        $schedule->command('backup:database --auto')
            ->dailyAt('17:00')
            ->timezone('Asia/Manila');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
