<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            // Add 'exported' column if it doesn't exist
            if (!Schema::hasColumn('sacco_members_new_applications', 'exported')) {
                $table->enum('exported', ['Y', 'N'])
                    ->default('N')
                    ->after('email_key_unique');
            }

            // Add 'exported_on' column if it doesn't exist
            if (!Schema::hasColumn('sacco_members_new_applications', 'exported_on')) {
                $table->timestamp('exported_on')
                    ->nullable()
                    ->after('exported');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sacco_members_new_applications', function (Blueprint $table) {
            if (Schema::hasColumn('sacco_members_new_applications', 'exported_on')) {
                $table->dropColumn('exported_on');
            }
            if (Schema::hasColumn('sacco_members_new_applications', 'exported')) {
                $table->dropColumn('exported');
            }
        });
    }
};
