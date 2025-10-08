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
        Schema::table('sacco_system_notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('sacco_system_notifications', 'notif_meta')) {
                $table->json('notif_meta')->nullable()->after('notif_ip');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sacco_system_notifications', function (Blueprint $table) {
            if (Schema::hasColumn('sacco_system_notifications', 'notif_meta')) {
                $table->dropColumn('notif_meta');
            }
        });
    }
};