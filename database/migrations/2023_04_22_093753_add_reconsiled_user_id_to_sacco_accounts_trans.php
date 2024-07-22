<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddReconsiledUserIdToSaccoAccountsTrans extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sacco_accounts_trans', function (Blueprint $table) {
            $table->unsignedBigInteger('accounts_trans_reconsiled_user_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sacco_accounts_trans', function (Blueprint $table) {
            //
        });
    }
}
