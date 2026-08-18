<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Explicitly confirmed IP-address columns that must have
     * a minimum capacity of VARCHAR(100).
     *
     * No automatic column-name matching is performed.
     */
    private array $ipColumns = [
        'sacco_defaults' => [
            'default_ip',
        ],

        'sacco_modules' => [
            'module_ip',
        ],

        'sacco_userrights' => [
            'rights_ip',
        ],

        'mpesa_app_stk_requests' => [
            'request_ip',
        ],

        'sacco_bank_ipns' => [
            'cf_connecting_ip',
            'source_ip',
            'x_real_ip',
        ],

        'sacco_bulk_sms_callbacks' => [
            'callback_ip',
        ],

        'sacco_bulk_sms_messages' => [
            'created_ip',
        ],

        'sacco_fosa_types' => [
            'created_ip',
            'updated_ip',
        ],

        'sacco_matatu_maintenance' => [
            'maintenance_ip',
        ],

        'sacco_matatu_penalties' => [
            'penalty_ip',
        ],

        'sacco_mobile_app_loan_applications' => [
            'mobile_app_submitted_ip',
        ],

        'sacco_registration_fees' => [
            'regfee_created_ip',
            'regfee_ip',
            'regfee_updated_ip',
        ],

        'sacco_system_notifications' => [
            'notif_ip',
        ],

        'sacco_fosa_transaction_type_changes' => [
            'changed_ip',
        ],

        'sacco_member_password_resets' => [
            'reset_ip',
        ],

        'sacco_menu' => [
            'menu_ip',
        ],
    ];

    /**
     * Run the migrations.
     *
     * Safety rules:
     * - Only explicitly listed columns can be changed.
     * - Only CHAR/VARCHAR columns are accepted.
     * - Only fields below 100 characters are widened.
     * - Fields already 100+ are left untouched.
     * - Existing charset, collation, NULL status, default and comments
     *   are preserved.
     * - Existing row data is never updated or deleted.
     */
    public function up(): void
    {
        $database = DB::connection()->getDatabaseName();
        $columnsToChange = [];

        /*
         * ---------------------------------------------------------
         * FIRST PASS
         * Inspect and validate every explicitly approved field.
         * Do not alter anything yet.
         * ---------------------------------------------------------
         */
        foreach ($this->ipColumns as $table => $columns) {
            foreach ($columns as $columnName) {
                $column = DB::selectOne(
                    "
                    SELECT
                        TABLE_NAME,
                        COLUMN_NAME,
                        DATA_TYPE,
                        CHARACTER_MAXIMUM_LENGTH,
                        IS_NULLABLE,
                        COLUMN_DEFAULT,
                        CHARACTER_SET_NAME,
                        COLLATION_NAME,
                        COLUMN_COMMENT
                    FROM information_schema.COLUMNS
                    WHERE TABLE_SCHEMA = ?
                      AND TABLE_NAME = ?
                      AND COLUMN_NAME = ?
                    LIMIT 1
                    ",
                    [
                        $database,
                        $table,
                        $columnName,
                    ]
                );

                /*
                 * Some SACCO installations may not yet contain every
                 * newer module/table. Skip missing fields safely.
                 */
                if (!$column) {
                    continue;
                }

                $type = strtolower((string) $column->DATA_TYPE);
                $length = (int) $column->CHARACTER_MAXIMUM_LENGTH;

                if (!in_array($type, ['varchar', 'char'], true)) {
                    throw new \RuntimeException(
                        "Safety stop: {$table}.{$columnName} is {$type}; expected CHAR/VARCHAR."
                    );
                }

                /*
                 * Already large enough — leave completely untouched.
                 */
                if ($length >= 100) {
                    continue;
                }

                if ($length <= 0) {
                    throw new \RuntimeException(
                        "Safety stop: invalid length detected for {$table}.{$columnName}."
                    );
                }

                if (
                    empty($column->CHARACTER_SET_NAME) ||
                    empty($column->COLLATION_NAME)
                ) {
                    throw new \RuntimeException(
                        "Safety stop: charset/collation unavailable for {$table}.{$columnName}."
                    );
                }

                $columnsToChange[] = $column;
            }
        }

        /*
         * ---------------------------------------------------------
         * SECOND PASS
         * Widen only the validated explicit IP fields.
         * ---------------------------------------------------------
         */
        foreach ($columnsToChange as $column) {
            $table = (string) $column->TABLE_NAME;
            $name = (string) $column->COLUMN_NAME;
            $type = strtolower((string) $column->DATA_TYPE);

            /*
             * Preserve CHAR vs VARCHAR.
             */
            $newType = $type === 'char'
                ? 'CHAR(100)'
                : 'VARCHAR(100)';

            $charset = (string) $column->CHARACTER_SET_NAME;
            $collation = (string) $column->COLLATION_NAME;

            /*
             * Preserve NULL / NOT NULL.
             */
            $nullable =
                strtoupper((string) $column->IS_NULLABLE) === 'YES';

            $nullSql = $nullable
                ? 'NULL'
                : 'NOT NULL';

            /*
             * Preserve the existing default.
             */
            if ($column->COLUMN_DEFAULT === null) {
                $defaultSql = $nullable
                    ? 'DEFAULT NULL'
                    : '';
            } else {
                $defaultSql = 'DEFAULT ' .
                    DB::connection()
                        ->getPdo()
                        ->quote((string) $column->COLUMN_DEFAULT);
            }

            /*
             * Preserve existing column comment.
             */
            $comment = (string) ($column->COLUMN_COMMENT ?? '');

            $commentSql = $comment !== ''
                ? 'COMMENT ' .
                    DB::connection()
                        ->getPdo()
                        ->quote($comment)
                : '';

            /*
             * Quote identifiers.
             */
            $quotedTable =
                '`' . str_replace('`', '``', $table) . '`';

            $quotedColumn =
                '`' . str_replace('`', '``', $name) . '`';

            DB::statement(
                "
                ALTER TABLE {$quotedTable}
                MODIFY COLUMN {$quotedColumn}
                {$newType}
                CHARACTER SET {$charset}
                COLLATE {$collation}
                {$nullSql}
                {$defaultSql}
                {$commentSql}
                "
            );
        }
    }

    /**
     * Do not automatically shrink production audit fields.
     *
     * Once longer IP/audit values have been stored, shrinking them
     * could truncate data.
     */
    public function down(): void
    {
        throw new \RuntimeException(
            'Rollback blocked for data safety: IP fields must not be automatically shrunk.'
        );
    }
};