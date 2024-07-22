<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class ModifyAccTransTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sacco_accounts_trans', function (Blueprint $table) {
            $table->integer('accounts_trans_member_id')->nullable();
            $table->char('accounts_trans_app_name')->nullable();
            $table->boolean('accounts_trans_cash_in_already')->nullable();
            $table->boolean('accounts_trans_reconsiled')->nullable();
            $table->char('accounts_trans_reconsiled_comments')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
