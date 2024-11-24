<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateKinSharePercentToJsonInSaccoMembersNewApplications extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            // Drop the existing `kin_share_percent` column if it exists
            if (Schema::hasColumn('sacco_members_new_applications', 'kin_share_percent')) {
                $table->dropColumn('kin_share_percent');
            }
        });

        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            // Add the `kin_share_percent` column back as JSON
            $table->json('kin_share_percent')->nullable()->after('next_of_kin_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            // Drop the JSON column
            $table->dropColumn('kin_share_percent');
        });

        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            // Add the column back as a string (original state)
            $table->string('kin_share_percent', 255)->nullable()->after('next_of_kin_id');
        });
    }
}