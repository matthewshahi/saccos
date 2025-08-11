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
    Schema::create('sacco_matatus_routes', function (Blueprint $table) {
        $table->id();
        $table->string('route_name')->unique();
        $table->string('route_start')->nullable();
        $table->string('route_end')->nullable();
        $table->decimal('route_distance_km', 5, 2)->nullable(); // Optional
        $table->timestamps();
    });
}
};
