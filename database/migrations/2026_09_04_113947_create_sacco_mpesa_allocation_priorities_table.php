<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $tableName = 'sacco_mpesa_allocation_priorities';

    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Create Table Only If Missing
        |--------------------------------------------------------------------------
        |
        | This covers existing SACCO databases where the previous migration had
        | not yet run.
        |
        | For SACCO databases where the table already exists, we do not drop it.
        |
        */
        if (! Schema::hasTable($this->tableName)) {
            Schema::create($this->tableName, function (Blueprint $table) {
                $table->bigIncrements('priority_id');

                $table->string('priority_key', 190);

                $table->string('priority_type', 60);

                $table->string('priority_source_table', 120)->nullable();

                $table->unsignedBigInteger('priority_source_id')->nullable();

                $table->string('priority_label', 190)->nullable();

                $table->string('priority_code', 100)->nullable();

                $table->unsignedInteger('priority_order')->default(0);

                $table->char('priority_active', 1)->default('Y');

                $table->longText('priority_options')->nullable();

                $table->text('priority_notes')->nullable();

                $table->timestamp('priority_discovered_at')->nullable();

                $table->timestamp('priority_last_seen_at')->nullable();

                $table->unsignedBigInteger('priority_created_by')->nullable();

                $table->string('priority_created_ip', 100)->nullable();

                $table->unsignedBigInteger('priority_updated_by')->nullable();

                $table->string('priority_updated_ip', 100)->nullable();

                $table->timestamps();
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Add Missing Columns
        |--------------------------------------------------------------------------
        |
        | This handles SACCO databases where the previous migration partially ran.
        |
        */
        if (! Schema::hasColumn($this->tableName, 'priority_key')) {
            Schema::table($this->tableName, function (Blueprint $table) {
                $table->string('priority_key', 190)->nullable()->after('priority_id');
            });
        }

        if (! Schema::hasColumn($this->tableName, 'priority_type')) {
            Schema::table($this->tableName, function (Blueprint $table) {
                $table->string('priority_type', 60)->nullable()->after('priority_key');
            });
        }

        if (! Schema::hasColumn($this->tableName, 'priority_source_table')) {
            Schema::table($this->tableName, function (Blueprint $table) {
                $table->string('priority_source_table', 120)->nullable()->after('priority_type');
            });
        }

        if (! Schema::hasColumn($this->tableName, 'priority_source_id')) {
            Schema::table($this->tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('priority_source_id')->nullable()->after('priority_source_table');
            });
        }

        if (! Schema::hasColumn($this->tableName, 'priority_label')) {
            Schema::table($this->tableName, function (Blueprint $table) {
                $table->string('priority_label', 190)->nullable()->after('priority_source_id');
            });
        }

        if (! Schema::hasColumn($this->tableName, 'priority_code')) {
            Schema::table($this->tableName, function (Blueprint $table) {
                $table->string('priority_code', 100)->nullable()->after('priority_label');
            });
        }

        if (! Schema::hasColumn($this->tableName, 'priority_order')) {
            Schema::table($this->tableName, function (Blueprint $table) {
                $table->unsignedInteger('priority_order')->default(0)->after('priority_code');
            });
        }

        if (! Schema::hasColumn($this->tableName, 'priority_active')) {
            Schema::table($this->tableName, function (Blueprint $table) {
                $table->char('priority_active', 1)->default('Y')->after('priority_order');
            });
        }

        if (! Schema::hasColumn($this->tableName, 'priority_options')) {
            Schema::table($this->tableName, function (Blueprint $table) {
                $table->longText('priority_options')->nullable()->after('priority_active');
            });
        }

        if (! Schema::hasColumn($this->tableName, 'priority_notes')) {
            Schema::table($this->tableName, function (Blueprint $table) {
                $table->text('priority_notes')->nullable()->after('priority_options');
            });
        }

        if (! Schema::hasColumn($this->tableName, 'priority_discovered_at')) {
            Schema::table($this->tableName, function (Blueprint $table) {
                $table->timestamp('priority_discovered_at')->nullable()->after('priority_notes');
            });
        }

        if (! Schema::hasColumn($this->tableName, 'priority_last_seen_at')) {
            Schema::table($this->tableName, function (Blueprint $table) {
                $table->timestamp('priority_last_seen_at')->nullable()->after('priority_discovered_at');
            });
        }

        if (! Schema::hasColumn($this->tableName, 'priority_created_by')) {
            Schema::table($this->tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('priority_created_by')->nullable()->after('priority_last_seen_at');
            });
        }

        if (! Schema::hasColumn($this->tableName, 'priority_created_ip')) {
            Schema::table($this->tableName, function (Blueprint $table) {
                $table->string('priority_created_ip', 100)->nullable()->after('priority_created_by');
            });
        }

        if (! Schema::hasColumn($this->tableName, 'priority_updated_by')) {
            Schema::table($this->tableName, function (Blueprint $table) {
                $table->unsignedBigInteger('priority_updated_by')->nullable()->after('priority_created_ip');
            });
        }

        if (! Schema::hasColumn($this->tableName, 'priority_updated_ip')) {
            Schema::table($this->tableName, function (Blueprint $table) {
                $table->string('priority_updated_ip', 100)->nullable()->after('priority_updated_by');
            });
        }

        if (! Schema::hasColumn($this->tableName, 'created_at')) {
            Schema::table($this->tableName, function (Blueprint $table) {
                $table->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasColumn($this->tableName, 'updated_at')) {
            Schema::table($this->tableName, function (Blueprint $table) {
                $table->timestamp('updated_at')->nullable();
            });
        }

        /*
        |--------------------------------------------------------------------------
        | Clean Bad Indexes
        |--------------------------------------------------------------------------
        |
        | These are old names from earlier attempts.
        |
        | We remove:
        | - unwanted unique keys
        | - Laravel auto-generated long indexes
        | - old non-standard index names
        |
        | Primary key is not touched.
        |
        */
        $badIndexes = [
            'sacco_mpesa_allocation_priorities_priority_key_unique',
            'sacco_mpesa_allocation_priorities_key_unique',

            'sacco_mpesa_allocation_priorities_priority_order_index',
            'sacco_mpesa_allocation_priorities_priority_key_index',
            'sacco_mpesa_allocation_priorities_priority_type_index',
            'sacco_mpesa_allocation_priorities_priority_source_id_index',
            'sacco_mpesa_allocation_priorities_priority_source_table_priority_source_id_index',

            'sacco_mpesa_allocation_priorities_order_index',
            'sacco_mpesa_allocation_priorities_type_source_index',
        ];

        foreach ($badIndexes as $indexName) {
            $this->dropIndexIfExists($indexName);
        }

        /*
        |--------------------------------------------------------------------------
        | Add Correct Short Ordinary Indexes
        |--------------------------------------------------------------------------
        |
        | These indexes are intentionally ordinary indexes.
        |
        | No unique constraints.
        | No foreign-key constraints.
        |
        */
        if (! $this->indexExists('smp_ap_order_idx')) {
            Schema::table($this->tableName, function (Blueprint $table) {
                $table->index('priority_order', 'smp_ap_order_idx');
            });
        }

        if (! $this->indexExists('smp_ap_key_idx')) {
            Schema::table($this->tableName, function (Blueprint $table) {
                $table->index('priority_key', 'smp_ap_key_idx');
            });
        }

        if (! $this->indexExists('smp_ap_type_idx')) {
            Schema::table($this->tableName, function (Blueprint $table) {
                $table->index('priority_type', 'smp_ap_type_idx');
            });
        }

        if (! $this->indexExists('smp_ap_src_id_idx')) {
            Schema::table($this->tableName, function (Blueprint $table) {
                $table->index('priority_source_id', 'smp_ap_src_id_idx');
            });
        }

        if (! $this->indexExists('smp_ap_src_tbl_id_idx')) {
            Schema::table($this->tableName, function (Blueprint $table) {
                $table->index(
                    ['priority_source_table', 'priority_source_id'],
                    'smp_ap_src_tbl_id_idx'
                );
            });
        }

        if (! $this->indexExists('smp_ap_active_idx')) {
            Schema::table($this->tableName, function (Blueprint $table) {
                $table->index('priority_active', 'smp_ap_active_idx');
            });
        }
    }

    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Rollback Only What This Repair Migration Added
        |--------------------------------------------------------------------------
        |
        | We do not drop the table here because the table may already exist with
        | useful configuration data in some SACCO databases.
        |
        */
        if (! Schema::hasTable($this->tableName)) {
            return;
        }

        $indexes = [
            'smp_ap_order_idx',
            'smp_ap_key_idx',
            'smp_ap_type_idx',
            'smp_ap_src_id_idx',
            'smp_ap_src_tbl_id_idx',
            'smp_ap_active_idx',
        ];

        foreach ($indexes as $indexName) {
            $this->dropIndexIfExists($indexName);
        }
    }

    private function indexExists(string $indexName): bool
    {
        if (! Schema::hasTable($this->tableName)) {
            return false;
        }

        $databaseName = DB::getDatabaseName();

        $result = DB::select(
            "
            SELECT COUNT(1) AS total
            FROM information_schema.statistics
            WHERE table_schema = ?
              AND table_name = ?
              AND index_name = ?
            ",
            [
                $databaseName,
                $this->tableName,
                $indexName,
            ]
        );

        return isset($result[0]) && (int) $result[0]->total > 0;
    }

    private function dropIndexIfExists(string $indexName): void
    {
        if (! $this->indexExists($indexName)) {
            return;
        }

        Schema::table($this->tableName, function (Blueprint $table) use ($indexName) {
            $table->dropIndex($indexName);
        });
    }
};