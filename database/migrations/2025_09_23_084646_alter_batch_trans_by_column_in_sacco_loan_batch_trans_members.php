<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        Schema::table('sacco_loan_batch_trans_members', function (Blueprint $table) {
            // change batch_trans_by to varchar(50)
            $table->string('batch_trans_by', 50)->change();
        });
    }

    public function down()
    {
        Schema::table('sacco_loan_batch_trans_members', function (Blueprint $table) {
            // rollback to integer if needed
            $table->integer('batch_trans_by')->change();
        });
    }
};