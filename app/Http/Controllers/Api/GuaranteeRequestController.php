<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GuaranteeRequestController extends Controller
{
    /**
     * GET /api/auth/guarantee-requests
     * List only pending guarantee requests for the logged-in guarantor.
     */
    public function index(Request $request)
    {
        $member = $request->user();

        if (!$member || !isset($member->member_id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $memberId = (int) $member->member_id;

        $rows = $this->basePendingQuery($memberId)
            ->orderByDesc('requested_at')
            ->get();

        return response()->json([
            'count' => $rows->count(),
            'requests' => $rows->map(fn ($row) => $this->mapGuaranteeRow($row))->values(),
        ]);
    }

    /**
     * GET /api/auth/guarantee-requests/summary
     * Lightweight dashboard summary.
     */
    public function summary(Request $request)
    {
        $member = $request->user();

        if (!$member || !isset($member->member_id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $memberId = (int) $member->member_id;

        $rows = $this->basePendingQuery($memberId)->get();

        $count = $rows->count();
        $totalGuaranteed = (float) $rows->sum(function ($r) {
            return (float) ($r->my_guaranteed_amount ?? 0);
        });

        return response()->json([
            'count' => $count,
            'has_pending' => $count > 0,
            'total_guaranteed_amount' => round($totalGuaranteed, 2),
        ]);
    }

    /**
     * GET /api/auth/guarantee-requests/{id}
     * Show one pending request that belongs to the logged-in guarantor.
     *
     * IMPORTANT:
     * {id} here means batch_trans_id, not guarantors_id.
     */
    public function show(Request $request, int $id)
    {
        $member = $request->user();

        if (!$member || !isset($member->member_id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $memberId = (int) $member->member_id;

        $row = $this->basePendingQuery($memberId)
            ->where('bt.batch_trans_id', $id)
            ->first();

        if (!$row) {
            return response()->json([
                'message' => 'Guarantee request not found.',
            ], 404);
        }

        return response()->json([
            'request' => $this->mapGuaranteeRow($row),
        ]);
    }

    /**
     * POST /api/auth/guarantee-requests/{id}/approve
     * Approve only the logged-in guarantor's own pending request(s).
     */
    public function approve(Request $request, int $id)
    {
        $member = $request->user();

        if (!$member || !isset($member->member_id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $memberId = (int) $member->member_id;

        // Verify the pending request belongs to this exact member
        $exists = $this->pendingActionExists($memberId, $id);

        if (!$exists) {
            return response()->json([
                'message' => 'Guarantee request not found or already actioned.',
            ], 404);
        }

        $updated = DB::table('sacco_loan_batch_guarantors_members as g')
            ->where('g.guarantors_loan_batch_trans_id', $id)
            ->where('g.guarantors_guarantor_id', $memberId)
            ->where('g.guarantors_approved', '<>', 'Y')
            ->where('g.guarantors_deleted', '<>', 'Y')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('sacco_loan_batch_trans_members as bt')
                    ->whereColumn('bt.batch_trans_id', 'g.guarantors_loan_batch_trans_id')
                    ->where('bt.batch_trans_updated', '<>', 'Y')
                    ->where('bt.batch_trans_deleted', '<>', 'Y');
            })
            ->update([
                'guarantors_approved' => 'Y',
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Guarantee request approved successfully.',
            'updated_rows' => $updated,
        ]);
    }

    /**
     * POST /api/auth/guarantee-requests/{id}/decline
     * Decline only the logged-in guarantor's own pending request(s).
     */
    public function decline(Request $request, int $id)
    {
        $member = $request->user();

        if (!$member || !isset($member->member_id)) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        }

        $memberId = (int) $member->member_id;

        // Verify the pending request belongs to this exact member
        $exists = $this->pendingActionExists($memberId, $id);

        if (!$exists) {
            return response()->json([
                'message' => 'Guarantee request not found or already actioned.',
            ], 404);
        }

        $updated = DB::table('sacco_loan_batch_guarantors_members as g')
            ->where('g.guarantors_loan_batch_trans_id', $id)
            ->where('g.guarantors_guarantor_id', $memberId)
            ->where('g.guarantors_approved', '<>', 'Y')
            ->where('g.guarantors_deleted', '<>', 'Y')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('sacco_loan_batch_trans_members as bt')
                    ->whereColumn('bt.batch_trans_id', 'g.guarantors_loan_batch_trans_id')
                    ->where('bt.batch_trans_updated', '<>', 'Y')
                    ->where('bt.batch_trans_deleted', '<>', 'Y');
            })
            ->update([
                'guarantors_deleted'     => 'Y',
                'guarantors_deleted_by'  => $memberId,
                'guarantors_deleted_on'  => now(),
                'guarantors_deleted_ip'  => $request->ip(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Guarantee request declined successfully.',
            'updated_rows' => $updated,
        ]);
    }

    /**
     * Core pending guarantee query.
     * This query is always scoped to the logged-in guarantor only.
     */
    private function basePendingQuery(int $memberId)
    {
        return DB::table('sacco_loan_batch_guarantors_members as g')
            ->join(
                'sacco_loan_batch_trans_members as bt',
                'bt.batch_trans_id',
                '=',
                'g.guarantors_loan_batch_trans_id'
            )
            ->join(
                'sacco_loan_types as lt',
                'bt.batch_trans_loan_type',
                '=',
                'lt.loan_type_id'
            )
            ->join(
                'sacco_loan_category as lc',
                'bt.batch_trans_loan_category',
                '=',
                'lc.loan_category_id'
            )
            ->join(
                'sacco_members as m',
                'bt.batch_trans_member_id',
                '=',
                'm.member_id'
            )
            ->leftJoin(
                'sacco_loans as tl',
                'bt.batch_trans_loan_to_top_up',
                '=',
                'tl.loan_id'
            )
            ->leftJoin(
                'sacco_loan_types as tlt',
                'tl.loan_loan_type',
                '=',
                'tlt.loan_type_id'
            )
            ->where('g.guarantors_guarantor_id', $memberId)
            ->where('g.guarantors_approved', '<>', 'Y')
            ->where('g.guarantors_deleted', '<>', 'Y')
            ->where('bt.batch_trans_updated', '<>', 'Y')
            ->where('bt.batch_trans_deleted', '<>', 'Y')
            ->groupBy(
                'bt.batch_trans_id',
                'bt.batch_trans_member_id',
                'm.member_name',
                'm.member_sacco_id',
                'lt.loan_type_name',
                'lc.loan_category_name',
                'bt.batch_trans_loan_amount',
                'bt.batch_trans_insurance',
                'bt.batch_trans_commission',
                'bt.batch_trans_monthly_payment',
                'bt.batch_trans_loan_duration',
                'bt.batch_trans_description',
                'bt.batch_trans_loan_to_top_up',
                'bt.batch_trans_loan_to_top_up_amount',
                'tlt.loan_type_name'
            )
            ->selectRaw('
                bt.batch_trans_id,
                bt.batch_trans_member_id,
                m.member_name as borrower_name,
                m.member_sacco_id as borrower_member_number,

                lt.loan_type_name,
                lc.loan_category_name,

                bt.batch_trans_loan_amount,
                bt.batch_trans_insurance,
                bt.batch_trans_commission,
                bt.batch_trans_monthly_payment,
                bt.batch_trans_loan_duration,
                bt.batch_trans_description,

                bt.batch_trans_loan_to_top_up,
                bt.batch_trans_loan_to_top_up_amount,
                tlt.loan_type_name as topup_loan_type_name,

                COALESCE(SUM(g.guarantors_amount_guaranteed), 0) as my_guaranteed_amount,
                MAX(g.guarantors_on) as requested_at
            ');
    }

    /**
     * Check whether the logged-in guarantor has a pending actionable request
     * for the given batch transaction ID.
     */
    private function pendingActionExists(int $memberId, int $batchTransId): bool
    {
        return DB::table('sacco_loan_batch_guarantors_members as g')
            ->where('g.guarantors_loan_batch_trans_id', $batchTransId)
            ->where('g.guarantors_guarantor_id', $memberId)
            ->where('g.guarantors_approved', '<>', 'Y')
            ->where('g.guarantors_deleted', '<>', 'Y')
            ->whereExists(function ($q) {
                $q->select(DB::raw(1))
                    ->from('sacco_loan_batch_trans_members as bt')
                    ->whereColumn('bt.batch_trans_id', 'g.guarantors_loan_batch_trans_id')
                    ->where('bt.batch_trans_updated', '<>', 'Y')
                    ->where('bt.batch_trans_deleted', '<>', 'Y');
            })
            ->exists();
    }

    /**
     * Normalize one row for mobile response.
     */
    private function mapGuaranteeRow($row): array
    {
        return [
            'id' => (int) $row->batch_trans_id,
            'status' => 'pending',

            'requested_at' => !empty($row->requested_at)
                ? Carbon::parse($row->requested_at)->toDateTimeString()
                : null,

            'borrower' => [
                'member_id' => (int) $row->batch_trans_member_id,
                'name' => (string) $row->borrower_name,
                'member_number' => (string) $row->borrower_member_number,
            ],

            'loan' => [
                'type' => (string) $row->loan_type_name,
                'category' => (string) $row->loan_category_name,
                'amount' => (float) ($row->batch_trans_loan_amount ?? 0),
                'insurance' => (float) ($row->batch_trans_insurance ?? 0),
                'commission' => (float) ($row->batch_trans_commission ?? 0),
                'monthly_payment' => (float) ($row->batch_trans_monthly_payment ?? 0),
                'duration_months' => (int) ($row->batch_trans_loan_duration ?? 0),
                'description' => (string) ($row->batch_trans_description ?? ''),
            ],

            'top_up' => (
                !empty($row->batch_trans_loan_to_top_up) &&
                (int) $row->batch_trans_loan_to_top_up > 0
            ) ? [
                'loan_id' => (int) $row->batch_trans_loan_to_top_up,
                'loan_type' => (string) ($row->topup_loan_type_name ?? 'N/A'),
                'amount' => (float) ($row->batch_trans_loan_to_top_up_amount ?? 0),
            ] : null,

            'my_guarantee' => [
                'amount' => (float) ($row->my_guaranteed_amount ?? 0),
            ],
        ];
    }
}