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
    Schema::table('sacco_matatus_collections', function (Blueprint $table) {
        $table->enum('coll_reconciled', ['Yes', 'No'])->default('Yes')->after('coll_notes');
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sacco_matatus_collections', function (Blueprint $table) {
            //
        });
    }
};
