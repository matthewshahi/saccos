<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class LoanDeductionTypeController extends Controller
{
    public function index()
    {
        $records = DB::table('sacco_loan_deductions_types as dt')
            ->leftJoin('sacco_sub_account as s', 'dt.deduction_type_account', '=', 's.sub_account_id')
            ->leftJoin('sacco_main_account as m', 's.sub_account_main_account', '=', 'm.main_account_id')
            ->where('dt.deduction_type_deleted', 'N')
            ->orderBy('dt.deduction_type_name', 'asc')
            ->select(
                'dt.*',
                's.sub_account_name',
                's.sub_account_code',
                'm.main_account_name',
                'm.main_account_code',
                'm.main_account_type'
            )
            ->get();

        return view('loans.deduction_types.index', compact('records'));
    }

    public function create()
    {
        $incomeLiabilityAccounts = $this->getIncomeLiabilityAccounts();

        return view('loans.deduction_types.add', compact('incomeLiabilityAccounts'));
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'deduction_type_name'          => 'required|string|max:250',
            'deduction_type_code'          => 'nullable|string|max:100',
            'deduction_type_description'   => 'nullable|string',
            'deduction_type_value_type'    => 'required|in:FIXED,PERCENT',
            'deduction_type_default_value' => 'nullable|numeric|min:0',
            'deduction_type_effect'        => 'required|in:ADD_TO_LOAN,DEDUCT_FROM_DISBURSEMENT',
            'deduction_type_account'       => 'nullable|integer',
        ]);

        DB::beginTransaction();

        try {
            DB::table('sacco_loan_deductions_types')->insert([
                'deduction_type_name'          => trim($validated['deduction_type_name']),
                'deduction_type_code'          => $this->nullIfEmpty($validated['deduction_type_code'] ?? null),
                'deduction_type_description'   => $this->nullIfEmpty($validated['deduction_type_description'] ?? null),
                'deduction_type_value_type'    => strtoupper($validated['deduction_type_value_type']),
                'deduction_type_default_value' => (float) ($validated['deduction_type_default_value'] ?? 0),
                'deduction_type_effect'        => strtoupper($validated['deduction_type_effect']),
                'deduction_type_account'       => $this->nullIfEmpty($validated['deduction_type_account'] ?? null),
                'deduction_type_active'        => $request->has('deduction_type_active') ? 1 : 0,
                'deduction_type_deleted'       => 'N',
                'deduction_type_by'            => Auth::id(),
                'deduction_type_ip'            => $request->ip(),
                'deduction_type_transdate'     => now(),
            ]);

            DB::commit();

            return redirect()
                ->route('loans.deduction-types')
                ->with('success', 'Loan deduction type created successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to create loan deduction type. ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $record = DB::table('sacco_loan_deductions_types')
            ->where('deduction_type_id', $id)
            ->where('deduction_type_deleted', 'N')
            ->first();

        if (!$record) {
            return redirect()
                ->route('loans.deduction-types')
                ->with('error', 'Loan deduction type not found.');
        }

        $incomeLiabilityAccounts = $this->getIncomeLiabilityAccounts();

        return view('loans.deduction_types.edit', compact('record', 'incomeLiabilityAccounts'));
    }

    public function update(Request $request, $id)
    {
        $record = DB::table('sacco_loan_deductions_types')
            ->where('deduction_type_id', $id)
            ->where('deduction_type_deleted', 'N')
            ->first();

        if (!$record) {
            return redirect()
                ->route('loans.deduction-types')
                ->with('error', 'Loan deduction type not found.');
        }

        $validated = $request->validate([
            'deduction_type_name'          => 'required|string|max:250',
            'deduction_type_code'          => 'nullable|string|max:100',
            'deduction_type_description'   => 'nullable|string',
            'deduction_type_value_type'    => 'required|in:FIXED,PERCENT',
            'deduction_type_default_value' => 'nullable|numeric|min:0',
            'deduction_type_effect'        => 'required|in:ADD_TO_LOAN,DEDUCT_FROM_DISBURSEMENT',
            'deduction_type_account'       => 'nullable|integer',
        ]);

        DB::beginTransaction();

        try {
            DB::table('sacco_loan_deductions_types')
                ->where('deduction_type_id', $id)
                ->update([
                    'deduction_type_name'          => trim($validated['deduction_type_name']),
                    'deduction_type_code'          => $this->nullIfEmpty($validated['deduction_type_code'] ?? null),
                    'deduction_type_description'   => $this->nullIfEmpty($validated['deduction_type_description'] ?? null),
                    'deduction_type_value_type'    => strtoupper($validated['deduction_type_value_type']),
                    'deduction_type_default_value' => (float) ($validated['deduction_type_default_value'] ?? 0),
                    'deduction_type_effect'        => strtoupper($validated['deduction_type_effect']),
                    'deduction_type_account'       => $this->nullIfEmpty($validated['deduction_type_account'] ?? null),
                    'deduction_type_active'        => $request->has('deduction_type_active') ? 1 : 0,
                    'deduction_type_by'            => Auth::id(),
                    'deduction_type_ip'            => $request->ip(),
                    'deduction_type_transdate'     => now(),
                ]);

            DB::commit();

            return redirect()
                ->route('loans.deduction-types')
                ->with('success', 'Loan deduction type updated successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()
                ->back()
                ->withInput()
                ->with('error', 'Failed to update loan deduction type. ' . $e->getMessage());
        }
    }

    public function destroy(Request $request, $id)
    {
        $record = DB::table('sacco_loan_deductions_types')
            ->where('deduction_type_id', $id)
            ->where('deduction_type_deleted', 'N')
            ->first();

        if (!$record) {
            return redirect()
                ->route('loans.deduction-types')
                ->with('error', 'Loan deduction type not found.');
        }

        DB::beginTransaction();

        try {
            DB::table('sacco_loan_deductions_types')
                ->where('deduction_type_id', $id)
                ->update([
                    'deduction_type_deleted'    => 'Y',
                    'deduction_type_active'     => 0,
                    'deduction_type_deleted_by' => Auth::id(),
                    'deduction_type_deleted_on' => now(),
                    'deduction_type_deleted_ip' => $request->ip(),
                ]);

            DB::commit();

            return redirect()
                ->route('loans.deduction-types')
                ->with('success', 'Loan deduction type deleted successfully.');
        } catch (\Throwable $e) {
            DB::rollBack();

            return redirect()
                ->route('loans.deduction-types')
                ->with('error', 'Failed to delete loan deduction type. ' . $e->getMessage());
        }
    }

    private function getIncomeLiabilityAccounts()
    {
        return DB::table('sacco_sub_account as s')
            ->join('sacco_main_account as m', 's.sub_account_main_account', '=', 'm.main_account_id')
            ->where('s.sub_account_deleted', 'N')
            ->where('m.main_account_deleted', 'N')
            ->where(function ($query) {
                $query->whereRaw('UPPER(m.main_account_type) = ?', ['INCOME'])
                      ->orWhereRaw('UPPER(m.main_account_type) LIKE ?', ['LIABILIT%']);
            })
            ->orderBy('s.sub_account_name', 'asc')
            ->orderBy('m.main_account_code', 'asc')
            ->orderBy('s.sub_account_code', 'asc')
            ->select(
                's.sub_account_id',
                's.sub_account_name',
                's.sub_account_code',
                'm.main_account_name',
                'm.main_account_code',
                'm.main_account_type'
            )
            ->get();
    }

    private function nullIfEmpty($value)
    {
        return $value === '' || $value === null ? null : $value;
    }
}