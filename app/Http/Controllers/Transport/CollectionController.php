<?php

namespace App\Http\Controllers\Transport;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CollectionController extends Controller
{
public function index(Request $request)
{
  
    $query = DB::table('sacco_matatus_collections AS c')
        ->leftJoin('sacco_matatus_operators AS o', 'c.coll_operator_id', '=', 'o.id')
        ->leftJoin('sacco_matatus_vehicles AS v', 'c.coll_vehicle_id', '=', 'v.id')
        ->select(
            'c.*',
            'o.full_name AS operator_name',
            'v.vehicles_registration_number AS vehicle_reg'
        );

    if ($search = $request->query('search')) {
        $query->where(function ($q) use ($search) {
            $q->where('o.full_name', 'like', "%{$search}%")
              ->orWhere('v.vehicles_registration_number', 'like', "%{$search}%")
              ->orWhere('c.coll_reference', 'like', "%{$search}%")
              ->orWhere('c.coll_type', 'like', "%{$search}%")
              ->orWhere('c.coll_mode', 'like', "%{$search}%");
        });
    }

    $collections = $query->orderByDesc('c.coll_date')->get();

    return view('transport.collections.index', compact('collections'));
}

public function create()
{
    $vehicles = DB::table('sacco_matatus_vehicles')
        ->select('id', 'vehicles_registration_number')
        ->orderBy('vehicles_registration_number')
        ->get();

    $operators = DB::table('sacco_matatus_operators')
        ->select('id', 'full_name')
        ->orderBy('full_name')
        ->get();

    return view('transport.collections.create', compact('vehicles', 'operators'));
}


public function store(Request $request)
{
    $validated = $request->validate([
        'coll_date' => 'required|date',
        'coll_operator_id' => 'nullable|integer',
        'coll_vehicle_id' => 'nullable|integer',
        'coll_type' => 'required|string',
        'coll_mode' => 'required|string',
        'coll_amount' => 'required|numeric|min:1',
        'coll_reference' => 'nullable|string|max:100',
        'coll_notes' => 'nullable|string|max:255',
    ]);

    // Ensure either operator or vehicle is selected
    if (empty($validated['coll_operator_id']) && empty($validated['coll_vehicle_id'])) {
        return back()->withErrors(['coll_operator_id' => 'You must select either an Operator or a Vehicle'])->withInput();
    }

    // Default reconciled flag
    $validated['coll_reconciled'] = $validated['coll_type'] === 'loan_repayment' ? 'No' : 'Yes';

    // Source logging
    $member = auth()->user();
    $validated['coll_source_log'] = 'Manual by ' . ($member->member_name ?? 'Unknown') . ' (User ID: ' . $member->member_id . ')';

    // Timestamps
    $validated['coll_entered_by'] = auth()->id();
    $validated['created_at'] = now();
    $validated['updated_at'] = now();

    DB::transaction(function () use (&$validated) {
        // Save collection
        $collectionId = DB::table('sacco_matatus_collections')->insertGetId($validated);
        $validated['collection_id'] = $collectionId;

        // Now process logic
        $this->postProcessCollection($validated);
    });

    return redirect()->route('collections')->with('success', 'Collection added successfully.');
}



protected function postProcessCollection(array $data)
{
    $memberId   = $data['coll_operator_id'];
    $vehicleId  = $data['coll_vehicle_id'] ?? null;
    $amount     = $data['coll_amount'];
    $date       = $data['coll_date'];
    $type       = $data['coll_type'];
    $mode       = $data['coll_mode'];
    $reference  = $data['coll_reference'];
    $notes      = $data['coll_notes'] ?? null;

    $userId = auth()->id();
    $ip     = request()->ip();
    $period = date('Ym', strtotime($date));

    // Resolve memberId from vehicle if operator not given
    if (!$memberId && $vehicleId) {
        $memberId = DB::table('sacco_matatus_vehicles')
                    ->where('id', $vehicleId)
                    ->value('vehicles_owner_member_id');
    }

    // Get vehicle registration number
    $vehicleReg = null;
    if ($vehicleId) {
        $vehicleReg = DB::table('sacco_matatus_vehicles')
                        ->where('id', $vehicleId)
                        ->value('vehicles_registration_number');
    }

    // Get operator name
    $operatorName = null;
    if ($data['coll_operator_id']) {
        $operatorName = DB::table('sacco_matatus_operators')
                          ->where('id', $data['coll_operator_id'])
                          ->value('full_name');
    }

    // Build description
    $descriptionParts = [$type];

    if ($vehicleReg) {
        $descriptionParts[] = 'Vehicle ' . $vehicleReg;
    }

    if ($mode) {
        $descriptionParts[] = 'Mode ' . ucfirst($mode);
    }

    if ($operatorName) {
        $descriptionParts[] = 'Driver ' . $operatorName . ' (ID: ' . $data['coll_operator_id'] . ')';
    } elseif ($data['coll_operator_id']) {
        $descriptionParts[] = 'Driver ID ' . $data['coll_operator_id'];
    }

    if (!empty($data['collection_id'])) {
        $descriptionParts[] = '[#' . $data['collection_id'] . ']';
    }

    if (!empty($notes)) {
        $descriptionParts[] = 'Note: ' . $notes;
    }

    $description = implode(' - ', $descriptionParts);

    // Skip loan processing — handled via reconciliation modal
    if ($type === 'loan_repayment') {
        return;
    }

    // Share capital deposit
    if ($type === 'share' || $type === 'deposit') {
        DB::table('sacco_capital_shares')->insert([
            'share_capitalmember_id'         => $memberId,
            'share_capitalamount_paying'     => $amount,
            'share_capitalpaid_by'           => $mode,
            'share_capitalperiod'            => $period,
            'share_capitaldescription'       => $description,
            'share_capitaldoc_no'            => $reference,
            'share_capitaldate_paid'         => $date,
            'share_capitalby'                => $userId,
            'share_capitalip'                => $ip,
            'share_capitalend_month_proc'    => 'N',
        ]);
    }
    // All other collection types (including daily_target, penalty, etc.)
    else {
        DB::table('sacco_shares')->insert([
            'share_member_id'        => $memberId,
            'share_amount_paying'    => $amount,
            'share_paid_by'          => $mode,
            'share_period'           => $period,
            'share_description'      => $description,
            'share_doc_no'           => $reference,
            'share_date_paid'        => $date,
            'share_by'               => $userId,
            'share_ip'               => $ip,
            'share_end_month_proc'   => 'N',
        ]);
    }
}
public function reconcile($id, Request $request)
{
    $collection = DB::table('sacco_matatus_collections')->where('id', $id)->first();

    if (!$collection || $collection->coll_type !== 'loan_repayment') {
        abort(404, 'Invalid collection for reconciliation.');
    }

    $memberId = $collection->coll_operator_id;

    $member = DB::table('sacco_members')->where('member_id', $memberId)->first();

    $loans = DB::table('sacco_loans as l')
        ->join('sacco_loan_types as t', 'l.loan_loan_type', '=', 't.loan_type_id') // updated join
        ->select('l.*', 't.loan_type_name')
        ->where('l.loan_member', $memberId)
        // ->where('l.loan_status', 'active')
        ->where('l.loan_stoped', 'N')
        ->whereRaw('(l.loan_amount - IFNULL(l.loan_loan_paid, 0)) > 1') 
        // ->whereRaw('(COALESCE(l.loan_amount, 0) - COALESCE(l.loan_loan_paid, 0)) > 0')
        ->get();

    return view('transport.collections._reconcile_modal_body', compact('collection', 'member', 'loans'));
}

public function attachLoan($id, Request $request)
{
    $request->validate([
        'loan_id' => 'required|integer|exists:sacco_loans,loan_id',
    ]);

    // Simulate the reconciliation process
    $loanId = $request->loan_id;

    echo "<h3 style='padding:20px;'>✅ Adding Collection ID: {$id} to Loan ID: {$loanId}...<br>Process will be completed tomorrow.</h3>";
    exit;
}
}
