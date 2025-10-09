<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RegistrationFeeController extends Controller
{
    /**
     * List and search registration fee payments
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $query = DB::table('sacco_registration_fees as r')
            ->join('sacco_members as m', 'r.regfee_member_id', '=', 'm.member_id')
            ->leftJoin('users as u', 'r.regfee_by', '=', 'u.id')
            ->select([
                'r.regfee_id',
                'r.regfee_member_id',
                'r.regfee_amount',
                'r.regfee_paid_by',
                'r.regfee_description',
                'r.regfee_doc_no',
                'r.regfee_date_paid',
                'r.regfee_transdate',
                'r.regfee_end_month_proc',

                // Member details
                'm.member_name',
                'm.member_sacco_id',
                'm.member_national_id',
                'm.member_phone_no',
                'm.member_email',

                // User (entered by)
                'u.name as entered_by_name',
                'u.email as entered_by_email',
            ])
            ->orderBy('r.regfee_transdate', 'desc');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('m.member_name', 'like', "%{$search}%")
                  ->orWhere('m.member_national_id', 'like', "%{$search}%")
                  ->orWhere('m.member_phone_no', 'like', "%{$search}%")
                  ->orWhere('m.member_sacco_id', 'like', "%{$search}%")
                  ->orWhere('r.regfee_doc_no', 'like', "%{$search}%")
                  ->orWhere('r.regfee_description', 'like', "%{$search}%")
                  ->orWhere('u.name', 'like', "%{$search}%");
            });
        }

        $records = $query->limit(300)->paginate(25)->appends(['search' => $search]);

        return view('registrationfees.index', compact('records', 'search'));
    }

    /**
     * Show receipt for a single registration fee
     */
    public function receipt($id)
    {
        $rec = DB::table('sacco_registration_fees as r')
            ->join('sacco_members as m', 'r.regfee_member_id', '=', 'm.member_id')
            ->leftJoin('users as u', 'r.regfee_by', '=', 'u.id')
            ->select([
                'r.*',
                'm.member_name',
                'm.member_sacco_id',
                'm.member_national_id',
                'm.member_phone_no',
                'm.member_email',
                'u.name as entered_by_name',
            ])
            ->where('r.regfee_id', $id)
            ->first();

        if (!$rec) {
            abort(404, 'Registration Fee not found');
        }

        $companyName = DB::table('sacco_defaults')
            ->where('default_name', 'company_name')
            ->value('default_value') ?? 'Sacco';

        return view('registrationfees.receipt', compact('rec', 'companyName'));
    }
public function create()
{
    // Registration fee account (credit)
    $regFeeAccountId = DB::table('sacco_defaults')
        ->where('default_name', 'default_member_ship_fee_account')
        ->value('default_value');

    // Get readable account name for display
    $regFeeAccount = DB::table('sacco_sub_account as s')
        ->join('sacco_main_account as m', 's.sub_account_main_account', '=', 'm.main_account_id')
        ->where('s.sub_account_id', $regFeeAccountId)
        ->select(
            's.sub_account_id',
            DB::raw("CONCAT(m.main_account_code, ' /', s.sub_account_code, ' — ', s.sub_account_name) as account_label")
        )
        ->first();

    // Fallback in case the account was deleted or missing
    $regFeeAccountLabel = $regFeeAccount->account_label ?? 'Unknown Registration Fee Account';
    $regFeeAccountId = $regFeeAccount->sub_account_id ?? null;

    // Fetch members (for reference)
    $members = DB::table('sacco_members')
        ->select('member_id', 'member_name', 'member_sacco_id')
        ->where('member_deleted', '<>', 'Y')
        ->orderBy('member_name')
        ->get();

    // Fetch only current asset sub-accounts for contra
    $accounts = DB::table('sacco_sub_account as s')
        ->join('sacco_main_account as m', 's.sub_account_main_account', '=', 'm.main_account_id')
        ->where(function ($q) {
            $q->where('m.main_account_type', 'like', '%ASSET - CURRENT%')
              ->orWhere('m.main_account_type', 'like', '%ASSETS - CURRENT%');
        })
        ->select(
            's.sub_account_id as account_id',
            DB::raw("CONCAT(m.main_account_code, ' /', s.sub_account_code, ' — ', s.sub_account_name) as account_name")
        )
        ->orderBy('s.sub_account_name')
        ->get();

    return view('registrationfees.create', [
        'members'           => $members,
        'accounts'          => $accounts,
        'regFeeAccountId'   => $regFeeAccountId,
        'regFeeAccountLabel'=> $regFeeAccountLabel,
    ]);
}

/**
 * Store a manual registration fee (manual entry form)
 */
public function store(Request $request)
{
    // --- Validate incoming data ---
    $data = $request->validate([
        'regfee_member_id'   => 'required|string',
        'regfee_amount'      => 'required|numeric|min:1',
        'regfee_description' => 'required|string|max:255',
        'regfee_date_paid'   => 'required|date',
        'contra_account'     => 'required|string',
        'regfee_period'      => [
            'required',
            'regex:/^\d{6}$/', // must be 6 digits
            function ($attribute, $value, $fail) {
                // Ensure valid YYYYMM
                $year  = substr($value, 0, 4);
                $month = substr($value, 4, 2);
                if (!checkdate((int)$month, 1, (int)$year)) {
                    $fail('The ' . str_replace('_', ' ', $attribute) . ' must be a valid year and month (YYYYMM).');
                }
            },
        ],
    ]);

    // --- Core setup ---
    $userId = auth()->id() ?? 999;
    $ip     = $request->ip();
    $now    = Carbon::now();
    $period = $data['regfee_period'] ?? $now->format('Ym');

    // --- Extract Member SACCO ID (e.g. (0962)) ---
    preg_match('/\((.*?)\)/', $data['regfee_member_id'], $matches);
    $memberSaccoId = $matches[1] ?? null;

    $member = DB::table('sacco_members')
        ->where('member_sacco_id', $memberSaccoId)
        ->select('member_id', 'member_name', 'member_sacco_id')
        ->first();

    if (!$member) {
        return back()->withErrors(['regfee_member_id' => 'Member not found for SACCO ID ' . $memberSaccoId])->withInput();
    }

    $memberId = $member->member_id;

    // --- Extract Contra Account Codes (e.g. E400 /031) ---
    preg_match('/([A-Z0-9]+)\s*\/\s*(\d+)/', $data['contra_account'], $accMatches);
    $mainCode = $accMatches[1] ?? null;
    $subCode  = $accMatches[2] ?? null;

    $account = DB::table('sacco_sub_account as s')
        ->join('sacco_main_account as m', 's.sub_account_main_account', '=', 'm.main_account_id')
        ->where('m.main_account_code', $mainCode)
        ->where('s.sub_account_code', $subCode)
        ->select('s.sub_account_id')
        ->first();

    if (!$account) {
        return back()->withErrors(['contra_account' => 'Account not found for ' . $data['contra_account']])->withInput();
    }

    $contraAccountId = $account->sub_account_id;

    // --- Registration Fee Account (Credit) ---
    $regFeeAccount = DB::table('sacco_defaults')
        ->where('default_name', 'default_member_ship_fee_account')
        ->value('default_value');

    if (!$regFeeAccount) {
        return back()->withErrors(['error' => 'Default membership fee account not configured.']);
    }

    // --- Generate Document Number ---
    $docNo = 'REG-' . strtoupper(uniqid());

    // --- Build Ledger Description ---
    $ledgerDesc = 'Registration Fee — ' . strtoupper($member->member_name) . ' (' . $member->member_sacco_id . ')';

    // --- Insert into sacco_registration_fees ---
    $regId = DB::table('sacco_registration_fees')->insertGetId([
        'regfee_member_id'      => $memberId,
        'regfee_amount'         => $data['regfee_amount'],
        'regfee_doc_no'         => $docNo,
        'regfee_description'    => $data['regfee_description'],
        'regfee_date_paid'      => $data['regfee_date_paid'],
        'regfee_paid_by'        => $userId,
        'regfee_ip'             => $ip,
        'regfee_by'             => $userId,
        'regfee_created_ip'     => $ip,
        'regfee_transdate'      => $now,
        'regfee_end_month_proc' => $period,
        'created_at'            => $now,
        'updated_at'            => $now,
    ]);

    // --- Double Entry Posting ---
    self::updateSaccoAccountsTrans(
        $contraAccountId, // Dr side
        $data['regfee_amount'],
        0,
        $docNo,
        $ledgerDesc,
        $data['regfee_date_paid']
    );

    self::updateSaccoAccountsTrans(
        $regFeeAccount, // Cr side
        0,
        $data['regfee_amount'],
        $docNo,
        $ledgerDesc,
        $data['regfee_date_paid']
    );

    return redirect()->route('registrationfees.index', $regId)
                     ->with('success', 'Registration fee recorded successfully.');
}
/**
 * Shared ledger posting utility (Dr/Cr entries)
 */
private static function updateSaccoAccountsTrans($account, $debit, $credit, $docNo, $description, $date)
{
    DB::table('sacco_accounts_trans')->insert([
        'accounts_trans_sub_account' => $account,
        'accounts_trans_period'      => now()->format('Ym'),
        'accounts_trans_debit'       => $debit,
        'accounts_trans_credit'      => $credit,
        'accounts_trans_doc_no'      => $docNo,
        'accounts_trans_decription'  => $description,
        'accounts_trans_source'      => 'Manual Entry',
        'accounts_trans_dat_date'    => $date,
        'accounts_trans_transdate'   => now(),
        'accounts_trans_user_id'     => auth()->id() ?? 999,
        'accounts_trans_ip'          => request()->ip() ?? '127.0.0.1',
        'accounts_trans_app_name'    => 'manual',
    ]);
}
}