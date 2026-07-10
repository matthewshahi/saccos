<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sacco_member_loan_limits', function (Blueprint $table) {
            $table->integer('member_loan_limit_id', true);

            $table->integer('member_loan_limit_member_id');

            $table->integer('member_loan_limit_loan_type_id');

            $table->decimal(
                'member_loan_limit_amount',
                15,
                2
            );

            $table->boolean('member_loan_limit_active')
                ->default(true);

            $table->char(
                'member_loan_limit_deleted',
                1
            )->default('N');

            $table->integer('member_loan_limit_by')
                ->nullable();

            $table->timestamp(
                'member_loan_limit_transdate'
            )->useCurrent();

            $table->string(
                'member_loan_limit_ip',
                100
            )->nullable();

            /*
            |--------------------------------------------------------------------------
            | One limit per member per loan type
            |--------------------------------------------------------------------------
            */
            $table->unique(
                [
                    'member_loan_limit_member_id',
                    'member_loan_limit_loan_type_id',
                ],
                'uq_member_loan_type_limit'
            );

            /*
            |--------------------------------------------------------------------------
            | Search indexes
            |--------------------------------------------------------------------------
            */
            $table->index(
                'member_loan_limit_member_id',
                'idx_member_loan_limit_member'
            );

            $table->index(
                'member_loan_limit_loan_type_id',
                'idx_member_loan_limit_type'
            );

            $table->index(
                [
                    'member_loan_limit_active',
                    'member_loan_limit_deleted',
                ],
                'idx_member_loan_limit_status'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sacco_member_loan_limits');
    }
};