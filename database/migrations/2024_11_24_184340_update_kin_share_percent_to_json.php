<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateKinSharePercentToJson extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            $table->json('kin_share_percent')->change(); // Update column type to JSON
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            $table->string('kin_share_percent')->change(); // Revert back to string
        });
    }
}