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
        // Schedule for processing transactions
        $schedule->job(new \App\Jobs\ProcessTransactionsJob())
            ->everyMinute()
            ->withoutOverlapping();

        // Schedule for sending welcome emails
        $schedule->job(new \App\Jobs\SendWelcomeEmailJob())
            ->everyMinute()
            ->withoutOverlapping();

        // Schedule for sending guarantor emails
        $schedule->job(new \App\Jobs\SendGuarantorEmailJob())
            ->everyMinute()
            ->withoutOverlapping();

        // Schedule for processing loan emails
        $schedule->command('process:loan-emails')
            ->everyMinute()
            ->withoutOverlapping();

        // Log scheduler activity
        \Log::info('Scheduled jobs executed successfully.');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        // Load custom console commands
        $this->load(__DIR__ . '/Commands');

        // Load console routes
        require base_path('routes/console.php');
    }
}