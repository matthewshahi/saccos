<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payment_inquiries', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number');
            $table->string('status')->nullable();
            $table->longText('response')->nullable();
            $table->unsignedBigInteger('member_id')->nullable();
            $table->string('checked_ip')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_inquiries');
    }
};