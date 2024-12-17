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
    Schema::table('sacco_members', function (Blueprint $table) {
        $table->string('member_signature')->nullable()->after('member_image');
        $table->string('member_id_copy_front')->nullable()->after('member_signature');
        $table->string('member_id_copy_back')->nullable()->after('member_id_copy_front');
        $table->string('member_payslips_bank_statements')->nullable()->after('member_id_copy_back');
    });
}

public function down()
{
    Schema::table('sacco_members', function (Blueprint $table) {
        $table->dropColumn([
            'member_signature',
            'member_id_copy_front',
            'member_id_copy_back',
            'member_payslips_bank_statements'
        ]);
    });
}
};
