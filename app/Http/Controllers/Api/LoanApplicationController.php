<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanApplicationController extends Controller
{
    /**
     * GET /api/auth/loan-applications/products
     *
     * Returns loan types available for application.
     * Read-only, authenticated, integrity-first.
     */
    public function products(Request $request)
    {
        /*
        |------------------------------------------------------------------
        | 1. Authenticate member (authoritative, never client-provided)
        |------------------------------------------------------------------
        */
        $member = $request->user();

        if (!$member || !isset($member->member_id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        /*
        |------------------------------------------------------------------
        | 2. Fetch active loan types only
        |------------------------------------------------------------------
        | loan_type_deleted = 'N' → authoritative active filter
        |------------------------------------------------------------------
        */
        $loanTypes = DB::table('sacco_loan_types')
            ->where('loan_type_deleted', 'N')
            ->orderBy('loan_type_name')
            ->get()
            ->map(function ($t) {
                return [
                    // 🔑 Canonical identifier (never trust name)
                    'loan_type_id' => (int) $t->loan_type_id,

                    // 🏷 UI-facing fields
                    'loan_name'    => $t->loan_type_name,
                    'loan_code'    => $t->loan_type_code,

                    // 💰 Financial constraints (authoritative)
                    'interest_rate'        => (float) $t->loan_type_interest,
                    'interest_type'        => $t->loan_type_interest_type,
                    'max_amount'           => (float) $t->loan_type_max_amount,
                    'duration_months'      => (int) $t->loan_type_duration,

                    // 📋 Qualification hints (non-enforcing, UI guidance only)
                    'qualification_period' => (int) $t->loan_type_qualification_period,
                    'instant_qualification'=> $t->loan_type_instant_qualification === 'Y',

                    // 🔒 Flags (future use)
                    'insurable'            => $t->loan_type_insurable === 'Y',
                    'share_factor'         => (int) $t->loan_type_share_factor,
                ];
            })
            ->values();

        /*
        |------------------------------------------------------------------
        | 3. Final response (Loan Application – Products Contract)
        |------------------------------------------------------------------
        */
        return response()->json([
            'loan_products' => $loanTypes,
        ]);
    }
}
