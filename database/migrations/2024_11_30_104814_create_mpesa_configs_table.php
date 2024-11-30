<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMpesaConfigsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mpesa_configs', function (Blueprint $table) {
            $table->bigIncrements('id'); // Primary Key
            $table->string('shortcode', 255);
            $table->string('api_type', 255)->default('mpesa_express');
            $table->string('response_type', 255);
            $table->string('confirmation_url', 255);
            $table->string('validation_url', 255);
            $table->string('consumer_key', 255);
            $table->string('consumer_secret', 255);
            $table->string('passkey', 255);
            $table->string('access_token', 255)->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mpesa_configs');
    }
}