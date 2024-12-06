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
        $table->string('shortcode')->nullable(); // Adding the shortcode column
    });
}

public function down()
{
    Schema::table('stk_push_logs', function (Blueprint $table) {
        $table->dropColumn('shortcode'); // Removing the column if the migration is rolled back
    });
}
};
