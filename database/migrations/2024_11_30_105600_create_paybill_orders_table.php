<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreatePaybillOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('paybill_orders', function (Blueprint $table) {
            $table->bigIncrements('id'); // Auto-increment primary key
            $table->decimal('amount', 10, 2)->nullable(); // Order amount
            $table->timestamp('created_at')->useCurrent(); // Timestamp with default current time
            $table->timestamp('updated_at')->nullable(); // Nullable timestamp for updates
            $table->integer('user_id')->nullable(); // Foreign key to user
            $table->integer('package_id')->nullable(); // Foreign key to package
            $table->enum('is_paid', ['PAID', 'PENDING'])->default('PENDING'); // Payment status
            $table->text('TransID')->nullable(); // Transaction ID
            $table->decimal('amount_paid', 10, 2)->nullable(); // Amount paid
            $table->decimal('balance', 10, 2)->nullable(); // Balance amount
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('paybill_orders');
    }
}