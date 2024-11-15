<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSaccoMembersNewApplicationsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sacco_members_new_applications', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email')->unique(); // Ensure no duplicate emails
            $table->string('phone')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('national_id')->nullable(); // National ID or equivalent identification
            $table->string('physical_location')->nullable(); // Address, location, or country
            $table->string('occupation')->nullable();
            $table->enum('membership_type', ['Regular', 'Associate'])->default('Regular'); // Optional membership type
            $table->text('notes')->nullable(); // Additional notes for admin
            $table->boolean('is_reviewed')->default(false); // Indicates if application has been reviewed by admin
            $table->timestamps(); // Created and updated timestamps
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sacco_members_new_applications');
    }
}