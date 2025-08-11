<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sacco_matatus_collections', function (Blueprint $table) {
    $table->string('coll_source_log')->nullable()->after('coll_mode');
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
