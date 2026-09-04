<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sacco_mpesa_allocation_priorities')) {
            return;
        }

        Schema::create('sacco_mpesa_allocation_priorities', function (Blueprint $table) {

            /*
            |--------------------------------------------------------------------------
            | Local Row Identity
            |--------------------------------------------------------------------------
            |
            | This is only the primary identifier for THIS table.
            |
            | No foreign-key relationship is created to any other table.
            |
            */
            $table->bigIncrements('priority_id');


            /*
            |--------------------------------------------------------------------------
            | Logical Identity
            |--------------------------------------------------------------------------
            |
            | Examples:
            |
            | CORE:REGISTRATION_FEE
            | CORE:CAPITAL
            | CORE:SHARES
            |
            | LOAN_TYPE:31
            | FOSA_TYPE:6
            | SPECIAL_SAVING_PRODUCT:1
            |
            | This is NOT database-unique.
            | The controller prevents duplicates in code.
            |
            */
            $table->string('priority_key', 190);


            /*
            |--------------------------------------------------------------------------
            | Product Family
            |--------------------------------------------------------------------------
            |
            | Expected values:
            |
            | CORE
            | LOAN_TYPE
            | FOSA_TYPE
            | SPECIAL_SAVING_PRODUCT
            |
            | Kept as VARCHAR rather than ENUM so additional payment families
            | can be added later without changing the database definition.
            |
            */
            $table->string('priority_type', 60);


            /*
            |--------------------------------------------------------------------------
            | Source Information
            |--------------------------------------------------------------------------
            |
            | Purely informational pointers.
            |
            | NO foreign key.
            |
            | Examples:
            |
            | priority_source_table = sacco_loan_types
            | priority_source_id    = 31
            |
            | priority_source_table = sacco_fosa_types
            | priority_source_id    = 6
            |
            */
            $table->string('priority_source_table', 120)->nullable();

            $table->unsignedBigInteger('priority_source_id')->nullable();


            /*
            |--------------------------------------------------------------------------
            | Snapshot Information
            |--------------------------------------------------------------------------
            |
            | The actual source table remains authoritative.
            |
            | These fields are useful if a product is renamed/deactivated later.
            | They also make auditing and administration easier.
            |
            */
            $table->string('priority_label', 190)->nullable();

            $table->string('priority_code', 100)->nullable();


            /*
            |--------------------------------------------------------------------------
            | Ordering
            |--------------------------------------------------------------------------
            |
            | 1 = first
            | 2 = second
            | etc.
            |
            */
            $table->unsignedInteger('priority_order')->default(0);


            /*
            |--------------------------------------------------------------------------
            | Status
            |--------------------------------------------------------------------------
            |
            | Administrative status of the priority row itself.
            |
            | It is NOT necessarily the same as the underlying product status.
            |
            */
            $table->char('priority_active', 1)->default('Y');


            /*
            |--------------------------------------------------------------------------
            | Future Allocation Options
            |--------------------------------------------------------------------------
            |
            | Intentionally flexible.
            |
            | Later we may need settings such as:
            |
            | - maximum allocation
            | - overflow behaviour
            | - settlement policy
            | - target policy
            | - product-specific smart allocation options
            |
            | We are NOT implementing those rules during Stage 1.
            |
            */
            $table->longText('priority_options')->nullable();

            $table->text('priority_notes')->nullable();


            /*
            |--------------------------------------------------------------------------
            | Product Discovery Tracking
            |--------------------------------------------------------------------------
            |
            | Helps us know when the product was first discovered and when
            | it was last seen in its source table.
            |
            */
            $table->timestamp('priority_discovered_at')->nullable();

            $table->timestamp('priority_last_seen_at')->nullable();


            /*
            |--------------------------------------------------------------------------
            | Audit Information
            |--------------------------------------------------------------------------
            */
            $table->unsignedBigInteger('priority_created_by')->nullable();

            $table->string('priority_created_ip', 100)->nullable();

            $table->unsignedBigInteger('priority_updated_by')->nullable();

            $table->string('priority_updated_ip', 100)->nullable();

            $table->timestamps();


            /*
            |--------------------------------------------------------------------------
            | Ordinary Indexes ONLY
            |--------------------------------------------------------------------------
            |
            | No relationship constraints.
            | No unique constraints.
            |
            */
            $table->index('priority_order');

            $table->index('priority_key');

            $table->index('priority_type');

            $table->index('priority_source_id');

            $table->index([
                'priority_source_table',
                'priority_source_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sacco_mpesa_allocation_priorities');
    }
};