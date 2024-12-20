<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ProcessLoanEmails extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:loan-emails';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process loan approval emails';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Add your logic for processing loan emails
        $this->info('Processing loan emails...');
    }
}