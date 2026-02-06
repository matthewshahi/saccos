<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sacco_fosa_transaction_type_changes', function (Blueprint $table) {
            $table->bigIncrements('change_id');

            // Link to the transaction
            $table->unsignedBigInteger('fosa_id');

            // From/To
            $table->unsignedBigInteger('from_type_id')->nullable();
            $table->unsignedBigInteger('to_type_id');

            // Narratives
            $table->string('change_reason', 255)->nullable();  // user narrative
            $table->text('change_notes')->nullable();          // optional longer notes

            // Audit
            $table->unsignedBigInteger('changed_by')->nullable(); // users.id
            $table->string('changed_ip', 64)->nullable();
            $table->timestamp('changed_at')->useCurrent();

            // Indexes (no foreign keys, to match your style)
            $table->index('fosa_id');
            $table->index('from_type_id');
            $table->index('to_type_id');
            $table->index('changed_by');
            $table->index('changed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sacco_fosa_transaction_type_changes');
    }
};
