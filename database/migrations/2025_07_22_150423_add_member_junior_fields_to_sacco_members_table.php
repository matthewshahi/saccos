<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMemberJuniorFieldsToSaccoMembersTable extends Migration
{
    public function up()
    {
        Schema::table('sacco_members', function (Blueprint $table) {
            $table->boolean('member_is_junior')->default(0)->after('member_deleted');
            $table->unsignedBigInteger('member_guardian_id')->nullable()->after('member_is_junior');
            // $table->date('member_dob')->nullable()->after('member_guardian_id');

            // Optional: If you want to enforce relational integrity:
            // $table->foreign('member_guardian_id')->references('member_id')->on('sacco_members')->onDelete('set null');
        });
    }

    public function down()
    {
        Schema::table('sacco_members', function (Blueprint $table) {
            // $table->dropForeign(['member_guardian_id']);
            $table->dropColumn(['member_is_junior', 'member_guardian_id', 'member_dob']);
        });
    }
}