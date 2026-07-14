<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected function schedule(Schedule $schedule): void
    {
        /*
        |--------------------------------------------------------------------------
        | STK Push Reconciliation
        |--------------------------------------------------------------------------
        | Currently disabled.
        |--------------------------------------------------------------------------
        */

        // $schedule->job(new \App\Jobs\ReconcileStkPushPaymentsJob())
        //     ->everyMinute()
        //     ->withoutOverlapping(5)
        //     ->onOneServer()
        //     ->timezone('Africa/Nairobi');

        /*
        |--------------------------------------------------------------------------
        | Incoming Transactions
        |--------------------------------------------------------------------------
        */

        $schedule->job(new \App\Jobs\ProcessTransactionsJob())
            ->everyMinute()
            ->withoutOverlapping(5)
            ->onOneServer()
            ->timezone('Africa/Nairobi');

        /*
        |--------------------------------------------------------------------------
        | Loan Disbursement Queue
        |--------------------------------------------------------------------------
        | Picks a maximum of 10 eligible loans created within the last 24 hours
        | and creates disbursement records for provider processing.
        |--------------------------------------------------------------------------
        */

        $schedule->command(
            'loans:queue-disbursements --limit=10'
        )
            ->everyMinute()
            ->withoutOverlapping(5)
            ->onOneServer()
            ->timezone('Africa/Nairobi');

        /*
        |--------------------------------------------------------------------------
        | NCBA Loan Disbursements
        |--------------------------------------------------------------------------
        | Processes one READY_TO_SEND NCBA disbursement every minute.
        |
        | The --live option permits submission to the configured NCBA endpoint.
        | In the current environment, this should point to the NCBA UAT endpoint.
        |--------------------------------------------------------------------------
        */

        $schedule->command(
            'ncba:process-loan-disbursements --live --limit=1'
        )
            ->everyMinute()
            ->withoutOverlapping(5)
            ->onOneServer()
            ->timezone('Africa/Nairobi')
            ->runInBackground();

        /*
        |--------------------------------------------------------------------------
        | NCBA Disbursement Confirmation
        |--------------------------------------------------------------------------
        | Checks the bank status of disbursements already submitted to NCBA.
        |--------------------------------------------------------------------------
        */

        $schedule->command(
            'ncba:confirm-loan-disbursements --limit=10'
        )
            ->everyMinute()
            ->withoutOverlapping(5)
            ->onOneServer()
            ->timezone('Africa/Nairobi')
            ->runInBackground();

        /*
        |--------------------------------------------------------------------------
        | Welcome Emails
        |--------------------------------------------------------------------------
        */

        $schedule->job(new \App\Jobs\SendWelcomeEmailJob())
            ->everyMinute()
            ->withoutOverlapping(5)
            ->onOneServer()
            ->timezone('Africa/Nairobi');

        /*
        |--------------------------------------------------------------------------
        | Guarantor Emails
        |--------------------------------------------------------------------------
        */

        $schedule->job(new \App\Jobs\SendGuarantorEmailJob())
            ->everyMinute()
            ->withoutOverlapping(5)
            ->onOneServer()
            ->timezone('Africa/Nairobi');

        /*
        |--------------------------------------------------------------------------
        | Pending Notifications
        |--------------------------------------------------------------------------
        */

        $schedule->job(new \App\Jobs\SendPendingNotificationsJob())
            ->everyMinute()
            ->withoutOverlapping(5)
            ->onOneServer()
            ->timezone('Africa/Nairobi');

        /*
        |--------------------------------------------------------------------------
        | Bulk SMS Outbox
        |--------------------------------------------------------------------------
        | Sends a maximum of 60 queued messages per invocation.
        | Provider requests begin no faster than one message per second.
        |--------------------------------------------------------------------------
        */

        $schedule->command(
            'bulk-sms:dispatch-outbox --limit=60'
        )
            ->everyMinute()
            ->withoutOverlapping(10)
            ->onOneServer()
            ->timezone('Africa/Nairobi')
            ->runInBackground();

        /*
        |--------------------------------------------------------------------------
        | FOSA Transaction Categorisation
        |--------------------------------------------------------------------------
        */

        $schedule->job(new \App\Jobs\CategorizeUnsortedFosaJob())
            ->everyFiveMinutes()
            ->withoutOverlapping(10)
            ->onOneServer()
            ->timezone('Africa/Nairobi');

        /*
        |--------------------------------------------------------------------------
        | Nightly Loan Recompute
        |--------------------------------------------------------------------------
        */

        $schedule->job(new \App\Jobs\UpdateMembersLoanBalancesJob())
            ->dailyAt('00:00')
            ->withoutOverlapping(60)
            ->onOneServer()
            ->timezone('Africa/Nairobi');

        /*
        |--------------------------------------------------------------------------
        | Nightly Guarantor Reset
        |--------------------------------------------------------------------------
        */

        $schedule->job(new \App\Jobs\ResetGuarantorsJob())
            ->dailyAt('00:30')
            ->withoutOverlapping(60)
            ->onOneServer()
            ->timezone('Africa/Nairobi');

        /*
        |--------------------------------------------------------------------------
        | Nightly Member Totals Update
        |--------------------------------------------------------------------------
        */

        $schedule->job(new \App\Jobs\UpdateMemberTotalsJob())
            ->dailyAt('01:00')
            ->withoutOverlapping(60)
            ->onOneServer()
            ->timezone('Africa/Nairobi');

        /*
        |--------------------------------------------------------------------------
        | Special Savings Interest Accrual
        |--------------------------------------------------------------------------
        | Checks for special-savings accounts whose monthly interest date is due.
        |
        | The command runs every minute from 02:00 through 03:59.
        | Each invocation processes a controlled batch of eligible accounts so
        | that large SACCOs do not overload the application or database server.
        |--------------------------------------------------------------------------
        */

        $schedule->command(
            'special-savings:process-due-interest'
        )
            ->everyMinute()
            ->between('02:00', '03:59')
            ->withoutOverlapping(10)
            ->onOneServer()
            ->timezone('Africa/Nairobi')
            ->runInBackground();

        /*
        |--------------------------------------------------------------------------
        | Special Savings Interest Vesting
        |--------------------------------------------------------------------------
        | Checks for accrued special-savings interest whose configured withdrawal
        | cycle has matured and moves it into available interest.
        |
        | The command runs every minute from 04:00 through 07:59, after monthly
        | interest processing and before normal official working hours.
        |--------------------------------------------------------------------------
        */

        $schedule->command(
            'special-savings:process-due-vesting'
        )
            ->everyMinute()
            ->between('04:00', '07:59')
            ->withoutOverlapping(10)
            ->onOneServer()
            ->timezone('Africa/Nairobi')
            ->runInBackground();
    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}