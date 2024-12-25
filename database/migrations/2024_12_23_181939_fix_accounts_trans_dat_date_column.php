<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

class FixAccountsTransDatDateColumn extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        // Step 1: Update invalid '0000-00-00 00:00:00' values to NULL
        DB::table('sacco_accounts_trans')
            ->where('accounts_trans_dat_date', '0000-00-00 00:00:00')
            ->update(['accounts_trans_dat_date' => null]);

        // Step 2: Modify the accounts_trans_dat_date column to allow NULL values
        Schema::table('sacco_accounts_trans', function (Blueprint $table) {
            $table->dateTime('accounts_trans_dat_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // Revert accounts_trans_dat_date column to NOT NULL (if required)
        Schema::table('sacco_accounts_trans', function (Blueprint $table) {
            $table->dateTime('accounts_trans_dat_date')->nullable(false)->default('0000-00-00 00:00:00')->change();
        });
    }
}