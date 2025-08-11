<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sacco_matatus_routes', function (Blueprint $table) {
            $table->string('status')->default('active')->after('route_distance_km');
        });
    }

    public function down(): void
    {
        Schema::table('sacco_matatus_routes', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};