<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sacco_system_notifications', function (Blueprint $table) {
            // Create indexes (MySQL ignores duplicates gracefully)
            $table->index('notif_status', 'notif_status_index');
            $table->index('notif_sent_at', 'notif_sent_at_index');
            $table->index('notif_created_at', 'notif_created_at_index');
            $table->index('notif_recipient_email', 'notif_recipient_email_index');
            $table->index('notif_member_id', 'notif_member_id_index');
        });

        // Add compound index (safe wrapper)
        try {
            DB::statement("CREATE INDEX notif_status_sent_at_idx ON sacco_system_notifications (notif_status, notif_sent_at)");
        } catch (\Throwable $e) {
            // Ignore duplicate index error
        }
    }

    public function down(): void
    {
        Schema::table('sacco_system_notifications', function (Blueprint $table) {
            $table->dropIndex(['notif_status_index']);
            $table->dropIndex(['notif_sent_at_index']);
            $table->dropIndex(['notif_created_at_index']);
            $table->dropIndex(['notif_recipient_email_index']);
            $table->dropIndex(['notif_member_id_index']);
        });

        try {
            DB::statement("DROP INDEX notif_status_sent_at_idx ON sacco_system_notifications");
        } catch (\Throwable $e) {
            // Ignore if not exists
        }
    }
};