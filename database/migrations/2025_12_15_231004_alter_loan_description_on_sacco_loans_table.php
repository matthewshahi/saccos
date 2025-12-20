<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sacco_loans', function (Blueprint $table) {
            $table->text('loan_description')->change();
        });
    }

    public function down(): void
    {
        Schema::table('sacco_loans', function (Blueprint $table) {
            // revert only if you are sure this was previously safe
            $table->string('loan_description', 255)->change();
        });
    }
};
