<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Use raw SQL because modifying ENUMs via Schema builder isn't supported
        DB::statement("
            ALTER TABLE sacco_system_notifications 
            MODIFY COLUMN notif_status 
            ENUM('unread','queued','sent','failed','read') 
            NOT NULL DEFAULT 'unread'
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Rollback to original enum definition
        DB::statement("
            ALTER TABLE sacco_system_notifications 
            MODIFY COLUMN notif_status 
            ENUM('unread','read','sent','failed') 
            NOT NULL DEFAULT 'unread'
        ");
    }
};