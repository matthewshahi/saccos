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
    Schema::table('sacco_loan_types', function (Blueprint $table) {
        $table->tinyInteger('loan_type_instant_qualification')
              ->default(0)
              ->after('loan_type_share_factor'); // or choose where you prefer
    });
}

public function down()
{
    Schema::table('sacco_loan_types', function (Blueprint $table) {
        $table->dropColumn('loan_type_instant_qualification');
    });
}

};
