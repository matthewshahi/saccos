<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateStkPushLogsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('stk_push_logs', function (Blueprint $table) {
            $table->bigIncrements('id'); // Auto-increment primary key
            $table->string('unique_number', 255)->nullable();
            $table->string('merchant_request_id', 255)->nullable();
            $table->string('checkout_request_id', 255)->nullable();
            $table->string('result_code', 255)->nullable();
            $table->string('result_description', 255)->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('transaction_id', 255)->nullable();
            $table->timestamp('transaction_time')->nullable();
            $table->string('phone_number', 20)->nullable();
            $table->string('account_reference', 255)->nullable();
            $table->text('transaction_description')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->integer('user_id')->nullable();
            $table->integer('package_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('stk_push_logs');
    }
}