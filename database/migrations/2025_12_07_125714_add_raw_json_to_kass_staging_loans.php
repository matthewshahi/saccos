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
    Schema::table('kass_staging_loans', function (Blueprint $table) {
        $table->longText('raw_row_json')->nullable()->after('source_file');
    });
}

public function down()
{
    Schema::table('kass_staging_loans', function (Blueprint $table) {
        $table->dropColumn('raw_row_json');
    });
}

};
