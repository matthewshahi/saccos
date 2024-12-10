<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateC2bPaymentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('c2b_payments', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_type');
            $table->string('transaction_id')->unique();
            $table->timestamp('transaction_time')->nullable();
            $table->decimal('transaction_amount', 10, 2);
            $table->string('business_shortcode');
            $table->string('bill_ref_number')->nullable();
            $table->string('invoice_number')->nullable();
            $table->decimal('org_account_balance', 15, 2)->nullable();
            $table->string('third_party_transaction_id')->nullable();
            $table->string('msisdn')->nullable();
            $table->string('first_name')->nullable();
            $table->string('middle_name')->nullable();
            $table->string('last_name')->nullable();
            $table->ipAddress('ip_address')->nullable();
            $table->string('processed')->default('No');
            $table->timestamp('processed_date')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('c2b_payments');
    }
}