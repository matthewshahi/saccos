<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDocumentFieldsToSaccoMembersNewApplications extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            $table->string('passport_photo')->nullable()->after('kin_share_percent')->comment('Path to uploaded passport photo');
            $table->string('signature')->nullable()->after('passport_photo')->comment('Path to uploaded signature');
            $table->string('id_copy_front')->nullable()->after('signature')->comment('Path to uploaded ID copy (front)');
            $table->string('id_copy_back')->nullable()->after('id_copy_front')->comment('Path to uploaded ID copy (back)');
            $table->string('payslips_bank_statements')->nullable()->after('id_copy_back')->comment('Path to uploaded payslips or bank statements');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            $table->dropColumn('passport_photo');
            $table->dropColumn('signature');
            $table->dropColumn('id_copy_front');
            $table->dropColumn('id_copy_back');
            $table->dropColumn('payslips_bank_statements');
        });
    }
}