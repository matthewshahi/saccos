<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateProcessedColumnInStkPushResponses extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('stk_push_responses', function (Blueprint $table) {
            // Drop the existing 'processed' column
            $table->dropColumn('processed');
        });

        Schema::table('stk_push_responses', function (Blueprint $table) {
            // Recreate the 'processed' column as CHAR(1) with a default value of 'N'
            $table->char('processed', 1)->default('N'); // Replace 'some_column' with the correct column name where it should be positioned
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('stk_push_responses', function (Blueprint $table) {
            // Drop the 'processed' column
            $table->dropColumn('processed');
        });

        Schema::table('stk_push_responses', function (Blueprint $table) {
            // Recreate the 'processed' column as a string (default behavior)
            $table->string('processed'); // Replace 'some_column' with the correct column name where it should be positioned
        });
    }
}