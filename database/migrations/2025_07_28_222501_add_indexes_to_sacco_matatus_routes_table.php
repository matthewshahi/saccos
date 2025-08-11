<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sacco_matatus_routes', function (Blueprint $table) {
            $table->index('route_name');
            $table->index('route_start');
            $table->index('route_end');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('sacco_matatus_routes', function (Blueprint $table) {
            $table->dropIndex(['route_name']);
            $table->dropIndex(['route_start']);
            $table->dropIndex(['route_end']);
            $table->dropIndex(['status']);
        });
    }
};
