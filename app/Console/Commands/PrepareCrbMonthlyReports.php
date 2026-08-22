<?php

namespace App\Console\Commands;

use App\Services\Crb\CrbReportService;
use Illuminate\Console\Command;
use Throwable;

class PrepareCrbMonthlyReports extends Command
{
    protected $signature = 'crb:prepare-monthly
                            {--date= : Reporting date YYYY-MM-DD. Defaults to the previous month-end in Africa/Nairobi.}';

    protected $description = 'Prepare draft monthly CRB CE, GI and CA reports without finalising or submitting them.';

    public function handle(CrbReportService $crb): int
    {
        $date = $this->option('date');

        try {
            foreach (['CE', 'GI', 'CA'] as $code) {
                $resolvedDate = $crb->normaliseReportDate($code, $date);
                $reportId = $crb->generateIfMissing($code, $resolvedDate, null, 'SCHEDULER');

                if ($reportId === null) {
                    $this->line("{$code} {$resolvedDate->format('Y-m-d')}: skipped (already exists or no source records).");
                    continue;
                }

                $report = $crb->getReport($reportId);
                $this->info(
                    "{$code} {$resolvedDate->format('Y-m-d')}: draft #{$reportId} generated "
                    . "({$report->total_records} records, {$report->error_records} error records, {$report->warning_records} warning records)."
                );
            }

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('CRB monthly preparation failed: ' . $e->getMessage());

            report($e);

            return self::FAILURE;
        }
    }
}
