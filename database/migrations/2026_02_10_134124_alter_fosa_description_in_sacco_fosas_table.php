<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sacco_fosas', function (Blueprint $table) {
            // Fix: prevent truncation on long import descriptions
            $table->text('fosa_description')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('sacco_fosas', function (Blueprint $table) {
            // Adjust this length to whatever you had before (commonly 255)
            $table->string('fosa_description', 255)->nullable()->change();
        });
    }
};
