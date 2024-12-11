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
         // Log each time the scheduler runs
         $schedule->call(function () {
             \Log::info('Cron job executed at: ' . now());
         })->everyTwoMinutes();
     
         // Schedule your ProcessTransactionsJob
         $schedule->job(new \App\Jobs\ProcessTransactionsJob)->everyTwoMinutes();
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
