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
            // Update 'processed' column to CHAR(1) with a default value of 'N'
            $table->char('processed', 1)->default('N')->change();
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
            // Revert 'processed' column to its original type (e.g., string)
            $table->string('processed')->change();
        });
    }
}