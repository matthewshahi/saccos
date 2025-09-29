<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CapitalShareTransactionController extends Controller
{
    /**
     * List and search all capital share transactions
     */
    public function index(Request $request)
    {
        $search = $request->input('search');

        $query = DB::table('sacco_capital_shares as c')
            ->join('sacco_members as m', 'c.share_capitalmember_id', '=', 'm.member_id')
            ->leftJoin('users as u', 'c.share_capitalby', '=', 'u.id')
            ->select([
                'c.share_capitalid',
                'c.share_capitalmember_id',
                'c.share_capitalamount_paying',
                'c.share_capitalpaid_by',
                'c.share_capitalperiod',
                'c.share_capitaldescription',
                'c.share_capitaldoc_no',
                'c.share_capitaldate_paid',
                'c.share_capitalby',
                'c.share_capitalip',
                'c.share_capitaltransdate',
                'c.share_capitalend_month_proc',

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
            ->orderBy('c.share_capitaltransdate', 'desc');

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('m.member_name', 'like', "%{$search}%")
                  ->orWhere('m.member_national_id', 'like', "%{$search}%")
                  ->orWhere('m.member_phone_no', 'like', "%{$search}%")
                  ->orWhere('m.member_sacco_id', 'like', "%{$search}%")
                  ->orWhere('c.share_capitaldoc_no', 'like', "%{$search}%")
                  ->orWhere('c.share_capitaldescription', 'like', "%{$search}%")
                  ->orWhere('c.share_capitalperiod', 'like', "%{$search}%")
                  ->orWhere('u.name', 'like', "%{$search}%");
            });
        }

        $records = $query->limit(300)->paginate(25)->appends(['search' => $search]);

        return view('capitalshares.transactions.index', compact('records', 'search'));
    }

    /**
     * Show a single capital share transaction receipt
     */
    public function receipt($id)
    {
        $rec = DB::table('sacco_capital_shares as c')
            ->join('sacco_members as m', 'c.share_capitalmember_id', '=', 'm.member_id')
            ->leftJoin('users as u', 'c.share_capitalby', '=', 'u.id')
            ->select([
                'c.*',
                'm.member_name',
                'm.member_sacco_id',
                'm.member_national_id',
                'm.member_phone_no',
                'm.member_email',
                'u.name as entered_by_name',
            ])
            ->where('c.share_capitalid', $id)
            ->first();

        if (!$rec) {
            abort(404, 'Transaction not found');
        }

        // ✅ Get company name from defaults
        $companyName = DB::table('sacco_defaults')
            ->where('default_name', 'company_name')
            ->value('default_value') ?? 'Sacco';

        return view('capitalshares.transactions.receipt', compact('rec', 'companyName'));
    }
}