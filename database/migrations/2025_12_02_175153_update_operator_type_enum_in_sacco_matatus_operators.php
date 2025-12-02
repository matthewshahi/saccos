<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up() {
        DB::statement("
            ALTER TABLE sacco_matatus_operators 
            MODIFY operator_type 
            ENUM('driver','conductor','staff','rider','cab')
            NOT NULL DEFAULT 'driver'
        ");
    }

    public function down() {
        DB::statement("
            ALTER TABLE sacco_matatus_operators 
            MODIFY operator_type 
            ENUM('driver','conductor')
            NOT NULL DEFAULT 'driver'
        ");
    }
};
