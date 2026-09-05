<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Validate Menu Table
        |--------------------------------------------------------------------------
        */

        if (!Schema::hasTable('sacco_menu')) {
            throw new RuntimeException(
                'Cannot add M-PESA Allocation Priorities menu because sacco_menu does not exist.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Find SACCO Admin Parent
        |--------------------------------------------------------------------------
        |
        | Do NOT hard-code menu_id because IDs may differ between SACCOs.
        |
        */

        $adminMenu = DB::table('sacco_menu')
            ->where('menu_key', 'admin')
            ->where('menu_deleted', 'N')
            ->first();

        if (!$adminMenu) {
            throw new RuntimeException(
                'Cannot add M-PESA Allocation Priorities menu because the Sacco Admin menu was not found.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | M-PESA Allocation Priorities
        |--------------------------------------------------------------------------
        |
        | Route:
        | mpesa.allocation.priorities.index
        |
        | Right:
        | mpesa_allocation_priorities
        |
        */

        $menu = [
            'menu_parent_id' => $adminMenu->menu_id,

            'menu_name' => 'M-PESA Allocation Priorities',

            'menu_description' =>
                'Configure the order used when allocating incoming M-PESA payments across SACCO products.',

            'menu_keywords' =>
                'mpesa allocation priorities priority payment allocation smart allocation loans fosa savings special savings products',

            'menu_type' => 'LINK',

            'menu_route_name' =>
                'mpesa.allocation.priorities.index',

            'menu_url' => null,

            'menu_route_parameters' => null,
            'menu_query_parameters' => null,

            'menu_http_method' => 'GET',

            'menu_icon' => null,

            'menu_right_code' =>
                'mpesa_allocation_priorities',

            'menu_scope' => 'OFFICIAL',

            'menu_member_positions' =>
                json_encode([2]),

            'menu_conditions' => null,

            /*
            |--------------------------------------------------------------------------
            | Position
            |--------------------------------------------------------------------------
            |
            | Current Sacco Admin M-PESA entries finish around 70.
            | This therefore follows them at 80.
            |
            */

            'menu_sort_order' => 80,

            'menu_search_weight' => 100,

            'menu_visible' => 'Y',
            'menu_searchable' => 'Y',
            'menu_active' => 'Y',

            'menu_open_new_tab' => 'N',

            'menu_locked' => 'Y',
            'menu_deleted' => 'N',

            'menu_source' => 'MIGRATION',

            'menu_updated_by' => null,
            'menu_ip' => null,
            'menu_deleted_at' => null,

            'updated_at' => now(),
        ];

        /*
        |--------------------------------------------------------------------------
        | Idempotent Insert / Repair
        |--------------------------------------------------------------------------
        |
        | If this menu already exists, update it instead of creating a duplicate.
        |
        */

        $existing = DB::table('sacco_menu')
            ->where(
                'menu_key',
                'admin.mpesa_allocation_priorities'
            )
            ->first();

        if ($existing) {

            DB::table('sacco_menu')
                ->where(
                    'menu_key',
                    'admin.mpesa_allocation_priorities'
                )
                ->update($menu);

            return;
        }

        $menu['menu_key'] =
            'admin.mpesa_allocation_priorities';

        $menu['menu_created_by'] = null;
        $menu['created_at'] = now();

        DB::table('sacco_menu')->insert($menu);
    }


    public function down(): void
    {
        if (!Schema::hasTable('sacco_menu')) {
            return;
        }

        DB::table('sacco_menu')
            ->where(
                'menu_key',
                'admin.mpesa_allocation_priorities'
            )
            ->delete();
    }
};