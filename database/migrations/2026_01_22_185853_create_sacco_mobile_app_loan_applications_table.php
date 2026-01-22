<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sacco_mobile_app_loan_applications', function (Blueprint $table) {

            /*
            |--------------------------------------------------
            | Primary key
            |--------------------------------------------------
            */
            $table->bigIncrements('mobile_app_application_id');

            /*
            |--------------------------------------------------
            | Ownership
            |--------------------------------------------------
            */
            $table->unsignedBigInteger('mobile_app_member_id');

            /*
            |--------------------------------------------------
            | Loan core snapshot (mobile app)
            |--------------------------------------------------
            */
            $table->unsignedBigInteger('mobile_app_loan_type_id');
            $table->decimal('mobile_app_amount', 14, 2);
            $table->unsignedInteger('mobile_app_duration_months');
            $table->text('mobile_app_reason')->nullable();

            /*
            |--------------------------------------------------
            | Top-up context
            |--------------------------------------------------
            */
            $table->unsignedBigInteger('mobile_app_topup_loan_id')->nullable();

            /*
            |--------------------------------------------------
            | Employment snapshot (mobile app)
            |--------------------------------------------------
            */
            $table->string('mobile_app_payroll_number', 100)->nullable();
            $table->string('mobile_app_designation', 150)->nullable();
            $table->string('mobile_app_employment_terms', 150)->nullable();

            /*
            |--------------------------------------------------
            | Consent
            |--------------------------------------------------
            */
            $table->boolean('mobile_app_agree')->default(false);

            /*
            |--------------------------------------------------
            | Duplicate protection
            |--------------------------------------------------
            */
            $table->char('mobile_app_payload_hash', 64);

            /*
            |--------------------------------------------------
            | Timing
            |--------------------------------------------------
            */
            $table->timestamp('mobile_app_submitted_at')->useCurrent();

            /*
            |--------------------------------------------------
            | Status lifecycle
            |--------------------------------------------------
            */
            $table->string('mobile_app_status', 30)->default('pending');
            // pending | approved | rejected | cancelled

            /*
            |--------------------------------------------------
            | Audit
            |--------------------------------------------------
            */
            $table->ipAddress('mobile_app_submitted_ip')->nullable();
            $table->string('mobile_app_submitted_by', 50)->nullable();

            /*
            |--------------------------------------------------
            | Indexes & constraints
            |--------------------------------------------------
            */
            $table->index(['mobile_app_member_id', 'mobile_app_submitted_at'], 'idx_mobile_app_member_time');
            $table->index('mobile_app_payload_hash', 'idx_mobile_app_payload_hash');

            // HARD duplicate prevention (exact same mobile payload)
            $table->unique(
                'mobile_app_payload_hash',
                'uq_mobile_app_loan_application_payload'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sacco_mobile_app_loan_applications');
    }
};
