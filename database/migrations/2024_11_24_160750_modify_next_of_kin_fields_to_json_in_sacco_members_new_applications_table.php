<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyNextOfKinFieldsToJsonInSaccoMembersNewApplicationsTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            // Modify the existing fields to JSON type
            $table->json('next_of_kin_name')->nullable()->change(); // Change to JSON
            $table->json('next_of_kin_relationship')->nullable()->change(); // Change to JSON
            $table->json('next_of_kin_phone')->nullable()->change(); // Change to JSON
            $table->json('next_of_kin_id')->nullable()->change(); // Change to JSON

            // Add kin_share_percent field
            $table->unsignedTinyInteger('kin_share_percent')->nullable()->after('next_of_kin_id'); // Add new column for share percent
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            // Revert JSON columns back to their original string type
            $table->string('next_of_kin_name', 100)->nullable()->change();
            $table->string('next_of_kin_relationship', 50)->nullable()->change();
            $table->string('next_of_kin_phone', 15)->nullable()->change();
            $table->string('next_of_kin_id', 20)->nullable()->change();

            // Drop kin_share_percent field
            $table->dropColumn('kin_share_percent');
        });
    }
}