<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sacco_fosa_types', function (Blueprint $table) {
            $table->id('type_id');
            $table->string('type_name', 50)->unique();
            $table->enum('type_active', ['Y', 'N'])->default('Y');
            $table->enum('type_default', ['Y', 'N'])->default('N');

            // 🔹 Audit fields
            $table->unsignedBigInteger('created_by')->nullable();
            $table->string('created_ip', 45)->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->string('updated_ip', 45)->nullable();

            $table->timestamps();
        });

        // Insert default FOSA type
        DB::table('sacco_fosa_types')->insert([
            'type_name'   => 'FOSA',
            'type_active' => 'Y',
            'type_default'=> 'Y',
            'created_by'  => 000000, // system/admin user
            'created_ip'  => '127.0.0.1',
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('sacco_fosa_types');
    }
};