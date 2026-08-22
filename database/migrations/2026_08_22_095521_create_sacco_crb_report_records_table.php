<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sacco_crb_report_records', function (Blueprint $table) {
            $table->bigIncrements('crb_record_id');

            $table->unsignedBigInteger('crb_record_report_id');
            $table->unsignedInteger('crb_record_sequence');

            $table->unsignedBigInteger('crb_record_member_id')->nullable();
            $table->unsignedBigInteger('crb_record_loan_id')->nullable();

            $table->string('crb_record_source_type', 50)->nullable();
            $table->unsignedBigInteger('crb_record_source_id')->nullable();

            $table->string('crb_record_status', 20)->default('VALID');

            $table->json('crb_record_data');
            $table->json('crb_record_errors')->nullable();

            $table->timestamp('crb_record_created_at')->useCurrent();

            $table->foreign('crb_record_report_id', 'fk_crb_record_report')
                ->references('crb_report_id')
                ->on('sacco_crb_reports')
                ->onDelete('cascade');

            $table->unique(
                [
                    'crb_record_report_id',
                    'crb_record_sequence',
                ],
                'uq_crb_record_sequence'
            );

            $table->index(
                [
                    'crb_record_report_id',
                    'crb_record_status',
                ],
                'idx_crb_record_status'
            );

            $table->index('crb_record_member_id', 'idx_crb_record_member');
            $table->index('crb_record_loan_id', 'idx_crb_record_loan');

            $table->index(
                [
                    'crb_record_source_type',
                    'crb_record_source_id',
                ],
                'idx_crb_record_source'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sacco_crb_report_records');
    }
};
