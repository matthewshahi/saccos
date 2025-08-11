<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSaccoMatatuPenaltiesTable extends Migration
{
    public function up()
    {
        Schema::create('sacco_matatu_penalties', function (Blueprint $table) {
            $table->id('penalty_id');

            // Link to sacco_members, but no foreign key constraint
            $table->unsignedBigInteger('penalty_member_id')->nullable()->comment('Refers to sacco_members.member_id');

            $table->string('penalty_type', 100)->nullable()->comment('E.g. Late Payment, Misconduct');
            $table->decimal('penalty_amount', 10, 2)->default(0.00);
            $table->text('penalty_description')->nullable();

            $table->date('penalty_date')->comment('Date when the penalty was applied');

            $table->unsignedBigInteger('penalty_by')->nullable()->comment('User who applied the penalty');
            $table->string('penalty_ip', 45)->nullable()->comment('IP address of the action');

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    public function down()
    {
        Schema::dropIfExists('sacco_matatu_penalties');
    }
}