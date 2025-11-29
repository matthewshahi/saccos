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
    Schema::table('c2b_payments', function (Blueprint $table) {
        $table->enum('picked', ['Yes', 'No'])->default('No')->after('processed');
        $table->text('failure_reason')->nullable()->after('picked');
    });
}

public function down()
{
    Schema::table('c2b_payments', function (Blueprint $table) {
        $table->dropColumn(['picked', 'failure_reason']);
    });
}

};
