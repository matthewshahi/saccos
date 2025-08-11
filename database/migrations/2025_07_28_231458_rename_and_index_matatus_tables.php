<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RenameAndIndexMatatusTables extends Migration
{
    public function up()
    {
        // Rename tables
        if (Schema::hasTable('sacco_operators')) {
            Schema::rename('sacco_operators', 'sacco_matatus_operators');
        }

        if (Schema::hasTable('sacco_operator_assignments')) {
            Schema::rename('sacco_operator_assignments', 'sacco_matatus_operator_assignments');
        }

        if (Schema::hasTable('sacco_fleets')) {
            Schema::rename('sacco_fleets', 'sacco_matatus_fleets');
        }

        // Add indexes
        Schema::table('sacco_matatus_operators', function (Blueprint $table) {
            if (!Schema::hasColumn('sacco_matatus_operators', 'introduced_by_member_id')) return;
            $table->index('introduced_by_member_id', 'idx_operator_introduced_by');
        });

        Schema::table('sacco_matatus_operator_assignments', function (Blueprint $table) {
            if (!Schema::hasColumn('sacco_matatus_operator_assignments', 'operator_id')) {
                return;
            }
            $table->index('operator_id', 'idx_assignment_operator');
            $table->index('vehicle_id', 'idx_assignment_vehicle');
        });

        Schema::table('sacco_matatus_fleets', function (Blueprint $table) {
            if (Schema::hasColumn('sacco_matatus_fleets', 'sacco_id')) {
                $table->index('sacco_id', 'idx_fleet_sacco');
            }
        });

        Schema::table('sacco_matatus_routes', function (Blueprint $table) {
            $table->index('route_name', 'idx_route_name');
            $table->index('status', 'idx_route_status');
        });
    }

    public function down()
    {
        // Remove indexes
        Schema::table('sacco_matatus_operators', function (Blueprint $table) {
            $table->dropIndex('idx_operator_introduced_by');
        });

        Schema::table('sacco_matatus_operator_assignments', function (Blueprint $table) {
            $table->dropIndex('idx_assignment_operator');
            $table->dropIndex('idx_assignment_vehicle');
        });

        Schema::table('sacco_matatus_fleets', function (Blueprint $table) {
            $table->dropIndex('idx_fleet_sacco');
        });

        Schema::table('sacco_matatus_routes', function (Blueprint $table) {
            $table->dropIndex('idx_route_name');
            $table->dropIndex('idx_route_status');
        });

        // Rename tables back
        if (Schema::hasTable('sacco_matatus_operators')) {
            Schema::rename('sacco_matatus_operators', 'sacco_operators');
        }

        if (Schema::hasTable('sacco_matatus_operator_assignments')) {
            Schema::rename('sacco_matatus_operator_assignments', 'sacco_operator_assignments');
        }

        if (Schema::hasTable('sacco_matatus_fleets')) {
            Schema::rename('sacco_matatus_fleets', 'sacco_fleets');
        }
    }
}