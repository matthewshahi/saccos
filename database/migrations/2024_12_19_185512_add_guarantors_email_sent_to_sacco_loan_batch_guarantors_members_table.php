<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        // Check if the table exists
        if (!Schema::hasTable('sacco_loan_batch_guarantors_members')) {
            Schema::create('sacco_loan_batch_guarantors_members', function (Blueprint $table) {
                $table->id('guarantors_id');
                $table->unsignedBigInteger('guarantors_loan_batch_trans_id')->nullable();
                $table->unsignedBigInteger('guarantors_guarantor_id')->nullable();
                $table->double('guarantors_amount_guaranteed')->nullable();
                $table->string('guarantors_description', 100)->nullable();
                $table->string('guarantors_transfered', 100)->nullable();
                $table->char('guarantors_approved', 1)->default('N');
                $table->char('guarantors_email_sent', 1)->default('N');
                $table->unsignedBigInteger('guarantors_by')->nullable();
                $table->timestamp('guarantors_on')->useCurrent();
                $table->string('guarantors_ip', 100)->nullable();
                $table->char('guarantors_deleted', 1)->default('N');
                $table->unsignedBigInteger('guarantors_deleted_by')->nullable();
                $table->datetime('guarantors_deleted_on')->nullable();
                $table->string('guarantors_deleted_ip', 100)->nullable();

                $table->timestamps();
            });
        } else {
            // Add the 'guarantors_email_sent' column if the table exists but the column does not
            if (!Schema::hasColumn('sacco_loan_batch_guarantors_members', 'guarantors_email_sent')) {
                Schema::table('sacco_loan_batch_guarantors_members', function (Blueprint $table) {
                    $table->char('guarantors_email_sent', 1)->default('N')->after('guarantors_approved');
                });
            }
        }
    }

    public function down()
    {
        // Drop the column only if it exists
        if (Schema::hasTable('sacco_loan_batch_guarantors_members') && Schema::hasColumn('sacco_loan_batch_guarantors_members', 'guarantors_email_sent')) {
            Schema::table('sacco_loan_batch_guarantors_members', function (Blueprint $table) {
                $table->dropColumn('guarantors_email_sent');
            });
        }

        // Optionally drop the table if needed
        // Schema::dropIfExists('sacco_loan_batch_guarantors_members');
    }
};