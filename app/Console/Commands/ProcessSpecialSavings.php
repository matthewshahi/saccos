<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SpecialSavingService;

class ProcessSpecialSavings extends Command
{
    protected $signature = 'special-savings:process {--date=} {--force}';

    protected $description = 'Process automatic special savings interest, vesting, and due product schedules.';

    public function handle(SpecialSavingService $service): int
    {
        $processDate = $this->option('date') ?: now()->toDateString();

        $this->info("Processing Special Savings for {$processDate}");

        $result = $service->processScheduledSavings($processDate, [
            'force' => (bool) $this->option('force'),
            'source' => 'SCHEDULER',
            'user_id' => null,
            'ip' => 'SYSTEM',
        ]);

        $this->info('Special Savings processing completed.');

        if (is_array($result)) {
            foreach ($result as $line) {
                $this->line(is_scalar($line) ? (string) $line : json_encode($line));
            }
        }

        return Command::SUCCESS;
    }
}