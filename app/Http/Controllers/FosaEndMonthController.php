<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class FosaEndMonthController extends Controller
{
    /** Show members + expected contributions */
    public function index(Request $request)
{
    $period = $request->get('period', now()->format('Ym'));
    $search = trim($request->get('search', '')); // ✅ strip spaces

    $query = DB::table('sacco_members as m')
        ->leftJoin('sacco_department as d', 'd.department_id', '=', 'm.member_dept')
        ->leftJoin('sacco_company as c', 'c.company_id', '=', 'd.department_company_id')
        ->select(
            'm.member_id',
            'm.member_name',
            'm.member_sacco_id',
            'm.member_national_id',
            'm.member_fosa_contr_monthly',
            'm.member_total_fosa',
            'd.department_name',
            'c.company_name'
        )
        ->where('m.member_active', 'Y')
        ->where('m.member_deleted', 'N')
        ->whereDate('m.member_date_joined', '<=', now()->endOfMonth());

    // ✅ Apply search filter if provided
    if ($search !== '') {
        $query->where(function($q) use ($search) {
            $q->where('m.member_name', 'like', "%{$search}%")
              ->orWhere('m.member_sacco_id', 'like', "%{$search}%")
              ->orWhere('m.member_national_id', 'like', "%{$search}%")
              ->orWhere('c.company_name', 'like', "%{$search}%")
              ->orWhere('d.department_name', 'like', "%{$search}%");
        });
    }

    $members = $query->get();

    return view('fosa.endmonth.index', compact('members', 'period', 'search'));
}

    /** AJAX update monthly contribution */
    public function update(Request $request, $id)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
        ]);

        DB::table('sacco_members')
            ->where('member_id', $id)
            ->update(['member_fosa_contr_monthly' => $request->amount]);

        return response()->json(['success' => true]);
    }

    /** Process contributions */
    public function process(Request $request)
    {
        $request->validate([
            'period'     => 'required|digits:6',
            'ledger_id'  => 'required|integer|exists:sacco_sub_account,sub_account_id',
            'doc_prefix' => 'required|string',
        ]);

        $period   = $request->period;
        $endDate  = Carbon::createFromFormat('Ym', $period)->endOfMonth();
        $descBase = "EndMonth Proc: {$period}";
        $docBase  = $request->doc_prefix;

        $defaultFosa = DB::table('sacco_defaults')
            ->where('default_name', 'default_fosa_account')
            ->value('default_value');

        if (!$defaultFosa) {
            return back()->with('error', 'Default FOSA account not configured.');
        }

        $processed = $skipped = 0;

        DB::beginTransaction();
        try {

            $selected = $request->input('selected_members', []);

if (empty($selected)) {
    return back()->with('error', 'No members selected for processing.');
}

$members = DB::table('sacco_members')
    ->whereIn('member_id', $selected)
    ->where('member_active', 'Y')
    ->where('member_deleted', 'N')
    ->get();

            // $members = DB::table('sacco_members')
            //     ->where('member_active', 'Y')
            //     ->where('member_deleted', 'N')
            //     ->get();

            foreach ($members as $member) {
                $amount = $member->member_fosa_contr_monthly;
                if ($amount <= 0) continue;

                // Skip if already processed
                $already = DB::table('sacco_fosas')
                    ->where('fosa_member_id', $member->member_id)
                    ->where('fosa_period', $period)
                    ->where('fosa_description', 'like', "%{$descBase}%")
                    ->exists();

                if ($already) { $skipped++; continue; }

                // Insert FOSA
                $fosaId = DB::table('sacco_fosas')->insertGetId([
                    'fosa_member_id'      => $member->member_id,
                    'fosa_type_id'        => null,
                    'fosa_amount_paying'  => $amount,
                    'fosa_paid_by'        => 'System',
                    'fosa_period'         => $period,
                    'fosa_description'    => "{$descBase}",
                    'fosa_doc_no'         => "{$docBase}-{$member->member_sacco_id}",
                    'fosa_date_paid'      => $endDate,
                    'fosa_by'             => Auth::id(),
                    'fosa_ip'             => $request->ip(),
                    'fosa_transdate'      => now(),
                    'fosa_end_month_proc' => 'Y',
                ]);

                // Update member totals
                DB::table('sacco_members')
                    ->where('member_id', $member->member_id)
                    ->increment('member_total_fosa', $amount);

                // Ledger double entry
                $entry = [
    'accounts_trans_period'     => $period,
    'accounts_trans_doc_no'     => "{$docBase}-{$member->member_sacco_id}",
    'accounts_trans_decription' => "EndMonth Proc - {$period} [FOSA ID: {$fosaId}] {$descBase} (Member: {$member->member_name})",
    'accounts_trans_source'     => "FOSA:{$fosaId}",
    'accounts_trans_dat_date'   => $endDate,
    'accounts_trans_user_id'    => Auth::id(),
    'accounts_trans_ip'         => $request->ip(),
    'accounts_trans_member_id'  => $member->member_id,
    'accounts_trans_app_name'   => 'FOSA EndMonth',
];

                DB::table('sacco_accounts_trans')->insert([
                    array_merge($entry, [
                        'accounts_trans_sub_account' => $request->ledger_id,
                        'accounts_trans_debit'       => $amount,
                        'accounts_trans_credit'      => 0,
                    ]),
                    array_merge($entry, [
                        'accounts_trans_sub_account' => $defaultFosa,
                        'accounts_trans_debit'       => 0,
                        'accounts_trans_credit'      => $amount,
                    ]),
                ]);

                $processed++;
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Processed {$processed} members. Skipped {$skipped} (already processed).");
    }
}