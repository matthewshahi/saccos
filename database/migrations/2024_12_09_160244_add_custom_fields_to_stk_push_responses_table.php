<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCustomFieldsToStkPushResponsesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('stk_push_responses', function (Blueprint $table) {
            // Add the unique_number field
            $table->string('unique_number', 255)->nullable()->after('id');

            // Add the processed field (Yes/No) as ENUM or boolean
            $table->enum('processed', ['Yes', 'No'])->default('No')->after('amount');

            // Add the processed_date field
            $table->timestamp('processed_date')->nullable()->after('processed');
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
            // Drop the added fields
            $table->dropColumn('unique_number');
            $table->dropColumn('processed');
            $table->dropColumn('processed_date');
        });
    }
}