<?php

namespace App\Http\Controllers;

use App\Jobs\RecalculateLoanPaidJob;
use App\Jobs\ResetGuarantorsJob;
use App\Jobs\UpdateMembersLoanBalancesJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Throwable;

class LoanReprocessController extends Controller
{
    public function index()
    {
        return view('loans.reprocess.index');
    }

    public function recalculateLoanPaid(): RedirectResponse
    {
        try {
            RecalculateLoanPaidJob::dispatch();

            Log::info('mugera_Reprocess: RecalculateLoanPaidJob dispatched from reprocess menu.', [
                'user_id' => auth()->id(),
            ]);

            return redirect()
                ->route('loans.reprocess.index')
                ->with('success', 'Recalculate Loan Paid job has been queued successfully.');
        } catch (Throwable $e) {
            Log::error('mugera_Reprocess: Failed to dispatch RecalculateLoanPaidJob.', [
                'user_id' => auth()->id(),
                'error'   => $e->getMessage(),
            ]);

            return redirect()
                ->route('loans.reprocess.index')
                ->with('error', 'Failed to queue Recalculate Loan Paid job. ' . $e->getMessage());
        }
    }

    public function resetGuarantors(): RedirectResponse
    {
        try {
            ResetGuarantorsJob::dispatch();

            Log::info('mugera_Reprocess: ResetGuarantorsJob dispatched from reprocess menu.', [
                'user_id' => auth()->id(),
            ]);

            return redirect()
                ->route('loans.reprocess.index')
                ->with('success', 'Reset Guarantors job has been queued successfully.');
        } catch (Throwable $e) {
            Log::error('mugera_Reprocess: Failed to dispatch ResetGuarantorsJob.', [
                'user_id' => auth()->id(),
                'error'   => $e->getMessage(),
            ]);

            return redirect()
                ->route('loans.reprocess.index')
                ->with('error', 'Failed to queue Reset Guarantors job. ' . $e->getMessage());
        }
    }

    public function updateMemberLoanBalances(): RedirectResponse
    {
        try {
            UpdateMembersLoanBalancesJob::dispatch();

            Log::info('mugera_Reprocess: UpdateMembersLoanBalancesJob dispatched from reprocess menu.', [
                'user_id' => auth()->id(),
            ]);

            return redirect()
                ->route('loans.reprocess.index')
                ->with('success', 'Update Member Loan Balances job has been queued successfully.');
        } catch (Throwable $e) {
            Log::error('mugera_Reprocess: Failed to dispatch UpdateMembersLoanBalancesJob.', [
                'user_id' => auth()->id(),
                'error'   => $e->getMessage(),
            ]);

            return redirect()
                ->route('loans.reprocess.index')
                ->with('error', 'Failed to queue Update Member Loan Balances job. ' . $e->getMessage());
        }
    }

    public function runAll(): RedirectResponse
    {
        try {
            Bus::chain([
                new RecalculateLoanPaidJob(),
                new ResetGuarantorsJob(),
                new UpdateMembersLoanBalancesJob(),
            ])->dispatch();

            Log::info('mugera_Reprocess: Loan reprocess chain dispatched from reprocess menu.', [
                'user_id' => auth()->id(),
                'sequence' => [
                    'RecalculateLoanPaidJob',
                    'ResetGuarantorsJob',
                    'UpdateMembersLoanBalancesJob',
                ],
            ]);

            return redirect()
                ->route('loans.reprocess.index')
                ->with('success', 'Loan reprocess chain has been queued successfully: loan paid totals, guarantors, then member loan balances.');
        } catch (Throwable $e) {
            Log::error('mugera_Reprocess: Failed to dispatch loan reprocess chain.', [
                'user_id' => auth()->id(),
                'error'   => $e->getMessage(),
            ]);

            return redirect()
                ->route('loans.reprocess.index')
                ->with('error', 'Failed to queue loan reprocess chain. ' . $e->getMessage());
        }
    }
}