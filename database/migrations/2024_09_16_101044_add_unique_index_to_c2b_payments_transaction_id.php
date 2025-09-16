<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
public function up(): void
{
    Schema::table('c2b_payments', function (Blueprint $table) {
        $table->unique('transaction_id', 'c2b_transaction_id_unique');
    });
}

public function down(): void
{
    Schema::table('c2b_payments', function (Blueprint $table) {
        $table->dropUnique('c2b_transaction_id_unique');
    });
} 
};
