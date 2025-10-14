<?php



use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('sacco_members', 'member_last_mobile_login')) {
            Schema::table('sacco_members', function (Blueprint $table) {
                $table->timestamp('member_last_mobile_login')->nullable()->after('member_password_last_changed');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sacco_members', 'member_last_mobile_login')) {
            Schema::table('sacco_members', function (Blueprint $table) {
                $table->dropColumn('member_last_mobile_login');
            });
        }
    }
};
