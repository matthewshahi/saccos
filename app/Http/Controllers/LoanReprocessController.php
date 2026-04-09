<?php

namespace App\Http\Controllers;

use App\Jobs\ResetGuarantorsJob;
use App\Jobs\UpdateMembersLoanBalancesJob;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;
use Throwable;

class LoanReprocessController extends Controller
{
    public function index()
    {
        return view('loans.reprocess.index');
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
            ResetGuarantorsJob::dispatch();
            UpdateMembersLoanBalancesJob::dispatch();

            Log::info('mugera_Reprocess: Both loan reprocess jobs dispatched from reprocess menu.', [
                'user_id' => auth()->id(),
            ]);

            return redirect()
                ->route('loans.reprocess.index')
                ->with('success', 'Both reprocess jobs have been queued successfully.');
        } catch (Throwable $e) {
            Log::error('mugera_Reprocess: Failed to dispatch one or more reprocess jobs.', [
                'user_id' => auth()->id(),
                'error'   => $e->getMessage(),
            ]);

            return redirect()
                ->route('loans.reprocess.index')
                ->with('error', 'Failed to queue reprocess jobs. ' . $e->getMessage());
        }
    }
}