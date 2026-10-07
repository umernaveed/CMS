<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $schedule->command('backups:automatic daily')->dailyAt('02:00')->withoutOverlapping();
        $schedule->command('backups:automatic weekly')->weeklyOn(1, '02:30')->withoutOverlapping();
        $schedule->command('backups:automatic monthly')->monthlyOn(1, '03:00')->withoutOverlapping();
        $schedule->command('crm:send-bulk-emails --limit=100')->everyMinute()->withoutOverlapping();
        $schedule->command('invoices:backfill-storage-fees --apply')->dailyAt('00:15')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
