<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;

class AddIndexesToSaccoMembersTable extends Migration
{
    protected $indexes = [
        ['table' => 'sacco_members', 'column' => 'member_deleted'],
        ['table' => 'sacco_members', 'column' => 'member_active'],
        ['table' => 'sacco_members', 'column' => 'member_date_joined'],
        ['table' => 'sacco_members', 'column' => 'member_name'],
        ['table' => 'sacco_members', 'column' => 'member_sacco_id'],
        ['table' => 'sacco_members', 'column' => 'member_national_id'],
        ['table' => 'sacco_period', 'column' => 'period_active'],
        ['table' => 'sacco_defaults', 'column' => 'default_name'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $index) {
            $table = $index['table'];
            $column = $index['column'];
            $indexName = "{$table}_{$column}_index";

            $exists = DB::table('information_schema.STATISTICS')
                ->where('TABLE_SCHEMA', DB::getDatabaseName())
                ->where('TABLE_NAME', $table)
                ->where('INDEX_NAME', $indexName)
                ->exists();

            if (!$exists) {
                Schema::table($table, function ($tableBlueprint) use ($column) {
                    $tableBlueprint->index($column);
                });
            }
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $index) {
            $table = $index['table'];
            $column = $index['column'];
            $indexName = "{$table}_{$column}_index";

            Schema::table($table, function ($tableBlueprint) use ($column, $indexName) {
                if (Schema::hasColumn($tableBlueprint->getTable(), $column)) {
                    $tableBlueprint->dropIndex($indexName);
                }
            });
        }
    }
}