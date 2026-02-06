<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Exports\FosaTransactionsExport;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;



class FosaTransactionController extends Controller
{
    public function index(Request $request)
{
    $search = $request->input('search');

    $query = DB::table('sacco_fosas as f')
        ->join('sacco_members as m', 'f.fosa_member_id', '=', 'm.member_id')
        ->leftJoin('sacco_fosa_types as t', 'f.fosa_type_id', '=', 't.type_id')
        ->leftJoin('users as u', 'f.fosa_by', '=', 'u.id') // ✅ join users table
        ->select([
            'f.fosa_id',
            'f.fosa_member_id',
            'f.fosa_type_id',
            'f.fosa_amount_paying',
            'f.fosa_paid_by',
            'f.fosa_period',
            'f.fosa_description',
            'f.fosa_doc_no',
            'f.fosa_date_paid',
            'f.fosa_by',
            'f.fosa_ip',
            'f.fosa_transdate',
            'f.fosa_end_month_proc',

            // Member details
            'm.member_id as member_id',
            'm.member_name',
            'm.member_sacco_id',
            'm.member_national_id',
            'm.member_phone_no',
            'm.member_email',

            // FOSA type
            't.type_name as fosa_type_name',

            // User (entered by)
            'u.name as entered_by_name',
            'u.email as entered_by_email',
        ])
        ->orderBy('f.fosa_transdate', 'desc');

    if (!empty($search)) {
        $query->where(function ($q) use ($search) {
            $q->where('m.member_name', 'like', "%{$search}%")
              ->orWhere('m.member_national_id', 'like', "%{$search}%")
              ->orWhere('m.member_phone_no', 'like', "%{$search}%")
              ->orWhere('m.member_sacco_id', 'like', "%{$search}%")
              ->orWhere('f.fosa_doc_no', 'like', "%{$search}%")
              ->orWhere('f.fosa_description', 'like', "%{$search}%")
              ->orWhere('f.fosa_period', 'like', "%{$search}%")
              ->orWhere('u.name', 'like', "%{$search}%"); // ✅ search by staff too
        });
    }

    // ✅ fetch a capped set, then paginate locally
    $records = $query->limit(300)->paginate(25)->appends(['search' => $search]);

    $fosaTypes = DB::table('sacco_fosa_types')
    ->where('type_active', 'Y')
    ->orderBy('type_name')
    ->get();

return view('fosa.transactions.index', compact('records', 'search', 'fosaTypes'));

}
    public function create()
    {
        // 🔹 Get default FOSA account
        $defaultFosa = DB::table('sacco_defaults')
            ->where('default_name', 'default_fosa_account')
            ->value('default_value');

        if (!$defaultFosa) {
            return view('fosa.transactions.missing_default');
        }

        // 🔹 Get active period from sacco_period table
        $activePeriod = DB::table('sacco_period')
            ->where('period_active', 'Y')
            ->value('period_name') ?? date('Ym'); // fallback if no active period

        // 🔹 Get active FOSA types
        $fosaTypes = DB::table('sacco_fosa_types')
            ->where('type_active', 'Y')
            ->orderBy('type_name')
            ->get();

        if ($fosaTypes->isEmpty()) {
            return redirect()->route('fosa.index')
                ->with('error', 'Please add at least one FOSA Type before proceeding.');
        }

        // 🔹 Load members
        $members = DB::table('sacco_members')
            ->select('member_id', 'member_name')
            ->orderBy('member_name')
            ->get();

        // 🔹 Load sub-accounts joined with main accounts
        $ledgerAccounts = DB::table('sacco_sub_account as s')
            ->join('sacco_main_account as m', 's.sub_account_main_account', '=', 'm.main_account_id')
            ->select(
                's.sub_account_id as ledger_id',
                DB::raw("CONCAT(m.main_account_name, ' - ', s.sub_account_name) as ledger_name")
            )
            ->orderBy('m.main_account_name')
            ->orderBy('s.sub_account_name')
            ->get();

        return view('fosa.transactions.create', compact(
            'members',
            'ledgerAccounts',
            'defaultFosa',
            'fosaTypes',
            'activePeriod'
        ));
    }
    public function store(Request $request)
    {
        $request->validate([
            'fosa_member_id'     => 'required|exists:sacco_members,member_id',
            'fosa_type_id'       => 'required|exists:sacco_fosa_types,type_id',
            'fosa_amount_paying' => 'required|numeric|min:0.01',
            'fosa_trans_type'    => 'required|in:credit,debit',
            'counter_account'    => 'required|exists:sacco_sub_account,sub_account_id',
            'fosa_period'        => 'required|digits:6',  // YYYYMM format
            'fosa_date_paid'     => 'required|date',
            'fosa_description'   => 'required|string|max:200',
            'fosa_doc_no'        => 'required|string|max:100',
        ]);

        // 🔹 Confirm default FOSA account
        $defaultFosa = DB::table('sacco_defaults')
            ->where('default_name', 'default_fosa_account')
            ->value('default_value');

        if (!$defaultFosa) {
            return back()->with('error', 'Default FOSA account missing. Please configure it.');
        }

        DB::beginTransaction();
        try {
            $amount    = $request->fosa_amount_paying;
            $isDeposit = $request->fosa_trans_type === 'credit';
            $docNo     = $request->fosa_doc_no ?? uniqid("FOSA-");

            $typeName = DB::table('sacco_fosa_types')
                ->where('type_id', $request->fosa_type_id)
                ->value('type_name');



            // 🔹 Insert into sacco_fosas and get ID
            $fosaId = DB::table('sacco_fosas')->insertGetId([
                'fosa_member_id'      => $request->fosa_member_id,
                'fosa_type_id'       => $request->fosa_type_id,
                'fosa_amount_paying'  => $isDeposit ? $amount : -$amount,
                'fosa_paid_by'        => Auth::user()->member_name ?? 'System',
                'fosa_period'         => $request->fosa_period,
                'fosa_description'   => "{$request->fosa_description} ({$typeName})",
                'fosa_doc_no'         => $docNo,
                'fosa_date_paid'      => $request->fosa_date_paid,
                'fosa_by'             => Auth::id(),
                'fosa_ip'             => $request->ip(),
                'fosa_transdate'      => now(),
                'fosa_end_month_proc' => 'N',
            ]);

            // Update member total FOSA balance
            if ($isDeposit) {
                DB::table('sacco_members')
                    ->where('member_id', $request->fosa_member_id)
                    ->increment('member_total_fosa', $amount);
            } else {
                DB::table('sacco_members')
                    ->where('member_id', $request->fosa_member_id)
                    ->decrement('member_total_fosa', $amount);
            }

            // 🔹 Fetch member name for description
            $memberName = DB::table('sacco_members')
                ->where('member_id', $request->fosa_member_id)
                ->value('member_name');

            // 🔹 Prepare common entry data with audit link
            $entryData = [
                'accounts_trans_period'     => $request->fosa_period,
                'accounts_trans_doc_no'     => $docNo,
                'accounts_trans_decription' => "FOSA Transaction #{$fosaId}: {$request->fosa_description} ({$typeName}) (Member: {$memberName})",
                'accounts_trans_source'     => "FOSA:{$fosaId}",  // ✅ link to FOSA table
                'accounts_trans_dat_date'   => $request->fosa_date_paid,
                'accounts_trans_user_id'    => Auth::id(),
                'accounts_trans_ip'         => $request->ip(),
                'accounts_trans_member_id'  => $request->fosa_member_id,
                'accounts_trans_app_name'   => 'FOSA Transactions',
            ];

            if ($isDeposit) {
                // Deposit: Debit Counter, Credit FOSA
                DB::table('sacco_accounts_trans')->insert([
                    array_merge($entryData, [
                        'accounts_trans_sub_account' => $request->counter_account,
                        'accounts_trans_debit'       => $amount,
                        'accounts_trans_credit'      => 0,
                    ]),
                    array_merge($entryData, [
                        'accounts_trans_sub_account' => $defaultFosa,
                        'accounts_trans_debit'       => 0,
                        'accounts_trans_credit'      => $amount,
                    ])
                ]);
            } else {
                // Withdrawal: Debit FOSA, Credit Counter
                DB::table('sacco_accounts_trans')->insert([
                    array_merge($entryData, [
                        'accounts_trans_sub_account' => $defaultFosa,
                        'accounts_trans_debit'       => $amount,
                        'accounts_trans_credit'      => 0,
                    ]),
                    array_merge($entryData, [
                        'accounts_trans_sub_account' => $request->counter_account,
                        'accounts_trans_debit'       => 0,
                        'accounts_trans_credit'      => $amount,
                    ])
                ]);
            }

            DB::commit();
            return redirect()->route('fosa.transactions.index')->with('success', 'FOSA transaction posted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Transaction failed: ' . $e->getMessage());
        }
    }
    public function searchMembers(Request $request)
    {
        $term = $request->get('term', '');

        $results = DB::table('sacco_members')
            ->where(function ($q) use ($term) {
                $q->where('member_name', 'like', "%{$term}%")
                    ->orWhere('member_phone_no', 'like', "%{$term}%")
                    ->orWhere('member_national_id', 'like', "%{$term}%")
                    ->orWhere('member_email', 'like', "%{$term}%");
            })
            ->limit(20)
            ->get()
            ->map(function ($m) {
                return [
                    'id'   => $m->member_id,
                    'text' => "{$m->member_name} ({$m->member_phone_no} | {$m->member_national_id} | {$m->member_email})"
                ];
            });

        return response()->json($results);
    }
    public function searchAccounts(Request $request)
    {
        $term = $request->get('term', '');

        $results = DB::table('sacco_sub_account as s')
            ->join('sacco_main_account as m', 's.sub_account_main_account', '=', 'm.main_account_id')
            ->where(function ($q) use ($term) {
                $q->where('s.sub_account_name', 'like', "%{$term}%")
                    ->orWhere('s.sub_account_code', 'like', "%{$term}%")
                    ->orWhere('m.main_account_name', 'like', "%{$term}%")
                    ->orWhere('m.main_account_code', 'like', "%{$term}%");
            })
            ->select(
                's.sub_account_id as id',
                DB::raw("CONCAT(m.main_account_code, '/', s.sub_account_code, ' - ', s.sub_account_name) as text")
            )
            ->limit(20)
            ->get();

        return response()->json($results);
    }
public function receipt($id)
{
    // Fetch transaction with related member, type, and user (staff who entered it)
    $record = DB::table('sacco_fosas as f')
        ->join('sacco_members as m', 'f.fosa_member_id', '=', 'm.member_id')
        ->leftJoin('sacco_fosa_types as t', 'f.fosa_type_id', '=', 't.type_id')
        ->leftJoin('users as u', 'f.fosa_by', '=', 'u.id')
        ->select([
            'f.fosa_id',
            'f.fosa_member_id',
            'f.fosa_type_id',
            'f.fosa_amount_paying',
            'f.fosa_paid_by',
            'f.fosa_period',
            'f.fosa_description',
            'f.fosa_doc_no',
            'f.fosa_date_paid',
            'f.fosa_ip',
            'f.fosa_transdate',

            // Member details
            'm.member_name',
            'm.member_sacco_id',
            'm.member_national_id',
            'm.member_phone_no',
            'm.member_email',

            // FOSA type
            't.type_name as fosa_type_name',

            // Staff who entered
            'u.name as entered_by_name',
            'u.email as entered_by_email',
        ])
        ->where('f.fosa_id', $id)
        ->first();

    if (!$record) {
        abort(404, 'Transaction not found.');
    }

    // Fetch company name from defaults
    $companyName = DB::table('sacco_defaults')
        ->where('default_name', 'company_name')
        ->value('default_value') ?? 'SACCO Ltd';

    // Dynamically decide if this is a Receipt or Voucher
    $docType = $record->fosa_amount_paying >= 0 ? 'Receipt' : 'Payment Voucher';
    $docNo   = 'FOSA' . $record->fosa_id;

    // Pass to view
    return view('fosa.transactions.receipt', [
        'record'      => $record,
        'companyName' => $companyName,
        'docType'     => $docType,
        'docNo'       => $docNo,
    ]);
}

public function export(Request $request)
{
    $search = $request->input('search', null);
    $fileName = 'FOSA_Transactions_' . now()->format('Ymd_His') . '.xlsx';

    return Excel::download(new FosaTransactionsExport($search), $fileName);
}
public function updateType(Request $request, $id)
{
    $request->validate([
        'fosa_type_id'  => 'required|exists:sacco_fosa_types,type_id',
        'change_reason' => 'nullable|string|max:255',
        'change_notes'  => 'nullable|string',
    ]);

    // prevent edits after end-month processing (recommended safeguard)
    $rec = DB::table('sacco_fosas')
        ->select('fosa_id', 'fosa_end_month_proc', 'fosa_type_id', 'fosa_description')
        ->where('fosa_id', $id)
        ->first();

    if (!$rec) {
        return back()->with('error', 'Transaction not found.');
    }

    if (($rec->fosa_end_month_proc ?? 'N') === 'Y') {
        return back()->with('error', 'This transaction is locked (end-month processed).');
    }

    
     if (empty($rec->fosa_transdate)) {
        return back()->with('error', 'This transaction has no transaction date; cannot validate edit window.');
    }

    $daysOld = Carbon::parse($rec->fosa_transdate)->diffInDays(now());

    if ($daysOld > 60) {
        return back()->with('error', "This transaction is too old to edit ({$daysOld} days old). Allowed window is 60 days.");
    }


    $newTypeId = (int) $request->fosa_type_id;
    $oldTypeId = (int) ($rec->fosa_type_id ?? 0);

    // No change
    if ($oldTypeId === $newTypeId) {
        return back()->with('success', 'No change applied (same FOSA Type).');
    }

    // optional: update description suffix to reflect new type
    $typeName = DB::table('sacco_fosa_types')
        ->where('type_id', $newTypeId)
        ->value('type_name');

    // If your description is always "... (TypeName)" you can normalize it:
    $newDescription = $rec->fosa_description;
    if ($typeName) {
        // remove last "(...)" if present and re-append
        $newDescription = preg_replace('/\s*\([^)]*\)\s*$/', '', (string) $rec->fosa_description);
        $newDescription = trim($newDescription) . " ({$typeName})";
    }

    DB::beginTransaction();
    try {
        // Update transaction type
        DB::table('sacco_fosas')
            ->where('fosa_id', $id)
            ->update([
                'fosa_type_id'     => $newTypeId,
                'fosa_description' => $newDescription,
            ]);

        // Audit log
        DB::table('sacco_fosa_transaction_type_changes')->insert([
            'fosa_id'       => $id,
            'from_type_id'  => $rec->fosa_type_id, // keep nullable
            'to_type_id'    => $newTypeId,
            'change_reason' => $request->input('change_reason'),
            'change_notes'  => $request->input('change_notes'),
            'changed_by'    => Auth::id(),
            'changed_ip'    => $request->ip(),
            'changed_at'    => now(),
        ]);

        DB::commit();
        return back()->with('success', 'FOSA Type updated and audit logged.');
    } catch (\Exception $e) {
        DB::rollBack();
        return back()->with('error', 'Update failed: ' . $e->getMessage());
    }
}


}
