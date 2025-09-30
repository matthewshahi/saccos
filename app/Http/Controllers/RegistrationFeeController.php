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
}