<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateSaccoOperatorsAddGenderStatusAndNationalId extends Migration
{
    public function up()
    {
        Schema::table('sacco_operators', function (Blueprint $table) {
            if (!Schema::hasColumn('sacco_operators', 'national_id')) {
                $table->string('national_id')->nullable()->after('phone');
            }

            if (!Schema::hasColumn('sacco_operators', 'gender')) {
                $table->enum('gender', ['male', 'female'])->nullable()->after('national_id');
            }

            if (!Schema::hasColumn('sacco_operators', 'status')) {
                $table->enum('status', ['active', 'inactive'])->default('active')->after('gender');
            }
        });
    }

    public function down()
    {
        Schema::table('sacco_operators', function (Blueprint $table) {
            if (Schema::hasColumn('sacco_operators', 'national_id')) {
                $table->dropColumn('national_id');
            }

            if (Schema::hasColumn('sacco_operators', 'gender')) {
                $table->dropColumn('gender');
            }

            if (Schema::hasColumn('sacco_operators', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
}