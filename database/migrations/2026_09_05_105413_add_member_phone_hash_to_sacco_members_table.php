<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sacco_members')) {
            return;
        }

        if (!Schema::hasColumn('sacco_members', 'member_phone_hash')) {
            Schema::table('sacco_members', function (Blueprint $table) {
                $table->char('member_phone_hash', 64)
                    ->nullable()
                    ->after('member_phone_no');
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('sacco_members') &&
            Schema::hasColumn('sacco_members', 'member_phone_hash')
        ) {
            Schema::table('sacco_members', function (Blueprint $table) {
                $table->dropColumn('member_phone_hash');
            });
        }
    }
};