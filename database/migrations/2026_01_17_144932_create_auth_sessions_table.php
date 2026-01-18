<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auth_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('user_id');
            $table->string('device_id', 64);                // UUID from app (stored securely)
            $table->string('device_name', 120)->nullable(); // "Android Pixel 7"
            $table->string('refresh_token_hash', 255);      // hashed refresh token (never store plaintext)

            $table->string('ip_address', 45)->nullable();   // supports IPv4/IPv6
            $table->string('user_agent', 255)->nullable();

            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'device_id']);
            $table->index('refresh_token_hash');
            $table->index('revoked_at');

            $table->foreign('user_id')
                ->references('id')->on('users')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auth_sessions');
    }
};
