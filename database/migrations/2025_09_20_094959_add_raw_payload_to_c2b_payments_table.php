<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('c2b_payments', function (Blueprint $table) {
            // Add raw_payload column after last_name for clarity
            $table->json('raw_payload')->nullable()->after('last_name');
        });
    }

    public function down(): void
    {
        Schema::table('c2b_payments', function (Blueprint $table) {
            $table->dropColumn('raw_payload');
        });
    }
};