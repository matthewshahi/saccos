<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sacco_shares', function (Blueprint $table) {
            $table->string('share_description', 255)->nullable()->change();
            $table->string('share_doc_no', 255)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sacco_shares', function (Blueprint $table) {
            $table->string('share_description', 100)->nullable()->change();
            $table->string('share_doc_no', 100)->nullable()->change();
        });
    }
};