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
        Schema::create('sacco_system_notifications', function (Blueprint $table) {
            $table->bigIncrements('notif_id');

            // 📬 Recipient details
            $table->string('notif_recipient_name', 150)->nullable();
            $table->string('notif_recipient_email', 150)->nullable();
            $table->string('notif_recipient_phone', 50)->nullable();

            // 💬 Message content
            $table->string('notif_subject', 255)->nullable();
            $table->text('notif_message')->nullable();

            // 🔖 Status tracking
            $table->enum('notif_status', ['unread', 'read', 'sent', 'failed'])->default('unread');
            $table->timestamp('notif_sent_at')->nullable();
            $table->timestamp('notif_read_at')->nullable();

            // 🧩 Context / linkage
            $table->unsignedBigInteger('notif_member_id')->nullable();
            $table->string('notif_related_doc', 100)->nullable(); // e.g., Loan Doc No or reference
            $table->string('notif_type', 50)->default('system');  // e.g., system, email, sms

            // 🧾 Audit info
            $table->unsignedBigInteger('notif_created_by')->nullable();
            $table->string('notif_ip', 45)->nullable();
            $table->timestamp('notif_created_at')->useCurrent();

            // 🔍 Indexes for fast lookups
            $table->index('notif_member_id');
            $table->index('notif_status');
            $table->index('notif_created_at');
            $table->index('notif_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sacco_system_notifications');
    }
};