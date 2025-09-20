<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE c2b_payments MODIFY transaction_type VARCHAR(255) NULL");
        DB::statement("ALTER TABLE c2b_payments MODIFY business_shortcode VARCHAR(255) NULL");
        DB::statement("ALTER TABLE c2b_payments MODIFY transaction_amount DECIMAL(20,2) NOT NULL");
        DB::statement("ALTER TABLE c2b_payments MODIFY org_account_balance DECIMAL(20,2) NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE c2b_payments MODIFY transaction_type VARCHAR(255) NOT NULL");
        DB::statement("ALTER TABLE c2b_payments MODIFY business_shortcode VARCHAR(255) NOT NULL");
        DB::statement("ALTER TABLE c2b_payments MODIFY transaction_amount DECIMAL(10,2) NOT NULL");
        DB::statement("ALTER TABLE c2b_payments MODIFY org_account_balance DECIMAL(15,2) NULL");
    }
};