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
    Schema::create('kass_staging_contributions', function (Blueprint $table) {
        $table->id();
        $table->string('raw_name')->nullable();
        $table->string('member_identifier')->nullable();
        $table->string('adm_no')->nullable();
        $table->string('company')->nullable();
        $table->integer('year')->nullable();
        $table->string('month')->nullable();
        $table->string('raw_type')->nullable();
        $table->decimal('amount', 15, 2)->nullable();
        $table->string('source_file')->nullable();
        $table->text('notes')->nullable();
        $table->unsignedBigInteger('matched_member_id')->nullable();
        $table->timestamps();
    });
}

};
