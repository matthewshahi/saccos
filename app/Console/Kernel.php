<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        // Reconcile STK push payments - currently disabled
        // $schedule->job(new \App\Jobs\ReconcileStkPushPaymentsJob())
        //     ->everyMinute()
        //     ->withoutOverlapping()
        //     ->onOneServer()
        //     ->timezone('Africa/Nairobi');

        // Process incoming transactions every minute
        $schedule->job(new \App\Jobs\ProcessTransactionsJob())
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer()
            ->timezone('Africa/Nairobi');
            // ->runInBackground();

        // NCBA loan disbursements - DRY RUN by default.
        // This processes only rows marked READY_TO_SEND.
        // It will NOT send money unless --live is added and .env allows live mode.
        $schedule->command('ncba:process-loan-disbursements --limit=1')
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer()
            ->timezone('Africa/Nairobi');
            // ->runInBackground();

        // Confirm NCBA disbursements already sent to bank.
        // This does not send money; it only checks transaction status.
        $schedule->command('ncba:confirm-loan-disbursements --limit=10')
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->timezone('Africa/Nairobi');

        // Welcome emails
        $schedule->job(new \App\Jobs\SendWelcomeEmailJob())
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer()
            ->timezone('Africa/Nairobi');
            // ->runInBackground();

        // Guarantor emails
        $schedule->job(new \App\Jobs\SendGuarantorEmailJob())
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer()
            ->timezone('Africa/Nairobi');
            // ->runInBackground();

        // Pending notifications
        $schedule->job(new \App\Jobs\SendPendingNotificationsJob())
            ->everyMinute()
            ->withoutOverlapping()
            ->onOneServer()
            ->timezone('Africa/Nairobi');

        // Categorize unsorted FOSA transactions
        $schedule->job(new \App\Jobs\CategorizeUnsortedFosaJob())
            ->everyFiveMinutes()
            ->withoutOverlapping()
            ->onOneServer()
            ->timezone('Africa/Nairobi');

        // Nightly loan recompute
        $schedule->job(new \App\Jobs\UpdateMembersLoanBalancesJob())
            ->dailyAt('00:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->timezone('Africa/Nairobi');
            // ->runInBackground();

        // Nightly guarantor reset
        $schedule->job(new \App\Jobs\ResetGuarantorsJob())
            ->dailyAt('00:30')
            ->withoutOverlapping()
            ->onOneServer()
            ->timezone('Africa/Nairobi');
            // ->runInBackground();

        // Nightly member totals update
        $schedule->job(new \App\Jobs\UpdateMemberTotalsJob())
            ->dailyAt('01:00')
            ->withoutOverlapping()
            ->onOneServer()
            ->timezone('Africa/Nairobi');
            // ->runInBackground();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}