<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add CRB Reports under:
     *
     * Reports
     * └── General
     *     └── CRB Reports
     *
     * The menu is database-driven. The Blade resolves this row automatically,
     * applies the reports_crb permission, generates the route URL and adds the
     * item to menu search.
     */
    public function up(): void
    {
        if (!Schema::hasTable('sacco_menu')) {
            throw new \RuntimeException(
                'Cannot add the CRB menu because sacco_menu does not exist.'
            );
        }

        /*
         * Never hardcode menu_id = 83.
         *
         * The current database has reports.general as ID 83, but other SACCO
         * installations may have different auto-increment values.
         */
        $generalReports = DB::table('sacco_menu')
            ->where('menu_key', 'reports.general')
            ->where('menu_deleted', 'N')
            ->first();

        if (!$generalReports) {
            throw new \RuntimeException(
                'Cannot add the CRB menu because the Reports > General parent menu was not found.'
            );
        }

        $now = now();

        /*
         * menu_key is unique in sacco_menu.
         *
         * updateOrInsert also makes this safe where the CRB menu may have been
         * added manually before this migration reaches an environment.
         */
        DB::table('sacco_menu')->updateOrInsert(
            [
                'menu_key' => 'reports.general.crb',
            ],
            [
                /*
                 * Hierarchy
                 */
                'menu_parent_id' => $generalReports->menu_id,

                /*
                 * Display/search
                 */
                'menu_name' => 'CRB Reports',

                'menu_description' =>
                    'Generate, validate, review, finalise and manage Credit Reference Bureau reports.',

                'menu_keywords' =>
                    'crb credit bureau credit reference bureau credit information sharing cis '
                    . 'consumer employer account guarantor application daily payment microfinance '
                    . 'CE GI CA DP MF regulatory reporting',

                /*
                 * Link
                 */
                'menu_type' => 'LINK',
                'menu_route_name' => 'reports.crb.index',
                'menu_url' => null,
                'menu_route_parameters' => null,
                'menu_query_parameters' => null,
                'menu_http_method' => 'GET',

                /*
                 * Child links in General currently do not require their own
                 * icon, so keep this consistent.
                 */
                'menu_icon' => null,

                /*
                 * Security
                 *
                 * This is deliberately the SAME right already used by the CRB
                 * report routes.
                 */
                'menu_right_code' => 'reports_crb',

                /*
                 * Staff/official menu only.
                 */
                'menu_scope' => 'OFFICIAL',
                'menu_member_positions' => json_encode([2]),
                'menu_conditions' => null,

                /*
                 * Existing visible General reports use:
                 * 10 mPesa
                 * 20 Consolidated
                 * 30 Financial Position
                 * 40 Registration Fees
                 * 50 Insurance
                 *
                 * Put CRB immediately after those at 55.
                 */
                'menu_sort_order' => 55,

                /*
                 * Give CRB good menu-search ranking.
                 */
                'menu_search_weight' => 150,

                /*
                 * Availability
                 */
                'menu_visible' => 'Y',
                'menu_searchable' => 'Y',
                'menu_active' => 'Y',
                'menu_open_new_tab' => 'N',
                'menu_locked' => 'Y',
                'menu_deleted' => 'N',

                /*
                 * Audit/source
                 */
                'menu_source' => 'MIGRATION',
                'menu_created_by' => null,
                'menu_updated_by' => null,
                'menu_ip' => null,
                'menu_deleted_at' => null,
                'updated_at' => $now,
                'created_at' => $now,
            ]
        );
    }

    /**
     * Remove only the CRB menu entry.
     *
     * The Reports and General parent menus remain untouched.
     */
    public function down(): void
    {
        if (!Schema::hasTable('sacco_menu')) {
            return;
        }

        DB::table('sacco_menu')
            ->where('menu_key', 'reports.general.crb')
            ->delete();
    }
};