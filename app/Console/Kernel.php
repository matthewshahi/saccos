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
        | Automatic Loan Approval
        |--------------------------------------------------------------------------
        | Processes a maximum of 10 pending self-service loan applications whose
        | loan products have automatic approval enabled.
        |
        | Only applications submitted within the last 30 days are eligible for
        | automatic approval. Older applications remain pending and require
        | manual approval.
        |
        | The command must revalidate all applicable product, member, amount,
        | duration, qualification, guarantor, charge and accounting conditions
        | before approving an application.
        |--------------------------------------------------------------------------
        */

        $schedule->command(
            'loans:process-auto-approvals --limit=10 --max-age-days=30'
        )
            ->everyMinute()
            ->withoutOverlapping(5)
            ->onOneServer()
            ->timezone('Africa/Nairobi');

        /*
        |--------------------------------------------------------------------------
        | Loan Disbursement Queue
        |--------------------------------------------------------------------------
        | Picks a maximum of 10 eligible approved loans created within the last
        | 24 hours and creates disbursement records for provider processing.
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
        | Automatic Loan Default Interest
        |--------------------------------------------------------------------------
        | Processes a maximum of 20 eligible defaulted loans per minute.
        |
        | Runs nightly from 22:00 through 05:59 Africa/Nairobi.
        | The command itself performs loan-level eligibility, duplicate,
        | balance, accounting and idempotency checks before posting.
        |--------------------------------------------------------------------------
        */

        // $schedule->command(
        //     'loans:process-default-interest --limit=20'
        // )
        //     ->everyMinute()
        //     ->when(function () {
        //         $hour = (int) now('Africa/Nairobi')->format('H');

        //         return $hour >= 22 || $hour < 6;
        //     })
        //     ->withoutOverlapping(10)
        //     ->onOneServer()
        //     ->timezone('Africa/Nairobi')
        //     ->runInBackground();

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
| Sync Missing User Rights Modules
|--------------------------------------------------------------------------
| Checks all registered Laravel routes for check_user_rights:* middleware
| and creates any missing module records in sacco_modules.
|
| This only creates missing modules. It does NOT grant rights to users.
|--------------------------------------------------------------------------
*/

$schedule->command('modules:sync-route-rights')
    ->dailyAt('06:00')
    ->withoutOverlapping(10)
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

        // $schedule->command(
        //     'special-savings:process-due-vesting'
        // )
        //     ->everyMinute()
        //     ->between('04:00', '07:59')
        //     ->withoutOverlapping(10)
        //     ->onOneServer()
        //     ->timezone('Africa/Nairobi')
        //     ->runInBackground();
        /*
        |--------------------------------------------------------------------------
        | SACCO CRB REPORTING SCHEDULE
        |--------------------------------------------------------------------------
        | These jobs PREPARE versioned DRAFT reports only.
        |
        | They do NOT finalise files, transmit data to a CRB, or mark submissions.
        | Daily DP/MF drafts are prepared after the prior day's processing.
        | Monthly CE/GI/CA drafts are prepared on the first day of each month
        | for the previous month-end.
        |--------------------------------------------------------------------------
        */

        $schedule->command('crb:prepare-daily')
            ->dailyAt('01:30')
            ->withoutOverlapping(30)
            ->onOneServer()
            ->timezone('Africa/Nairobi');

        $schedule->command('crb:prepare-monthly')
            ->monthlyOn(1, '04:30')
            ->withoutOverlapping(30)
            ->onOneServer()
            ->timezone('Africa/Nairobi');

    }

    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}