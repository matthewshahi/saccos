<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Member-to-classification many-to-many relationship.
     *
     * A member may belong to multiple independent classifications:
     *
     * - Board Director
     * - Credit Committee Chairperson
     * - Education Committee Member
     * - Investment Committee Member
     *
     * This table does not replace sacco_members.member_position.
     */
    public function up(): void
    {
        Schema::create('sacco_member_classification_members', function (Blueprint $table) {
            $table->increments('member_classification_id');

            /*
             * sacco_members.member_id
             */
            $table->unsignedInteger('member_id');

            /*
             * sacco_member_classifications.classification_id
             */
            $table->unsignedInteger('classification_id');

            /*
             * Optional appointment / membership period.
             */
            $table->date('classification_date_from')->nullable();
            $table->date('classification_date_to')->nullable();

            /*
             * Y = currently assigned
             * N = no longer assigned
             */
            $table->char('classification_member_active', 1)
                ->default('Y');

            $table->text('classification_member_notes')
                ->nullable();

            $table->integer('classification_member_user_id')
                ->nullable();

            $table->timestamp('classification_member_transdate')
                ->useCurrent();

            /*
             * Prevent accidental duplicate assignment of the
             * same role to the same member.
             */
            $table->unique(
                ['member_id', 'classification_id'],
                'sacco_mcm_member_class_uq'
            );

            $table->index(
                'member_id',
                'sacco_mcm_member_idx'
            );

            $table->index(
                'classification_id',
                'sacco_mcm_class_idx'
            );

            $table->index(
                'classification_member_active',
                'sacco_mcm_active_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sacco_member_classification_members');
    }
};
