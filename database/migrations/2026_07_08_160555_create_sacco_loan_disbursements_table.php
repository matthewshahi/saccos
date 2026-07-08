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
        Schema::create('sacco_loan_disbursements', function (Blueprint $table) {
            $table->id();

            // Link to your loan system, but no foreign key.
            $table->unsignedBigInteger('loan_id')->index();
            $table->unsignedBigInteger('member_id')->nullable()->index();

            // Member/payment details.
            $table->string('member_name', 150);
            $table->string('member_phone', 15)->index(); // Save as 254722400737
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3)->default('KES');

            // For now we start with MPESA. Later can support NCBA_ACCOUNT, PESALINK, EFT, CASH_PICKUP.
            $table->string('disbursement_channel', 30)->default('MPESA');

            // Unique reference sent to the bank. This is very important to prevent double payment.
            $table->string('transaction_ref', 80)->unique();

            // Short narration to appear in bank/payment records.
            $table->string('narration', 80)->default('LOAN DISBURSEMENT');

            /*
             * Suggested statuses:
             * PENDING_APPROVAL
             * READY_TO_SEND
             * SENDING
             * SENT_TO_BANK
             * BANK_SUCCESS
             * BANK_PENDING
             * FAILED_RETRY
             * FAILED_FINAL
             * CANCELLED
             */
            $table->string('status', 30)->default('PENDING_APPROVAL')->index();

            // Approval/checker details.
            $table->unsignedBigInteger('approved_by')->nullable()->index();
            $table->timestamp('approved_at')->nullable();

            // Sending/confirmation details.
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();

            // Bank response references.
            $table->string('bank_reference', 120)->nullable();
            $table->string('core_reference', 120)->nullable();
            $table->string('bank_status_code', 50)->nullable();
            $table->string('bank_status_message', 255)->nullable();

            // Retry handling.
            $table->unsignedInteger('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable()->index();
            $table->text('last_error')->nullable();

            // Store latest request/response in same table, since you asked for one table only.
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();

            // User who created the disbursement queue record.
            $table->unsignedBigInteger('created_by')->nullable()->index();

            $table->timestamps();

            $table->index(['status', 'next_retry_at']);
            $table->index(['loan_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sacco_loan_disbursements');
    }
};