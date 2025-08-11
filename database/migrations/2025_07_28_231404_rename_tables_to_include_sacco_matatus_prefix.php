<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

class RenameTablesToIncludeSaccoMatatusPrefix extends Migration
{
    public function up()
    {
        if (Schema::hasTable('sacco_operators')) {
            Schema::rename('sacco_operators', 'sacco_matatus_operators');
        }

        if (Schema::hasTable('sacco_operator_assignments')) {
            Schema::rename('sacco_operator_assignments', 'sacco_matatus_operator_assignments');
        }

        if (Schema::hasTable('sacco_fleets')) {
            Schema::rename('sacco_fleets', 'sacco_matatus_fleets');
        }

        if (Schema::hasTable('sacco_matatus_routes')) {
            // Already prefixed, so no action
        }
    }

    public function down()
    {
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