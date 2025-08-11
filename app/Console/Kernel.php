<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Process incoming transactions every minute
        $schedule->job(new \App\Jobs\ProcessTransactionsJob())
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer()
            ->timezone('Africa/Nairobi')
            ->runInBackground();

        // Welcome emails (lightweight; still staggered safely)
        $schedule->job(new \App\Jobs\SendWelcomeEmailJob())
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer()
            ->timezone('Africa/Nairobi')
            ->runInBackground();

        // Guarantor emails
        $schedule->job(new \App\Jobs\SendGuarantorEmailJob())
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer()
            ->timezone('Africa/Nairobi')
            ->runInBackground();

        // Nightly resets
        $schedule->job(new \App\Jobs\ResetGuarantorsJob())
            ->dailyAt('00:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->timezone('Africa/Nairobi')
            ->runInBackground();

        // Nightly loan recompute (if you still use it)
        $schedule->job(new \App\Jobs\UpdateMembersLoanBalancesJob())
            ->dailyAt('00:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->timezone('Africa/Nairobi')
            ->runInBackground();

        // Nightly aggregates (shares/fosa/capital) at 01:00
        $schedule->job(new \App\Jobs\UpdateMemberAggregatesJob())
            ->dailyAt('01:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->timezone('Africa/Nairobi')
            ->runInBackground();
    }

    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}