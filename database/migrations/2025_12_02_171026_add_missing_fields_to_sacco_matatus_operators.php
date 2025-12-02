<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('sacco_matatus_operators', function (Blueprint $table) {

            // Introduced by member
            if (!Schema::hasColumn('sacco_matatus_operators', 'introduced_by_member_id')) {
                $table->unsignedBigInteger('introduced_by_member_id')->nullable()->after('operator_type');
            }

            // ID Number
            if (!Schema::hasColumn('sacco_matatus_operators', 'id_number')) {
                $table->string('id_number', 20)->nullable();
            }

            // KRA PIN
            if (!Schema::hasColumn('sacco_matatus_operators', 'kra_pin')) {
                $table->string('kra_pin', 20)->nullable();
            }

            // NTSA licence
            if (!Schema::hasColumn('sacco_matatus_operators', 'ntsa_number')) {
                $table->string('ntsa_number', 50)->nullable();
            }

            // Driving licence
            if (!Schema::hasColumn('sacco_matatus_operators', 'driving_licence')) {
                $table->string('driving_licence', 50)->nullable();
            }

            // Jacket number
            if (!Schema::hasColumn('sacco_matatus_operators', 'jacket_number')) {
                $table->string('jacket_number', 50)->nullable();
            }

            // UNIQUE KEYS to prevent duplicates
            $table->unique(['id_number'], 'unique_operator_id_number');
            $table->unique(['kra_pin'], 'unique_operator_kra_pin');
            $table->unique(['ntsa_number'], 'unique_operator_ntsa_number');

        });
    }

    public function down()
    {
        Schema::table('sacco_matatus_operators', function (Blueprint $table) {
            $table->dropUnique('unique_operator_id_number');
            $table->dropUnique('unique_operator_kra_pin');
            $table->dropUnique('unique_operator_ntsa_number');

            $table->dropColumn([
                'introduced_by_member_id',
                'id_number',
                'kra_pin',
                'ntsa_number',
                'driving_licence',
                'jacket_number'
            ]);
        });
    }
};
