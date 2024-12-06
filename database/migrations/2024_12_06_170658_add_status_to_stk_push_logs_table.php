<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::table('stk_push_logs', function (Blueprint $table) {
        $table->string('status')->default('pending')->after('transaction_description');
    });
}

public function down()
{
    Schema::table('stk_push_logs', function (Blueprint $table) {
        $table->dropColumn('status');
    });
}
};
