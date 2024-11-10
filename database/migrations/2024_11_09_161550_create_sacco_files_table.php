<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSaccoFilesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('sacco_files', function (Blueprint $table) {
            $table->id();
            $table->string('file_description', 255);
            $table->enum('file_accessibility', ['public', 'admin'])->default('admin');
            $table->date('file_start_date');
            $table->date('file_end_date')->default('9999-12-31');
            $table->string('file_path');
            $table->integer('file_uploaded_by'); // User ID without foreign key constraint
            $table->timestamp('file_uploaded_at')->useCurrent();
            $table->enum('file_deleted', ['Y', 'N'])->default('N'); // Indicates if file is deleted
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('sacco_files');
    }
}