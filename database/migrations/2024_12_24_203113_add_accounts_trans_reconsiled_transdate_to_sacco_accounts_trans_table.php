<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAccountsTransReconsiledTransdateToSaccoAccountsTransTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sacco_accounts_trans', function (Blueprint $table) {
            if (!Schema::hasColumn('sacco_accounts_trans', 'accounts_trans_reconsiled_transdate')) {
                $table->timestamp('accounts_trans_reconsiled_transdate')->nullable()->after('accounts_trans_reconsiled_user_id');
            }
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
            if (Schema::hasColumn('sacco_accounts_trans', 'accounts_trans_reconsiled_transdate')) {
                $table->dropColumn('accounts_trans_reconsiled_transdate');
            }
        });
    }
}