<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSaccoMemberPasswordResetsTable extends Migration
{
    public function up()
    {
        Schema::create('sacco_member_password_resets', function (Blueprint $table) {
            $table->bigIncrements('reset_id');

            /*
             * Linked member if the submitted details match an active SACCO member.
             * References sacco_members.member_id logically.
             */
            $table->unsignedBigInteger('reset_member_id')->nullable();

            /*
             * Member/account number entered by the user.
             * This helps audit/support trace reset requests.
             * Do not use National ID here.
             */
            $table->string('reset_account_number', 100)->nullable();

            /*
             * Email entered by the user, normalized to lowercase.
             */
            $table->string('reset_email', 191)->nullable();

            /*
             * Hash of account number + email.
             * Useful for throttling without depending only on plain values.
             */
            $table->char('reset_identifier_hash', 64);

            /*
             * SHA-256 hash of reset token.
             * The plain token is only sent by email and never stored.
             */
            $table->char('reset_token_hash', 64)->unique('smpr_token_hash_uq');

            /*
             * Token lifecycle.
             */
            $table->timestamp('reset_expires_at')->nullable();
            $table->timestamp('reset_used_at')->nullable();
            $table->timestamp('reset_sent_at')->nullable();

            /*
             * Request audit details.
             */
            $table->string('reset_ip', 64)->nullable();
            $table->string('reset_user_agent', 255)->nullable();

            /*
             * Status examples:
             * requested, sent, used, expired, blocked
             */
            $table->string('reset_status', 30)->default('requested');

            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            /*
             * Short custom index names to avoid MySQL 64-char limit.
             */
            $table->index('reset_member_id', 'smpr_member_idx');
            $table->index('reset_account_number', 'smpr_account_idx');
            $table->index('reset_email', 'smpr_email_idx');
            $table->index('reset_identifier_hash', 'smpr_ident_idx');
            $table->index('reset_expires_at', 'smpr_expires_idx');
            $table->index('reset_used_at', 'smpr_used_idx');
            $table->index('reset_ip', 'smpr_ip_idx');

            $table->index(['reset_identifier_hash', 'created_at'], 'smpr_ident_created_idx');
            $table->index(['reset_member_id', 'created_at'], 'smpr_member_created_idx');
            $table->index(['reset_email', 'created_at'], 'smpr_email_created_idx');
            $table->index(['reset_account_number', 'created_at'], 'smpr_account_created_idx');
        });
    }

    public function down()
    {
        Schema::dropIfExists('sacco_member_password_resets');
    }
}