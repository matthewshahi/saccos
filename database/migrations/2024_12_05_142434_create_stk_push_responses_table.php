<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStkPushResponsesTable extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('stk_push_responses', function (Blueprint $table) {
            $table->id();
            $table->string('checkout_request_id');
            $table->string('merchant_request_id')->nullable();
            $table->integer('result_code');
            $table->string('result_description');
            $table->string('mpesa_receipt_number')->nullable();
            $table->dateTime('transaction_date')->nullable();
            $table->string('phone_number')->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down()
    {
        Schema::dropIfExists('stk_push_responses');
    }
}