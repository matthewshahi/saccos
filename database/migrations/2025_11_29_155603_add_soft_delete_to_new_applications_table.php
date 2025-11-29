<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSoftDeleteToNewApplicationsTable extends Migration
{
    public function up()
    {
        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            // Only add if not already existing (safe for re-run)
            if (!Schema::hasColumn('sacco_members_new_applications', 'deleted')) {
                $table->enum('deleted', ['Y', 'N'])
                      ->default('N')
                      ->after('exported_on');
            }

            if (!Schema::hasColumn('sacco_members_new_applications', 'deleted_at')) {
                $table->timestamp('deleted_at')
                      ->nullable()
                      ->after('deleted');
            }
        });
    }

    public function down()
    {
        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            if (Schema::hasColumn('sacco_members_new_applications', 'deleted')) {
                $table->dropColumn('deleted');
            }

            if (Schema::hasColumn('sacco_members_new_applications', 'deleted_at')) {
                $table->dropColumn('deleted_at');
            }
        });
    }
}
