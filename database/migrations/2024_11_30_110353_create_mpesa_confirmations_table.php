<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMpesaConfirmationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('mpesa_confirmations', function (Blueprint $table) {
            $table->bigIncrements('id'); // Primary key with auto-increment
            $table->string('transaction_id')->unique(); // Unique transaction ID
            $table->string('transaction_type'); // Type of transaction
            $table->decimal('amount', 10, 2); // Transaction amount
            $table->string('account_reference')->nullable(); // Account reference, nullable
            $table->string('phone_number'); // Phone number
            $table->string('invoice_number')->nullable(); // Invoice number, nullable
            $table->string('organization_balance')->nullable(); // Organization balance, nullable
            $table->string('third_party_transaction_id')->nullable(); // Third-party transaction ID, nullable
            $table->string('bill_ref_number')->nullable(); // Bill reference number, nullable
            $table->string('shortcode'); // Shortcode
            $table->timestamp('transaction_time'); // Time of the transaction
            $table->timestamps(); // Created_at and updated_at timestamps
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('mpesa_confirmations');
    }
}