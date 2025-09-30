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
    Schema::create('sacco_registration_fees', function (Blueprint $table) {
        $table->bigIncrements('regfee_id');

        // Core fields
        $table->unsignedBigInteger('regfee_member_id');
        $table->decimal('regfee_amount', 12, 2);
        $table->string('regfee_doc_no', 50)->nullable();
        $table->string('regfee_description', 255)->nullable();
        $table->date('regfee_date_paid');

        // Audit
        $table->unsignedBigInteger('regfee_paid_by')->nullable();
        $table->string('regfee_ip', 45)->nullable();
        $table->unsignedBigInteger('regfee_by')->nullable();
        $table->string('regfee_created_ip', 45)->nullable();
        $table->unsignedBigInteger('regfee_updated_by')->nullable();
        $table->string('regfee_updated_ip', 45)->nullable();

        // Processing dates
        $table->dateTime('regfee_transdate')->useCurrent();
        $table->string('regfee_end_month_proc', 6)->nullable(); // YYYYmm

        $table->timestamps();

        // Indexes
        $table->index('regfee_member_id');
        $table->index('regfee_doc_no');
        $table->index('regfee_date_paid');
        $table->index('regfee_end_month_proc');
        $table->index(['regfee_member_id', 'regfee_end_month_proc']);
    });
}

public function down(): void
{
    Schema::dropIfExists('sacco_registration_fees');
}
};
