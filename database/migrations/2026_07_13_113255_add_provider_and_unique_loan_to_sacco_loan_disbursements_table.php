<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /*
         * One loan must have only one disbursement record.
         *
         * Do not automatically delete financial records if duplicates already
         * exist. Stop the migration and require the duplicates to be reviewed.
         */
        $duplicateLoan = DB::table('sacco_loan_disbursements')
            ->select([
                'loan_id',
                DB::raw('COUNT(*) AS total_records'),
            ])
            ->groupBy('loan_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('loan_id')
            ->first();

        if ($duplicateLoan !== null) {
            throw new \RuntimeException(
                sprintf(
                    'Cannot add the unique loan disbursement constraint. '
                    . 'Loan ID %s already has %s disbursement records. '
                    . 'Review duplicate records before running this migration.',
                    $duplicateLoan->loan_id,
                    $duplicateLoan->total_records
                )
            );
        }

        $provider = strtoupper(
            trim(
                (string) env(
                    'LOAN_DISBURSEMENT_PROVIDER',
                    'NCBA'
                )
            )
        );

        /*
         * Prevent an invalid provider name from becoming the database default.
         */
        if (
            $provider === ''
            || ! preg_match('/^[A-Z0-9_-]{2,30}$/', $provider)
        ) {
            throw new \RuntimeException(
                'LOAN_DISBURSEMENT_PROVIDER must contain between 2 and '
                . '30 uppercase letters, numbers, underscores or hyphens.'
            );
        }

        Schema::table(
            'sacco_loan_disbursements',
            function (Blueprint $table) use ($provider) {
                /*
                 * This records the provider selected when the loan was queued.
                 *
                 * Each SACCO still uses only one provider. The value is stored
                 * on the transaction for audit purposes and to prevent the
                 * wrong provider command from picking the record.
                 */
                $table->string(
                    'disbursement_provider',
                    30
                )
                    ->default($provider)
                    ->after('disbursement_channel');

                /*
                 * One loan can have only one disbursement queue record.
                 *
                 * Retries and bank-status updates must use the same record.
                 */
                $table->unique(
                    'loan_id',
                    'sld_loan_id_unique'
                );

                /*
                 * Provider processors usually search by:
                 *
                 * provider + status + next retry time
                 */
                $table->index(
                    [
                        'disbursement_provider',
                        'status',
                        'next_retry_at',
                    ],
                    'sld_provider_status_retry_idx'
                );
            }
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table(
            'sacco_loan_disbursements',
            function (Blueprint $table) {
                $table->dropIndex(
                    'sld_provider_status_retry_idx'
                );

                $table->dropUnique(
                    'sld_loan_id_unique'
                );

                $table->dropColumn(
                    'disbursement_provider'
                );
            }
        );
    }
};