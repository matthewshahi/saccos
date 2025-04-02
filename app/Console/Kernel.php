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
        $schedule->job(new \App\Jobs\ProcessTransactionsJob())
            ->everyMinute()
            ->withoutOverlapping();

        $schedule->job(new \App\Jobs\SendWelcomeEmailJob())
            ->everyMinute()
            ->withoutOverlapping();

        $schedule->job(new \App\Jobs\SendGuarantorEmailJob())
            ->everyMinute()
            ->withoutOverlapping();

        $schedule->job(new \App\Jobs\ResetGuarantorsJob())
            ->dailyAt('00:00')
            ->withoutOverlapping(); // prevent overlap if it runs long

        $schedule->job(new \App\Jobs\UpdateMembersLoanBalancesJob())
            ->dailyAt('00:00')
            ->withoutOverlapping()
            ->onOneServer(); // useful if using multiple servers

    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');
        require base_path('routes/console.php');
    }
}
