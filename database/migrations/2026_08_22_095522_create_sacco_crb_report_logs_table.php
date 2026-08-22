<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sacco_crb_report_logs', function (Blueprint $table) {
            $table->bigIncrements('crb_log_id');

            $table->unsignedBigInteger('crb_log_report_id');

            $table->string('crb_log_action', 50);
            $table->string('crb_log_status', 20)->default('INFO');

            $table->text('crb_log_message')->nullable();
            $table->unsignedInteger('crb_log_record_count')->default(0);
            $table->json('crb_log_context')->nullable();

            $table->unsignedBigInteger('crb_log_user_id')->nullable();
            $table->string('crb_log_ip', 100)->nullable();

            $table->timestamp('crb_log_created_at')->useCurrent();

            $table->foreign('crb_log_report_id', 'fk_crb_log_report')
                ->references('crb_report_id')
                ->on('sacco_crb_reports')
                ->onDelete('cascade');

            $table->index(
                [
                    'crb_log_report_id',
                    'crb_log_created_at',
                ],
                'idx_crb_log_report_date'
            );

            $table->index('crb_log_action', 'idx_crb_log_action');
            $table->index('crb_log_status', 'idx_crb_log_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sacco_crb_report_logs');
    }
};
