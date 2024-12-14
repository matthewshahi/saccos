<?php

namespace App\Console;
use Illuminate\Support\Facades\Log;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
  
     protected function schedule(Schedule $schedule): void
    {
        $schedule->job(new \App\Jobs\ProcessTransactionsJob())->everyTwoMinutes();
        $schedule->job(new \App\Jobs\SendWelcomeEmailJob)->everyTwoMinutes();
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
