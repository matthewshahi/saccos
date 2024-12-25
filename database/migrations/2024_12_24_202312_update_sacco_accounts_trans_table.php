<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateSaccoAccountsTransTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sacco_accounts_trans', function (Blueprint $table) {
            // Check and add 'accounts_trans_cash_in_already' if it doesn't exist
            if (!Schema::hasColumn('sacco_accounts_trans', 'accounts_trans_cash_in_already')) {
                $table->string('accounts_trans_cash_in_already', 1)->nullable()->default(null)->after('accounts_trans_app_name');
            } else {
                // Alter the column to accept NULL, 'Y', or 'N'
                $table->string('accounts_trans_cash_in_already', 1)->nullable()->default(null)->change();
            }

            // Check and add 'accounts_trans_reconsiled' if it doesn't exist
            if (!Schema::hasColumn('sacco_accounts_trans', 'accounts_trans_reconsiled')) {
                $table->string('accounts_trans_reconsiled', 1)->nullable()->default(null)->after('accounts_trans_cash_in_already');
            } else {
                // Alter the column to accept NULL, 'Y', or 'N'
                $table->string('accounts_trans_reconsiled', 1)->nullable()->default(null)->change();
            }

            // Check and add 'accounts_trans_reconsiled_comments' if it doesn't exist
            if (!Schema::hasColumn('sacco_accounts_trans', 'accounts_trans_reconsiled_comments')) {
                $table->string('accounts_trans_reconsiled_comments', 255)->nullable()->after('accounts_trans_reconsiled');
            }

            // Check and add 'accounts_trans_payment_type' if it doesn't exist
            if (!Schema::hasColumn('sacco_accounts_trans', 'accounts_trans_payment_type')) {
                $table->string('accounts_trans_payment_type', 255)->nullable()->after('accounts_trans_reconsiled_comments');
            }

            // Check and add 'accounts_trans_reconsiled_user_id' if it doesn't exist
            if (!Schema::hasColumn('sacco_accounts_trans', 'accounts_trans_reconsiled_user_id')) {
                $table->unsignedBigInteger('accounts_trans_reconsiled_user_id')->nullable()->after('accounts_trans_payment_type');
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
            // Optional: Drop the added columns if they exist
            if (Schema::hasColumn('sacco_accounts_trans', 'accounts_trans_cash_in_already')) {
                $table->dropColumn('accounts_trans_cash_in_already');
            }

            if (Schema::hasColumn('sacco_accounts_trans', 'accounts_trans_reconsiled')) {
                $table->dropColumn('accounts_trans_reconsiled');
            }

            if (Schema::hasColumn('sacco_accounts_trans', 'accounts_trans_reconsiled_comments')) {
                $table->dropColumn('accounts_trans_reconsiled_comments');
            }

            if (Schema::hasColumn('sacco_accounts_trans', 'accounts_trans_payment_type')) {
                $table->dropColumn('accounts_trans_payment_type');
            }

            if (Schema::hasColumn('sacco_accounts_trans', 'accounts_trans_reconsiled_user_id')) {
                $table->dropColumn('accounts_trans_reconsiled_user_id');
            }
        });
    }
}
