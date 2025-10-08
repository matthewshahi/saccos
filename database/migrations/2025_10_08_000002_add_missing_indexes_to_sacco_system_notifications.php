<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sacco_system_notifications', function (Blueprint $table) {
            // Add indexes only if they don't already exist
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $indexes = array_keys($sm->listTableIndexes('sacco_system_notifications'));

            if (!in_array('notif_status_index', $indexes)) {
                $table->index('notif_status', 'notif_status_index');
            }

            if (!in_array('notif_sent_at_index', $indexes)) {
                $table->index('notif_sent_at', 'notif_sent_at_index');
            }

            if (!in_array('notif_created_at_index', $indexes)) {
                $table->index('notif_created_at', 'notif_created_at_index');
            }

            if (!in_array('notif_recipient_email_index', $indexes)) {
                $table->index('notif_recipient_email', 'notif_recipient_email_index');
            }

            if (!in_array('notif_member_id_index', $indexes)) {
                $table->index('notif_member_id', 'notif_member_id_index');
            }
        });

        // Add a compound index for the scheduler filters if missing
        DB::statement("
            CREATE INDEX IF NOT EXISTS notif_status_sent_at_idx
            ON sacco_system_notifications (notif_status, notif_sent_at)
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sacco_system_notifications', function (Blueprint $table) {
            $table->dropIndex('notif_status_index');
            $table->dropIndex('notif_sent_at_index');
            $table->dropIndex('notif_created_at_index');
            $table->dropIndex('notif_recipient_email_index');
            $table->dropIndex('notif_member_id_index');
        });

        DB::statement("DROP INDEX IF EXISTS notif_status_sent_at_idx ON sacco_system_notifications");
    }
};