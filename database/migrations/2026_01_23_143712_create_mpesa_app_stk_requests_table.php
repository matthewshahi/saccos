<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mpesa_app_stk_requests', function (Blueprint $table) {

            // Primary key
            $table->bigIncrements('id');

            // Member / user initiating the request
            $table->unsignedBigInteger('member_id')->index();

            // STK details (already cleaned by controller)
            $table->string('phone', 15);
            $table->decimal('amount', 15, 2);
            $table->string('reference', 50)->index();

            // Lifecycle tracking
            $table->enum('status', [
                'PENDING',
                'SENT',
                'SUCCESS',
                'FAILED',
                'CANCELLED'
            ])->default('PENDING');

            // Optional response / error tracking
            $table->string('mpesa_checkout_request_id', 100)->nullable()->index();
            $table->string('mpesa_merchant_request_id', 100)->nullable()->index();
            $table->text('failure_reason')->nullable();

            // Audit fields
            $table->ipAddress('request_ip')->nullable();
            $table->timestamps();

            // Basic integrity guard
            $table->unique(
                ['member_id', 'reference', 'amount', 'created_at'],
                'uniq_member_reference_amount_time'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mpesa_app_stk_requests');
    }
};
