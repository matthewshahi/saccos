<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (
            !Schema::hasColumn(
                'sacco_members',
                'member_mobile_banking_active'
            )
        ) {
            Schema::table('sacco_members', function (Blueprint $table) {
                $table->char(
                    'member_mobile_banking_active',
                    1
                )
                    ->default('N')
                    ->after('member_active')
                    ->index();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (
            Schema::hasColumn(
                'sacco_members',
                'member_mobile_banking_active'
            )
        ) {
            Schema::table('sacco_members', function (Blueprint $table) {
                $table->dropIndex([
                    'member_mobile_banking_active',
                ]);

                $table->dropColumn(
                    'member_mobile_banking_active'
                );
            });
        }
    }
};