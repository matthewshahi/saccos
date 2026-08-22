<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sacco_crb_reports', function (Blueprint $table) {
            $table->bigIncrements('crb_report_id');

            $table->string('crb_report_code', 10);
            $table->string('crb_report_frequency', 20);
            $table->date('crb_report_date');
            $table->string('crb_report_period', 8);
            $table->unsignedSmallInteger('crb_report_version')->default(1);

            $table->string('crb_report_status', 30)->default('DRAFT');

            $table->unsignedInteger('crb_report_record_count')->default(0);
            $table->unsignedInteger('crb_report_valid_count')->default(0);
            $table->unsignedInteger('crb_report_warning_count')->default(0);
            $table->unsignedInteger('crb_report_error_count')->default(0);

            $table->string('crb_report_file_name', 255)->nullable();
            $table->string('crb_report_file_path', 500)->nullable();
            $table->char('crb_report_file_hash', 64)->nullable();

            $table->unsignedBigInteger('crb_report_generated_by')->nullable();
            $table->dateTime('crb_report_generated_at')->nullable();

            $table->unsignedBigInteger('crb_report_finalised_by')->nullable();
            $table->dateTime('crb_report_finalised_at')->nullable();

            $table->unsignedBigInteger('crb_report_submitted_by')->nullable();
            $table->dateTime('crb_report_submitted_at')->nullable();

            $table->text('crb_report_notes')->nullable();

            $table->string('crb_report_ip', 100)->nullable();

            $table->timestamp('crb_report_created_at')->useCurrent();
            $table->timestamp('crb_report_updated_at')->nullable();

            $table->unique(
                [
                    'crb_report_code',
                    'crb_report_date',
                    'crb_report_version',
                ],
                'uq_crb_report_version'
            );

            $table->index('crb_report_period', 'idx_crb_report_period');
            $table->index('crb_report_status', 'idx_crb_report_status');

            $table->index(
                [
                    'crb_report_code',
                    'crb_report_date',
                ],
                'idx_crb_report_code_date'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sacco_crb_reports');
    }
};
