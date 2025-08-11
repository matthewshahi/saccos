<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sacco_matatus_collections', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('coll_operator_id')->nullable()->index('idx_coll_operator');
            $table->unsignedBigInteger('coll_vehicle_id')->nullable()->index('idx_coll_vehicle');
            $table->unsignedBigInteger('coll_entered_by')->nullable()->index('idx_coll_entered_by');

            $table->date('coll_date')->index('idx_coll_date');

            $table->decimal('coll_amount', 12, 2)->default(0);
            $table->string('coll_reference')->nullable()->index('idx_coll_reference');
            $table->text('coll_notes')->nullable();

            $table->enum('coll_type', [
                'daily_target', 'share', 'loan_repayment', 'penalty', 'adjustment', 'fine', 'maintenance_fee', 'fuel_reimbursement', 'deposit', 'other'
            ])->default('daily_target')->index('idx_coll_type');

            $table->enum('coll_mode', [
                'cash', 'mpesa', 'airtel', 'bank', 'cheque', 'adjustment', 'other'
            ])->default('cash')->index('idx_coll_mode');

            $table->timestamps();

            // Additional indexes
            $table->index(['coll_operator_id', 'coll_vehicle_id'], 'idx_operator_vehicle');
            $table->index(['coll_type', 'coll_mode'], 'idx_type_mode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sacco_matatus_collections');
    }
};