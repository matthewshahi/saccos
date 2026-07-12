<?php

namespace App\Console\Commands;

use App\Services\BulkSms\BulkSmsConfigService;
use App\Services\BulkSms\BulkSmsDispatchService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class DispatchBulkSmsOutbox extends Command
{
    /**
     * Command name and options.
     *
     * The limit is capped internally at 60 even when a higher value
     * is supplied manually.
     */
    protected $signature = 'bulk-sms:dispatch-outbox
                            {--limit=60 : Maximum queued SMS records to process}';

    /**
     * Command description.
     */
    protected $description = 'Send a maximum of 60 queued Bulk SMS messages at one message per second';

    /**
     * Execute the command.
     */
    public function handle(
        BulkSmsDispatchService $dispatcher,
        BulkSmsConfigService $config
    ): int {
        /*
         * Hard maximum of 60 SMS messages per invocation.
         */
        $limit = max(
            1,
            min((int) $this->option('limit'), 60)
        );

        /*
         * Leave queued records untouched when Bulk SMS is disabled.
         */
        if (!$config->isEnabled()) {
            $this->info(
                'Bulk SMS is disabled. No queued messages were processed.'
            );

            return self::SUCCESS;
        }

        /*
         * Do not consume queued records while demo mode is enabled.
         */
        if ($config->isDemoMode()) {
            $this->info(
                'Bulk SMS demo mode is enabled. No queued messages were processed.'
            );

            return self::SUCCESS;
        }

        $readiness = $config->readiness();

        if (!($readiness['ready_to_send'] ?? false)) {
            $this->error(
                implode(
                    ' ',
                    $readiness['issues']
                        ?? ['Bulk SMS is not ready to send.']
                )
            );

            return self::FAILURE;
        }

        /*
         * Prevent this command from running concurrently through:
         *
         * - The scheduler
         * - A manual command
         * - Another server process
         */
        $lock = Cache::lock(
            'bulk_sms_dispatch_outbox',
            900
        );

        if (!$lock->get()) {
            $this->info(
                'Another Bulk SMS outbox dispatcher is already running.'
            );

            return self::SUCCESS;
        }

        try {
            /*
             * Select the oldest queued messages first.
             *
             * Only the IDs are loaded because dispatchOne() retrieves and
             * validates the complete SMS record immediately before sending.
             */
            $smsIds = DB::table('sacco_bulk_sms_messages')
                ->where('sms_status', 'queued')
                ->orderBy('sms_id')
                ->limit($limit)
                ->pluck('sms_id');

            if ($smsIds->isEmpty()) {
                $this->info(
                    'No queued Bulk SMS messages were found.'
                );

                return self::SUCCESS;
            }

            $selectedCount = $smsIds->count();
            $sentCount = 0;
            $failedCount = 0;
            $skippedCount = 0;

            /*
             * The next provider request must not begin before this time.
             */
            $nextDispatchTime = microtime(true);

            foreach ($smsIds as $smsId) {
                /*
                 * Wait until at least one second has passed since the
                 * previous provider request started.
                 */
                $currentTime = microtime(true);

                if ($currentTime < $nextDispatchTime) {
                    $microsecondsToWait = (int) ceil(
                        ($nextDispatchTime - $currentTime)
                        * 1_000_000
                    );

                    usleep($microsecondsToWait);
                }

                $dispatchStartedAt = microtime(true);

                try {
                    $result = $dispatcher->dispatchOne(
                        (int) $smsId
                    );

                    if (($result['sent'] ?? false) === true) {
                        $sentCount++;

                        $this->line(
                            sprintf(
                                'SMS #%d sent successfully.',
                                $smsId
                            )
                        );
                    } elseif (
                        ($result['success'] ?? false) === false
                    ) {
                        $failedCount++;

                        $this->error(
                            sprintf(
                                'SMS #%d failed: %s',
                                $smsId,
                                $result['error_message']
                                    ?? 'Unknown error'
                            )
                        );
                    } else {
                        $skippedCount++;

                        $this->warn(
                            sprintf(
                                'SMS #%d was not sent: %s',
                                $smsId,
                                $result['message']
                                    ?? $result['error_message']
                                    ?? 'Skipped'
                            )
                        );
                    }
                } catch (Throwable $exception) {
                    /*
                     * Leave the record queued when an unexpected exception
                     * occurs so that it can be retried on the next run.
                     */
                    $failedCount++;

                    report($exception);

                    $this->error(
                        sprintf(
                            'SMS #%d raised an exception: %s',
                            $smsId,
                            $exception->getMessage()
                        )
                    );
                }

                /*
                 * The next SMS may start no sooner than one second after
                 * this SMS request started.
                 *
                 * When the provider response itself takes longer than one
                 * second, the next SMS starts immediately after it returns,
                 * which still remains below one request per second.
                 */
                $nextDispatchTime = $dispatchStartedAt + 1.0;
            }

            $this->newLine();

            $this->table(
                [
                    'Selected',
                    'Sent',
                    'Failed',
                    'Skipped',
                    'Rate',
                ],
                [[
                    $selectedCount,
                    $sentCount,
                    $failedCount,
                    $skippedCount,
                    'Maximum 1 SMS/second',
                ]]
            );

            return self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
