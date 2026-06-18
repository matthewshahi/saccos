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
        Schema::create('sacco_route_audit_logs', function (Blueprint $table) {
            $table->bigIncrements('route_audit_log_id');

            $table->string('route_audit_log_request_id', 100)->nullable();

            /**
             * Examples:
             * ROUTE_REQUEST
             * PAGE_VIEW
             * FORM_SUBMIT
             * LOGIN_SUCCESS
             * LOGIN_FAILED
             * LOGIN_LOCKOUT
             * LOGOUT
             * PASSWORD_RESET
             * IMPORT_REQUEST
             * EXPORT_REQUEST
             * APPROVAL_REQUEST
             * REVERSAL_REQUEST
             */
            $table->string('route_audit_log_event_type', 80);

            /**
             * Examples:
             * SUCCESS
             * FAILED
             * ERROR
             * REDIRECT
             * UNAUTHENTICATED
             * FORBIDDEN
             * NOT_FOUND
             * LOCKED
             */
            $table->string('route_audit_log_outcome', 30)->default('SUCCESS');

            $table->unsignedBigInteger('route_audit_log_user_id')->nullable();
            $table->string('route_audit_log_user_name', 150)->nullable();
            $table->string('route_audit_log_user_email', 150)->nullable();

            /**
             * Useful for failed login because there may be no authenticated user.
             */
            $table->string('route_audit_log_attempted_login', 150)->nullable();

            $table->string('route_audit_log_route_name', 150)->nullable();
            $table->string('route_audit_log_controller_action', 255)->nullable();

            $table->string('route_audit_log_method', 20)->nullable();
            $table->string('route_audit_log_path', 255)->nullable();
            $table->text('route_audit_log_url')->nullable();
            $table->text('route_audit_log_referer')->nullable();

            $table->integer('route_audit_log_status_code')->nullable();

            $table->string('route_audit_log_ip_address', 100)->nullable();
            $table->text('route_audit_log_user_agent')->nullable();

            /**
             * Sanitised request data only.
             * Passwords, tokens, OTPs and API keys must not be stored.
             */
            $table->json('route_audit_log_payload')->nullable();

            /**
             * Uploaded file metadata only.
             * Do not store actual files here.
             */
            $table->json('route_audit_log_files')->nullable();

            $table->string('route_audit_log_exception_class', 255)->nullable();
            $table->text('route_audit_log_exception_message')->nullable();

            $table->integer('route_audit_log_duration_ms')->nullable();

            $table->timestamp('route_audit_log_created_at')->nullable()->useCurrent();

            $table->index('route_audit_log_request_id', 'idx_sacco_route_audit_request_id');
            $table->index('route_audit_log_event_type', 'idx_sacco_route_audit_event_type');
            $table->index('route_audit_log_outcome', 'idx_sacco_route_audit_outcome');
            $table->index('route_audit_log_user_id', 'idx_sacco_route_audit_user_id');
            $table->index('route_audit_log_attempted_login', 'idx_sacco_route_audit_attempted_login');
            $table->index('route_audit_log_route_name', 'idx_sacco_route_audit_route_name');
            $table->index('route_audit_log_method', 'idx_sacco_route_audit_method');
            $table->index('route_audit_log_status_code', 'idx_sacco_route_audit_status_code');
            $table->index('route_audit_log_ip_address', 'idx_sacco_route_audit_ip');
            $table->index('route_audit_log_created_at', 'idx_sacco_route_audit_created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sacco_route_audit_logs');
    }
};