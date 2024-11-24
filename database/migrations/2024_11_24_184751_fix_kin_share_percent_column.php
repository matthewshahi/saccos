<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

 
class FixKinSharePercentInSaccoMembersTables extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            // Drop the existing problematic column
            $table->dropColumn('kin_share_percent');
        });

        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            // Recreate the column as JSON with a default empty array
            $table->json('kin_share_percent')->default(json_encode([]))->after('next_of_kin_id');
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
            // Recreate the column as a string (original state)
            $table->string('kin_share_percent', 255)->nullable()->after('next_of_kin_id');
        });
    }
}