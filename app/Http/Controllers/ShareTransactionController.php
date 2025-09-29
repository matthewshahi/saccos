<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ShareTransactionController extends Controller
{
    /**
     * List and search all share transactions
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $query = DB::table('sacco_shares as s')
            ->join('sacco_members as m', 's.share_member_id', '=', 'm.member_id')
            ->leftJoin('users as u', 's.share_by', '=', 'u.id')
            ->select([
                's.share_id',
                's.share_member_id',
                's.share_amount_paying',
                's.share_paid_by',
                's.share_period',
                's.share_description',
                's.share_doc_no',
                's.share_date_paid',
                's.share_by',
                's.share_ip',
                's.share_transdate',
                's.share_end_month_proc',

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
            ->orderBy('s.share_transdate', 'desc');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('m.member_name', 'like', "%{$search}%")
                  ->orWhere('m.member_national_id', 'like', "%{$search}%")
                  ->orWhere('m.member_phone_no', 'like', "%{$search}%")
                  ->orWhere('m.member_sacco_id', 'like', "%{$search}%")
                  ->orWhere('s.share_doc_no', 'like', "%{$search}%")
                  ->orWhere('s.share_description', 'like', "%{$search}%")
                  ->orWhere('s.share_period', 'like', "%{$search}%")
                  ->orWhere('u.name', 'like', "%{$search}%");
            });
        }

        // Cap results for performance, then paginate
        $records = $query->limit(300)->paginate(25)->appends(['search' => $search]);

        return view('shares.transactions.index', compact('records', 'search'));
    }

    /**
     * Show a single share transaction receipt (basic stub for now)
     */
    public function receipt($id)
{
    $rec = DB::table('sacco_shares as s')
        ->join('sacco_members as m', 's.share_member_id', '=', 'm.member_id')
        ->leftJoin('users as u', 's.share_by', '=', 'u.id')
        ->select([
            's.*',
            'm.member_name',
            'm.member_sacco_id',
            'm.member_national_id',
            'm.member_phone_no',
            'm.member_email',
            'u.name as entered_by_name',
        ])
        ->where('s.share_id', $id)
        ->first();

    if (!$rec) {
        abort(404, 'Transaction not found');
    }

    // ✅ Fetch company/SACCO name from sacco_defaults
    $companyName = DB::table('sacco_defaults')
        ->where('default_name', 'company_name')
        ->value('default_value') ?? 'Sacco';

    return view('shares.transactions.receipt', compact('rec', 'companyName'));
}
}