<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMpesaStkReconciliationSupport extends Migration
{
    public function up()
    {
        if (Schema::hasTable('stk_push_logs')) {
            Schema::table('stk_push_logs', function (Blueprint $table) {
                if (!Schema::hasColumn('stk_push_logs', 'recon_status')) {
                    $table->string('recon_status')->nullable()->after('shortcode');
                }

                if (!Schema::hasColumn('stk_push_logs', 'recon_source')) {
                    $table->string('recon_source')->nullable()->after('recon_status');
                }

                if (!Schema::hasColumn('stk_push_logs', 'recon_attempts')) {
                    $table->unsignedInteger('recon_attempts')->default(0)->after('recon_source');
                }

                if (!Schema::hasColumn('stk_push_logs', 'last_reconciled_at')) {
                    $table->timestamp('last_reconciled_at')->nullable()->after('recon_attempts');
                }

                if (!Schema::hasColumn('stk_push_logs', 'recon_message')) {
                    $table->text('recon_message')->nullable()->after('last_reconciled_at');
                }
            });
        }

        if (!Schema::hasTable('mpesa_stk_reconciliations')) {
            Schema::create('mpesa_stk_reconciliations', function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->unsignedBigInteger('stk_push_log_id')->nullable();
                $table->unsignedBigInteger('c2b_payment_id')->nullable();
                $table->unsignedBigInteger('stk_push_response_id')->nullable();

                $table->string('unique_number')->nullable();
                $table->string('checkout_request_id')->nullable();
                $table->string('merchant_request_id')->nullable();

                $table->string('phone_number')->nullable();
                $table->decimal('amount', 20, 2)->nullable();
                $table->string('shortcode')->nullable();

                $table->string('recon_status')->nullable();
                $table->string('recon_source')->nullable();
                $table->string('match_confidence')->nullable();

                $table->string('safaricom_result_code')->nullable();
                $table->text('safaricom_result_description')->nullable();

                $table->string('mpesa_receipt_number')->nullable();
                $table->timestamp('transaction_time')->nullable();

                $table->json('raw_response')->nullable();
                $table->text('notes')->nullable();

                $table->unsignedInteger('attempt_no')->default(1);
                $table->timestamp('attempted_at')->nullable();

                $table->timestamps();

                $table->index('stk_push_log_id', 'idx_recon_stk_log_id');
                $table->index('c2b_payment_id', 'idx_recon_c2b_payment_id');
                $table->index('stk_push_response_id', 'idx_recon_stk_response_id');
                $table->index('checkout_request_id', 'idx_recon_checkout_request_id');
                $table->index('unique_number', 'idx_recon_unique_number');
                $table->index('recon_status', 'idx_recon_status');
                $table->index('recon_source', 'idx_recon_source');
                $table->index('attempted_at', 'idx_recon_attempted_at');
            });
        }

        if (Schema::hasTable('stk_push_logs')) {
            Schema::table('stk_push_logs', function (Blueprint $table) {
                try {
                    $table->index(['status', 'created_at'], 'idx_stk_status_created');
                } catch (\Throwable $e) {}

                try {
                    $table->index('checkout_request_id', 'idx_stk_checkout_request_id');
                } catch (\Throwable $e) {}

                try {
                    $table->index('unique_number', 'idx_stk_unique_number');
                } catch (\Throwable $e) {}

                try {
                    $table->index('recon_status', 'idx_stk_recon_status');
                } catch (\Throwable $e) {}
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('stk_push_logs')) {
            Schema::table('stk_push_logs', function (Blueprint $table) {
                try {
                    $table->dropIndex('idx_stk_status_created');
                } catch (\Throwable $e) {}

                try {
                    $table->dropIndex('idx_stk_checkout_request_id');
                } catch (\Throwable $e) {}

                try {
                    $table->dropIndex('idx_stk_unique_number');
                } catch (\Throwable $e) {}

                try {
                    $table->dropIndex('idx_stk_recon_status');
                } catch (\Throwable $e) {}

                if (Schema::hasColumn('stk_push_logs', 'recon_message')) {
                    $table->dropColumn('recon_message');
                }

                if (Schema::hasColumn('stk_push_logs', 'last_reconciled_at')) {
                    $table->dropColumn('last_reconciled_at');
                }

                if (Schema::hasColumn('stk_push_logs', 'recon_attempts')) {
                    $table->dropColumn('recon_attempts');
                }

                if (Schema::hasColumn('stk_push_logs', 'recon_source')) {
                    $table->dropColumn('recon_source');
                }

                if (Schema::hasColumn('stk_push_logs', 'recon_status')) {
                    $table->dropColumn('recon_status');
                }
            });
        }

        Schema::dropIfExists('mpesa_stk_reconciliations');
    }
}