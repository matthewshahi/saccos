<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class KasMemberImportController extends Controller
{
    protected $companyMap = [];
    protected $departmentMap = [];
    protected $memberByAdm = [];
    protected $memberByName = [];
    protected $openBalanceSet = [];

    public function __construct()
    {
        // $this->middleware('auth');
    }

    /**
     * Dashboard + summary
     */
    public function index()
    {
        $stats = [
            'staging_count'    => DB::table('kass_staging_contributions')->count(),
            'members_count'    => DB::table('sacco_members')->count(),
            'companies_count'  => DB::table('sacco_company')->count(),
            'departments_count' => DB::table('sacco_department')->count(),
        ];

        $summary = session('summary');

        return view('kasmember.index', compact('stats', 'summary'));
    }

    /**
     * Run full import: companies + departments + members + contributions
     */
    public function run(Request $request)
    {
        $summary = [
            'rows_processed'    => 0,
            'companies_created' => 0,
            'departments_created' => 0,
            'members_created'   => 0,
            'members_matched_adm'   => 0,
            'members_matched_name'  => 0,
            'shares_inserted'   => 0,
            'capital_inserted'  => 0,
            'fosa_inserted'     => 0,
            'skipped_no_name'   => 0,
            'skipped_no_company' => 0,
            // 🔥 ADD THESE:
            'openbal_inserted'  => 0,
            'openbal_skipped'   => 0,

        ];

        $this->openBalanceSet = [];

        // Process staging in chunks for memory safety
        DB::table('kass_staging_contributions')
            // ->whereRaw('UPPER(TRIM(raw_name)) = ?', ['JOAN JELAGATT'])
            ->orderBy('id')
            ->chunk(500, function ($rows) use (&$summary) {

                foreach ($rows as $row) {

                    // logger()->info('JOAN | STAGING ROW', [
                    //     'staging_id' => $row->id,
                    //     'raw_name'   => $row->raw_name,
                    //     'adm_no'     => $row->adm_no,
                    //     'company'    => $row->company,
                    //     'year'       => $row->year,
                    //     'month'      => $row->month,
                    //     'type'       => $row->raw_type,
                    //     'amount'     => $row->amount,
                    // ]);


                    $summary['rows_processed']++;

                    // 1) Clean & validate company
                    $cleanCompany = $this->cleanName($row->company ?? '');
                    if ($cleanCompany === '') {
                        $summary['skipped_no_company']++;
                        continue;
                    }

                    $companyId = $this->resolveCompany($cleanCompany, $summary);
                    $deptId    = $this->resolveDepartment($companyId, $cleanCompany, $summary);

                    // 2) Clean & validate member name
                    $cleanName = $this->cleanName($row->raw_name ?? '');
                    if ($cleanName === '') {
                        $summary['skipped_no_name']++;
                        continue;
                    }



                    // 3) Resolve / create member
                    $memberId = $this->resolveMember($row, $cleanName, $deptId, $summary);

                    if (!$memberId) {
                        // In theory should not happen; we always create
                        continue;
                    }

                    $this->updateMemberDeptIfChanged($memberId, $cleanCompany);

                    // Update staging with matched member
                    DB::table('kass_staging_contributions')
                        ->where('id', $row->id)
                        ->update(['matched_member_id' => $memberId]);

                    // 4) Insert contribution
                    $this->insertContribution($row, $memberId, $summary);
                }
            });

        // Optional: you can recalc member totals here in future

        return redirect()
            ->route('kasmember.index')
            ->with('summary', $summary);
    }

    /* ============================================================
     *  COMPANY + DEPARTMENT HELPERS
     * ============================================================
     */

    protected function resolveCompany(string $companyName, array &$summary)
    {
        if (isset($this->companyMap[$companyName])) {
            return $this->companyMap[$companyName];
        }

        // Try find existing
        $existing = DB::table('sacco_company')
            ->where('company_name', $companyName)
            ->first();

        if ($existing) {
            $this->companyMap[$companyName] = $existing->company_id;
            return $existing->company_id;
        }

        // Create new
        $id = DB::table('sacco_company')->insertGetId([
            'company_name'       => $companyName,
            'company_details'    => null,
            'company_account'    => null,
            'company_user_id'    => 1,
            'company_transdate'  => Carbon::now()->toDateTimeString(),
            'company_ip'         => 'MIGRATION',
            'company_deleted'    => 'N',
            'company_deleted_by' => null,
            'company_deleted_on' => null,
            'company_deleted_ip' => null,
        ]);

        $this->companyMap[$companyName] = $id;
        $summary['companies_created']++;

        return $id;
    }

    protected function resolveDepartment(int $companyId, string $companyName, array &$summary)
    {
        if (isset($this->departmentMap[$companyId])) {
            return $this->departmentMap[$companyId];
        }

        // Try existing dept for this company
        $existing = DB::table('sacco_department')
            ->where('department_company_id', $companyId)
            ->orderBy('department_id')
            ->first();

        if ($existing) {
            $this->departmentMap[$companyId] = $existing->department_id;
            return $existing->department_id;
        }

        // Create default department same as company name
        $deptId = DB::table('sacco_department')->insertGetId([
            'department_name'        => $companyName,
            'department_company_id'  => $companyId,
            'department_user_id'     => 1,
            'department_transdate'   => Carbon::now()->toDateTimeString(),
            'department_ip'          => 'MIGRATION',
            'department_deleted'     => 'N',
            'department_deleted_by'  => null,
            'department_deleted_on'  => null,
            'department_deleted_ip'  => null,
        ]);

        $this->departmentMap[$companyId] = $deptId;
        $summary['departments_created']++;

        return $deptId;
    }

    /* ============================================================
     *  MEMBER RESOLUTION + CREATION
     * ============================================================
     */

    protected function resolveMember($row, string $cleanName, int $deptId, array &$summary)
    {
        $adm = trim((string)($row->adm_no ?? ''));
        if ($adm === '') {
            $adm = null;
        }



        // 1) Match by ADM first
        if ($adm !== null) {
            if (isset($this->memberByAdm[$adm])) {
                return $this->memberByAdm[$adm];
            }

            $existingByAdm = DB::table('sacco_members')
                ->where('member_sacco_id', $adm)
                ->first();

            if ($existingByAdm) {

                // logger()->info('JOAN | MATCHED BY ADM', [
                //     'member_id' => $existingByAdm->member_id,
                //     'adm_no'    => $adm,
                //     'company'   => $row->company,
                // ]);

                $this->memberByAdm[$adm] = $existingByAdm->member_id;
                $this->memberByName[strtolower($cleanName)] = $existingByAdm->member_id;
                // $this->updateMemberDeptIfChanged($existingByAdm->member_id, $deptId);
                $this->updateMemberDeptIfChanged($existingByAdm->member_id, $row->company);

                $summary['members_matched_adm']++;
                return $existingByAdm->member_id;
            }
        }

        // 2) If no ADM match, try by cleaned name
        $nameKey = strtolower($cleanName);
        if (isset($this->memberByName[$nameKey])) {

            // logger()->info('JOAN | MATCHED BY NAME CACHE', [
            //     'member_id' => $memberId,
            //     'company'   => $row->company,
            // ]);

            $memberId = $this->memberByName[$nameKey];

            // If ADM appears now and member has no ADM, update it
            if ($adm !== null) {
                $this->updateMemberAdmIfEmpty($memberId, $adm);
                $this->memberByAdm[$adm] = $memberId;
            }

            // $this->updateMemberDeptIfChanged($memberId, $deptId);
            $this->updateMemberDeptIfChanged($memberId, $row->company);

            $summary['members_matched_name']++;
            return $memberId;
        }

        $memberId = $this->findMemberByNameDb($cleanName);

        if ($memberId) {

            // logger()->info('JOAN | MATCHED BY NAME DB', [
            //     'member_id' => $memberId,
            //     'company'   => $row->company,
            // ]);

            $this->memberByName[$nameKey] = $memberId;

            if ($adm !== null) {
                $this->updateMemberAdmIfEmpty($memberId, $adm);
                $this->memberByAdm[$adm] = $memberId;
            }
            //  $this->updateMemberDeptIfChanged($memberId, $deptId);


            $this->updateMemberDeptIfChanged($memberId, $row->company);


            $summary['members_matched_name']++;
            return $memberId;
        }

        // 3) If still not found → create new member
        $memberId = $this->createMember($row, $cleanName, $deptId, $adm, $summary);

        // Cache both
        $this->memberByName[$nameKey] = $memberId;
        if ($adm !== null) {
            $this->memberByAdm[$adm] = $memberId;
        }

        return $memberId;
    }

    protected function updateMemberAdmIfEmpty(int $memberId, string $adm)
    {
        $member = DB::table('sacco_members')
            ->where('member_id', $memberId)
            ->first();

        if ($member && (empty($member->member_sacco_id) || $member->member_sacco_id === null)) {
            DB::table('sacco_members')
                ->where('member_id', $memberId)
                ->update(['member_sacco_id' => $adm]);
        }
    }

    protected function createMember($row, string $cleanName, int $deptId, ?string $adm, array &$summary)
    {
        $email = $this->generateEmail($cleanName);

        // Derive a join date from year/month if possible

        // YEAR NORMALIZATION
        $year = trim((string)$row->year);
        if (!preg_match('/^\d{4}$/', $year)) {
            $joinDate = null;
        } else {
            $year = (int)$year;

            // MONTH NORMALIZATION
            $rawMonth = strtoupper(trim((string)$row->month));
            $rawMonth = preg_replace('/[^A-Z0-9]/', '', $rawMonth);

            $monthMap = [
                'JAN' => 1,
                'FEB' => 2,
                'MAR' => 3,
                'APR' => 4,
                'MAY' => 5,
                'JUN' => 6,
                'JUL' => 7,
                'AUG' => 8,
                'SEP' => 9,
                'SEPT' => 9,
                'OCT' => 10,
                'NOV' => 11,
                'DEC' => 12
            ];

            if (is_numeric($rawMonth)) {
                $month = (int)$rawMonth;
            } elseif (isset($monthMap[$rawMonth])) {
                $month = $monthMap[$rawMonth];
            } else {
                $month = null;
            }

            if ($month === null) {
                $joinDate = null;
            } else {
                $joinDate = Carbon::create($year, $month, 1)->toDateString();
            }
        }


        $memberId = DB::table('sacco_members')->insertGetId([
            'member_name'                  => $cleanName,
            'member_date_joined'           => $joinDate,
            'member_dept'                  => $deptId,
            'member_sacco_id'              => $adm,          // ADM number
            'member_national_id'           => null,
            'member_postal_address'        => null,
            'member_phone_no'              => null,
            'member_gender'                => 'M',
            'member_email'                 => $email,
            'member_share_contr_monthly'   => 0,
            'member_fosa_contr_monthly'    => 0,
            'member_total_share'           => 0,
            'member_total_fosa'            => 0,
            'member_total_loan'            => 0,
            'member_total_share_capital'   => 0,
            'member_tied_shares'           => 0,
            'member_tied_shares_self'      => 0,
            'member_active'                => 'Y',
            'member_date_dactivated'       => null,
            'member_deleted'               => 'N',
            'member_is_junior'             => 0,
            'member_guardian_id'           => null,
            'member_deleted_by'            => null,
            'member_deleted_ip'            => null,
            'member_deleted_on'            => null,
            'member_position'              => 1,
            'member_image'                 => null,
            'member_signature'             => null,
            'member_id_copy_front'         => null,
            'member_id_copy_back'          => null,
            'member_payslips_bank_statements' => null,
            'member_password'              => null,
            'member_password_last_changed' => null,
            'member_password_changed_by'   => null,
            'member_ip'                    => 'MIGRATION',
            'member_transdate'             => Carbon::now()->toDateTimeString(),
            'member_user_id'               => null,
            'member_last_mobile_login'     => null,
            'member_kra_pin'               => null,
            'member_dob'                   => null,
            'bank_name'                    => null,
            'bank_branch'                  => null,
            'bank_account_number'          => null,
        ]);

        $summary['members_created']++;

        return $memberId;

//         logger()->info('JOAN | MEMBER CREATED', [
//     'member_id' => $memberId,
//     'dept_id'   => $deptId,
//     'company'   => $row->company,
// ]);
    }

    // protected function findMemberByNameDb(string $cleanName)
    // {
    //     $tokens = $this->tokens($cleanName);
    //     if (empty($tokens)) {
    //         return null;
    //     }

    //     $likeEscape = function ($s) {
    //         return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);
    //     };

    //     // 1) All tokens AND
    //     $q = DB::table('sacco_members');
    //     foreach ($tokens as $t) {
    //         $q->where('member_name', 'LIKE', '%' . $likeEscape($t) . '%');
    //     }
    //     $m = $q->select('member_id')->first();
    //     if ($m) {
    //         return $m->member_id;
    //     }

    //     // 2) Any two-token AND (for 3+ parts)
    //     if (count($tokens) >= 3) {
    //         foreach ($this->twoCombos($tokens) as $pair) {
    //             [$a, $b] = explode(' ', $pair, 2);
    //             $q2 = DB::table('sacco_members')
    //                 ->where('member_name', 'LIKE', '%' . $likeEscape($a) . '%')
    //                 ->where('member_name', 'LIKE', '%' . $likeEscape($b) . '%');
    //             $m2 = $q2->select('member_id')->first();
    //             if ($m2) {
    //                 return $m2->member_id;
    //             }
    //         }
    //     }

    //     return null;
    // }

    protected function findMemberByNameDb(string $cleanName): ?int
    {
        $parts = $this->nameParts($cleanName);
        $tokens = $parts['tokens'];

        if (empty($tokens)) {
            return null;
        }

        $esc = fn($s) => str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $s);

        /**
         * ============================================================
         * 1. EXACT CANONICAL MATCH (STRONGEST)
         * ============================================================
         */
        $canonical = $this->canonicalName($cleanName);

        $exact = DB::table('sacco_members')
            ->select('member_id')
            ->whereRaw(
                "LOWER(
                TRIM(
                    REPLACE(
                        REGEXP_REPLACE(member_name, '[^A-Za-z ]', ''),
                        '  ', ' '
                    )
                )
            ) = ?",
                [$canonical]
            )
            ->first();

        if ($exact) {
            return $exact->member_id;
        }

        /**
         * ============================================================
         * 2. FIRST + LAST NAME MATCH (VERY STRONG)
         * ============================================================
         */
        if ($parts['first'] && $parts['last']) {
            $fl = DB::table('sacco_members')
                ->where('member_name', 'LIKE', '%' . $esc($parts['first']) . '%')
                ->where('member_name', 'LIKE', '%' . $esc($parts['last']) . '%')
                ->select('member_id')
                ->first();

            if ($fl) {
                return $fl->member_id;
            }
        }

        /**
         * ============================================================
         * 3. ANY TWO TOKEN COMBINATION (MEDIUM)
         * ============================================================
         */
        if (count($tokens) >= 2) {
            foreach ($this->twoCombos($tokens) as $pair) {
                [$a, $b] = explode(' ', $pair);

                $m = DB::table('sacco_members')
                    ->where('member_name', 'LIKE', '%' . $esc($a) . '%')
                    ->where('member_name', 'LIKE', '%' . $esc($b) . '%')
                    ->select('member_id')
                    ->first();

                if ($m) {
                    return $m->member_id;
                }
            }
        }

        /**
         * ============================================================
         * 4. ALL TOKENS AND (LAST RESORT)
         * ============================================================
         */
        $q = DB::table('sacco_members');
        foreach ($tokens as $t) {
            $q->where('member_name', 'LIKE', '%' . $esc($t) . '%');
        }

        $fallback = $q->select('member_id')->first();

        return $fallback?->member_id;
    }


    /* ============================================================
     *  CONTRIBUTION INSERT LOGIC
     * ============================================================
     */

    protected function insertContribution($row, int $memberId, array &$summary)
    {
        if (empty($row->year) || empty($row->month)) {
            $summary['skipped_no_period'] = ($summary['skipped_no_period'] ?? 0) + 1;
            return;
        }

        $amount = (float)($row->amount ?? 0);
        if ($amount == 0) return;

        // Normalize type
        $type = strtoupper(trim((string)$row->raw_type));
        // $normalized = preg_replace('/\s+/', '', $type);

        // --------- YEAR NORMALIZATION ----------
        $year = trim((string)$row->year);
        if (!preg_match('/^\d{4}$/', $year)) {
            $summary['skipped_no_period']++;
            return;
        }
        $year = (int)$year;

        // --------- MONTH NORMALIZATION ----------
        $rawMonth = strtoupper(preg_replace('/[^A-Z0-9]/', '', (string)$row->month));
        $monthMap = [
            'JAN' => 1,
            'FEB' => 2,
            'MAR' => 3,
            'APR' => 4,
            'MAY' => 5,
            'JUN' => 6,
            'JUL' => 7,
            'AUG' => 8,
            'SEP' => 9,
            'SEPT' => 9,
            'OCT' => 10,
            'NOV' => 11,
            'DEC' => 12
        ];

        if (is_numeric($rawMonth)) {
            $month = (int)$rawMonth;
        } elseif (isset($monthMap[$rawMonth])) {
            $month = $monthMap[$rawMonth];
        } else {
            $summary['skipped_no_period']++;
            return;
        }

        if ($month < 1 || $month > 12) {
            $summary['skipped_no_period']++;
            return;
        }

        // Final period
        $period   = sprintf('%04d%02d', $year, $month);
        $datePaid = Carbon::create($year, $month, 1)->endOfMonth()->toDateString();


        // Description
        $description = trim((string)$row->notes ?? '');
        if ($description === '') {
            $description = $row->source_file ?? 'Imported';
        }

        /* ============================================================
     *  1) CAPITAL → sacco_capital_shares
     * ============================================================ */
        $type = strtoupper(trim((string)$row->raw_type));
        $normalized = preg_replace('/[^A-Z]/', '', $type);

        // CAPITAL RULE:
        // - Anything containing CAPITAL
        // - OR anything containing MEM (e.g. MEM, MEMBER, MEMBERSHIP)
        if (
            str_contains($normalized, 'CAPITAL')

        ) {
            DB::table('sacco_capital_shares')->insert([
                'share_capitalmember_id'      => $memberId,
                'share_capitalamount_paying'  => abs($amount),
                'share_capitalpaid_by'        => 'imported',
                'share_capitalperiod'         => $period,
                'share_capitaldescription'    => $description,
                'share_capitaldoc_no'         => 'imported',
                'share_capitaldate_paid'      => $datePaid,
                'share_capitalby'             => 1,
                'share_capitalip'             => 'MIGRATION',
                'share_capitaltransdate'      => $datePaid,
                'share_capitalend_month_proc' => 'N',
            ]);

            $summary['capital_inserted']++;
            return;
        }

        /* ============================================================
     *  2) SHARES → ONLY CR or DR
     * ============================================================ */

        // DR = negative shares
        if ($normalized === 'DR') {

            DB::table('sacco_shares')->insert([
                'share_member_id'      => $memberId,
                'share_amount_paying'  => -abs($amount),
                'share_paid_by'        => 'imported',
                'share_period'         => $period,
                'share_description'    => 'DR - ' . $description,
                'share_doc_no'         => 'imported',
                'share_date_paid'      => $datePaid,
                'share_by'             => 1,
                'share_ip'             => 'MIGRATION',
                'share_transdate'      => $datePaid,
                'share_end_month_proc' => 'N',
            ]);

            $summary['shares_inserted']++;
            return;
        }

        // CR = positive shares
        if ($normalized === 'CR') {

            DB::table('sacco_shares')->insert([
                'share_member_id'      => $memberId,
                'share_amount_paying'  => abs($amount),
                'share_paid_by'        => 'imported',
                'share_period'         => $period,
                'share_description'    => 'CR - ' . $description,
                'share_doc_no'         => 'imported',
                'share_date_paid'      => $datePaid,
                'share_by'             => 1,
                'share_ip'             => 'MIGRATION',
                'share_transdate'      => $datePaid,
                'share_end_month_proc' => 'N',
            ]);

            $summary['shares_inserted']++;
            return;
        }

        /* ============================================================
     *  IMPORTANT CHANGE:
     *  MEMB IS NOW FOSA (NOT SHARES)
     * ============================================================ */

        // if (str_contains($normalized, 'MEMB')) {
        //     // goes to FOSA
        //      $fosaTypeId = $this->resolveFosaType($type);
        //     DB::table('sacco_fosas')->insert([
        //         'fosa_member_id'      => $memberId,
        //         'fosa_type_id'        => $fosaTypeId,          // <<< REQUIRED
        //         'fosa_amount_paying'  => abs($amount),
        //         'fosa_paid_by'        => 'imported',
        //         'fosa_period'         => $period,
        //         'fosa_description'    => 'MEMBERSHIP - ' . $description,
        //         'fosa_doc_no'         => 'imported',
        //         'fosa_date_paid'      => $datePaid,
        //         'fosa_by'             => 1,
        //         'fosa_ip'             => 'MIGRATION',
        //         'fosa_transdate'      => $datePaid,
        //         'fosa_end_month_proc' => 'N',
        //     ]);

        //     $summary['fosa_inserted']++;
        //     return;
        // }

        /* ============================================================
     *  3) IGNORE OPEN BAL (do NOT insert)
     * ============================================================ */
        if (str_contains($normalized, 'OPENBAL')) {
            return;
        }

        /* ============================================================
     *  4) EVERYTHING ELSE → FOSA
     * ============================================================ */

        $fosaTypeId = $this->resolveFosaType($type);
        DB::table('sacco_fosas')->insert([
            'fosa_member_id'      => $memberId,
            'fosa_type_id'        => $fosaTypeId,
            'fosa_amount_paying'  => abs($amount),
            'fosa_paid_by'        => 'imported',
            'fosa_period'         => $period,
            'fosa_description'    => strtoupper($type) . ' - ' . $description,
            'fosa_doc_no'         => 'imported',
            'fosa_date_paid'      => $datePaid,
            'fosa_by'             => 1,
            'fosa_ip'             => 'MIGRATION',
            'fosa_transdate'      => $datePaid,
            'fosa_end_month_proc' => 'N',
        ]);

        $summary['fosa_inserted']++;
    }


    /**
     * Resolve or Create FOSA Type based on raw type tag.
     *
     * @param string $typeRaw  (e.g. "MEMB", "SAVINGS", "FINE", "OTHER")
     * @return int type_id
     */
    /**
     * Resolve or create a FOSA Type with strict 2-character prefixes.
     */
    protected function resolveFosaType(string $typeRaw): int
    {
        $clean = strtoupper(trim($typeRaw));



        // ====================================================
        // WELFARE NORMALISATION (MERGE VARIANTS)
        // ====================================================
        if (
            str_contains($clean, 'WELFARE') ||
            str_contains($clean, 'WEL/REG') ||
            str_contains($clean, 'NEW WEL') ||
            $clean === 'WEL'
        ) {
            $clean = 'WELFARE';
        }

        if ($clean === '') {
            $clean = 'OTHER';
        }


        // 1. Check if type_name exists
        $existing = DB::table('sacco_fosa_types')
            ->where('type_name', $clean)
            ->first();

        if ($existing) {
            return $existing->type_id;
        }

        // -------------------------------------------------------
        // 2. Build a 2-character default prefix
        // -------------------------------------------------------
        $cleanLetters = preg_replace('/[^A-Z]/', '', $clean);

        if (strlen($cleanLetters) >= 2) {
            $prefix = substr($cleanLetters, 0, 2); // first two letters
        } elseif (strlen($cleanLetters) === 1) {
            $prefix = $cleanLetters . 'X'; // pad to 2 chars, e.g., "M" -> "MX"
        } else {
            $prefix = 'OT'; // fallback for weird names
        }

        // Ensure prefix is ALWAYS 2 chars
        $prefix = substr($prefix, 0, 2);

        $originalPrefix = $prefix;

        // -------------------------------------------------------
        // 3. Ensure uniqueness by generating alternate 2-char codes
        // -------------------------------------------------------
        $i = 1;

        while (
            DB::table('sacco_fosa_types')
            ->where('type_prefix', $prefix)
            ->exists()
        ) {
            // Generate alternatives like M1, M2 … M9, A0–Z9 etc.
            // Convert $i into a 2-char code safely
            if ($i < 10) {
                $prefix = $originalPrefix[0] . $i;   // e.g., M + 1 -> M1
            } else {
                // cycle through alphanumerics for 2-char codes
                $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
                $pos = $i % strlen($chars);
                $prefix = $originalPrefix[0] . $chars[$pos];
            }

            $i++;
        }

        // -------------------------------------------------------
        // 4. Create new type
        // -------------------------------------------------------
        $typeId = DB::table('sacco_fosa_types')->insertGetId([
            'type_name'     => $clean,
            'type_prefix'   => $prefix,
            'type_active'   => 'Y',
            'type_default'  => 'N',
            'created_by'    => 1,
            'created_ip'    => 'MIGRATION',
            'created_at'    => Carbon::now(),
        ]);

        return $typeId;
    }


    /* ============================================================
     *  STRING / NAME HELPERS
     * ============================================================
     */

    // Clean and Title Case a name/company
    protected function cleanName(string $name): string
    {
        $name = trim(preg_replace('/\s+/', ' ', $name));
        if ($name === '') {
            return '';
        }

        // Title Case but keep all caps words (e.g. acronyms) as-is if needed
        // $name = Str::lower($name);
        // $name = mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');

        $name = strtoupper(trim(preg_replace('/\s+/', ' ', $name)));


        return $name;
    }

    protected function generateEmail(string $cleanName): string
    {
        $parts = explode(' ', $cleanName);
        $parts = array_values(array_filter($parts, fn($p) => $p !== ''));

        $first = Str::lower($parts[0] ?? 'member');
        $last  = Str::lower($parts[count($parts) - 1] ?? 'kass');

        $base  = $first . '.' . $last;
        $email = $base . '@kassacco.com';

        // Basic uniqueness guard in current run (not global DB check)
        static $used = [];
        if (!isset($used[$email])) {
            $used[$email] = 1;
            return $email;
        }

        $i = 2;
        while (isset($used[$base . $i . '@kassacco.com'])) {
            $i++;
        }
        $email = $base . $i . '@kassacco.com';
        $used[$email] = 1;

        return $email;
    }

    protected function tokens(string $name): array
    {
        $name = Str::lower($name);
        $name = preg_replace("/[^a-z'\s]/", ' ', $name);
        $name = trim(preg_replace('/\s+/', ' ', $name));
        $parts = explode(' ', $name);
        return array_values(array_filter($parts, fn($t) => $t !== ''));
    }

    protected function twoCombos(array $parts): array
    {
        $out = [];
        $c = count($parts);
        for ($i = 0; $i < $c; $i++) {
            for ($j = $i + 1; $j < $c; $j++) {
                $pair = [$parts[$i], $parts[$j]];
                sort($pair);
                $out[] = implode(' ', $pair);
            }
        }
        return array_values(array_unique($out));
    }
   protected function updateMemberDeptIfChanged(int $memberId, ?string $companyName): void
{
    /*
     * ------------------------------------------------------------
     * 0. Resolve member name once (for scoped debugging)
     * ------------------------------------------------------------
     */
    $memberName = DB::table('sacco_members')
        ->where('member_id', $memberId)
        ->value('member_name');

    $debug = ($memberName === 'JOAN JELAGATT');

    if ($debug) {
        // logger()->info('JOAN | UPDATE DEPT CALLED', [
        //     'member_id'        => $memberId,
        //     'incoming_company' => $companyName,
        // ]);
    }

    /*
     * ------------------------------------------------------------
     * 1. Normalize company name (STRING ONLY)
     * ------------------------------------------------------------
     */
    $companyName = strtoupper(trim((string) $companyName));

    if ($debug) {
        // logger()->info('JOAN | COMPANY NORMALIZED', [
        //     'normalized_company' => $companyName,
        // ]);
    }

    /*
     * ------------------------------------------------------------
     * 2. Resolve company
     * ------------------------------------------------------------
     */
    if ($companyName === '') {
        // Fallback to default company (ID = 1)
        $companyId = 1;

        $deptName = DB::table('sacco_company')
            ->where('company_id', 1)
            ->value('company_name') ?? 'DEFAULT';

        if ($debug) {
            // logger()->info('JOAN | USING DEFAULT COMPANY', [
            //     'company_id' => $companyId,
            //     'dept_name'  => $deptName,
            // ]);
        }
    } else {
        $deptName = $companyName;

        $companyId = DB::table('sacco_company')
            ->where('company_name', $companyName)
            ->value('company_id');

        if (!$companyId) {
            $companyId = DB::table('sacco_company')->insertGetId([
                'company_name'      => $companyName,
                'company_transdate' => now(),
                'company_ip'        => 'MIGRATION',
                'company_deleted'   => 'N',
            ]);

            if ($debug) {
                // logger()->info('JOAN | COMPANY CREATED', [
                //     'company_id'   => $companyId,
                //     'company_name' => $companyName,
                // ]);
            }
        } else {
            if ($debug) {
                // logger()->info('JOAN | COMPANY FOUND', [
                //     'company_id'   => $companyId,
                //     'company_name' => $companyName,
                // ]);
            }
        }
    }

    /*
     * ------------------------------------------------------------
     * 3. Resolve department (1 dept per company)
     * ------------------------------------------------------------
     */
    $deptId = DB::table('sacco_department')
        ->where('department_company_id', $companyId)
        ->orderBy('department_id')
        ->value('department_id');

    if (!$deptId) {
        $deptId = DB::table('sacco_department')->insertGetId([
            'department_name'        => $deptName,
            'department_company_id'  => $companyId,
            'department_transdate'   => now(),
            'department_ip'          => 'MIGRATION',
            'department_deleted'     => 'N',
        ]);

        if ($debug) {
            // logger()->info('JOAN | DEPARTMENT CREATED', [
            //     'dept_id'   => $deptId,
            //     'dept_name' => $deptName,
            // ]);
        }
    } else {
        if ($debug) {
            // logger()->info('JOAN | DEPARTMENT FOUND', [
            //     'dept_id' => $deptId,
            // ]);
        }
    }

    /*
     * ------------------------------------------------------------
     * 4. Change detection
     * ------------------------------------------------------------
     */
    $currentDeptId = DB::table('sacco_members')
        ->where('member_id', $memberId)
        ->value('member_dept');

    if ($debug) {
        // logger()->info('JOAN | DEPT COMPARISON', [
        //     'current_dept'  => $currentDeptId,
        //     'resolved_dept' => $deptId,
        // ]);
    }

    if ((int) $currentDeptId === (int) $deptId) {
        if ($debug) {
            // logger()->info('JOAN | NO COMPANY CHANGE (same dept)');
        }
        return;
    }

    /*
     * ------------------------------------------------------------
     * 5. Apply update (REAL company change)
     * ------------------------------------------------------------
     */
    DB::table('sacco_members')
        ->where('member_id', $memberId)
        ->update([
            'member_dept' => $deptId,
        ]);

    if ($debug) {
        // logger()->info('JOAN | COMPANY CHANGED', [
        //     'from_dept' => $currentDeptId,
        //     'to_dept'   => $deptId,
        // ]);
    }

    /*
     * ------------------------------------------------------------
     * 6. (Optional) Persist change history – disabled for now
     * ------------------------------------------------------------
     */
    // DB::table('sacco_member_company_changes')->insert([
    //     'member_id'    => $memberId,
    //     'old_dept_id'  => $currentDeptId,
    //     'new_dept_id'  => $deptId,
    //     'changed_on'   => now(),
    //     'source'       => 'IMPORT',
    // ]);
}


    protected function canonicalName(string $name): string
    {
        $tokens = $this->tokens($name);
        sort($tokens); // alphabetical order
        return implode(' ', $tokens);
    }
    protected function nameParts(string $name): array
    {
        $tokens = $this->tokens($name);

        return [
            'first'  => $tokens[0] ?? null,
            'last'   => count($tokens) > 1 ? $tokens[count($tokens) - 1] : null,
            'tokens' => $tokens,
        ];
    }
}
