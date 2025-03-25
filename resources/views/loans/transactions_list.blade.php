@extends('layouts.app')

@section('content')
<style>
    td, th {
        white-space: nowrap;
    }
    td.text-left, th.text-left {
        text-align: left !important;
    }
    td.text-right, th.text-right {
        text-align: right !important;
    }
</style>

<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Transactions for Batch: {{ $batch->batch_reference }}</h1>
    <div class="header-part-right">
        <ul>
            @if(Auth::check())
                <li>{{ Auth::user()->name ?? 'User' }}</li>
            @endif
            @if(isset($currentPeriod))
                <li><a href="#">{{ $currentPeriod->period_name }}</a></li>
            @endif
            <li><i class="i-Full-Screen header-icon d-none d-sm-inline-block" data-fullscreen=""></i></li>
        </ul>
    </div>
</div>
<div class="separator-breadcrumb border-top"></div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

<div class="col-md-12 mb-3">
    <div class="card text-start">
        <div class="card-body">
            <div class="d-flex justify-content-between mb-3">
                <a href="{{ route('loans.batches') }}" class="btn btn-secondary">Back to Batches</a>
                <a href="{{ route('loans.batch.transactions.add_view', $batch->batch_id) }}" class="btn btn-primary">Add New Transaction</a>
            </div>
        </div>
    </div>
</div>

<div class="card o-hidden mb-4">
    <div class="card-header d-flex align-items-center">
        <h3 class="w-50 float-start card-title m-0">Loan Batch Transactions</h3>
        
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead class="text-center">
                    <tr>
                        <th>#</th>
                        <th class="text-left">Member Name</th>
                        <th class="text-left">Member No</th>
                        <th class="text-left">Loan Type</th>
                        <th class="text-right">Loan Amount</th>
                        <th class="text-right">Interest</th>
                        <th class="text-right">Total Payable</th>
                        <th class="text-right">Insurance</th>
                        <th class="text-right">Commission</th>
                        <th class="text-right">Duration</th>
                        <th class="text-right">Monthly Repayment</th>
                        <th class="text-left">Repayment Start</th>
                        <th class="text-left">Loan Category</th>
                        <th class="text-left">Doc No</th>
                        <th class="text-left">Description</th>
                        <th class="text-left">Transaction Date</th>
                        <th class="text-left">Entered By</th>
                        <th class="text-left">Created At</th>
                        <th class="text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($transactions as $index => $transaction)
                        <tr>
                            <th class="text-center" scope="row">{{ $index + 1 }}</th>
                            <td class="text-left">{{ $transaction->member_name ?? '-' }}</td>
                            <td class="text-left">{{ $transaction->member_id ?? '-' }}</td>
                            <td class="text-left">{{ $transaction->loan_type_name ?? '-' }}</td>
                            <td class="text-right">{{ number_format($transaction->batch_trans_loan_amount, 2) }}</td>
                            <td class="text-right">{{ number_format($transaction->batch_trans_interest_amount ?? 0, 2) }}</td>
                            <td class="text-right">{{ number_format($transaction->batch_trans_total_payable ?? 0, 2) }}</td>
                            <td class="text-right">{{ number_format($transaction->batch_trans_insurance ?? 0, 2) }}</td>
                            <td class="text-right">{{ number_format($transaction->batch_trans_commission ?? 0, 2) }}</td>
                            <td class="text-right">{{ $transaction->batch_trans_loan_duration ?? '-' }}</td>
                            <td class="text-right">{{ number_format($transaction->batch_trans_monthly_payment ?? 0, 2) }}</td>
                            <td class="text-left">{{ !empty($transaction->batch_trans_repayment_start) ? \Carbon\Carbon::parse($transaction->batch_trans_repayment_start)->format('d/m/Y') : 'N/A' }}</td>
                            <td class="text-left">{{ $transaction->loan_category_name ?? '-' }}</td>
                            <td class="text-left">{{ $transaction->batch_trans_doc_no ?? '-' }}</td>
                            <td class="text-left">{{ $transaction->batch_trans_description ?? '-' }}</td>
                            <td class="text-left">{{ !empty($transaction->batch_trans_on) ? \Carbon\Carbon::parse($transaction->batch_trans_on)->format('d/m/Y') : 'N/A' }}</td>
                            <td class="text-left">{{ $transaction->user_name ?? 'System' }}</td>
                            <td class="text-left">{{ !empty($transaction->created_at) ? \Carbon\Carbon::parse($transaction->created_at)->format('d/m/Y H:i') : 'N/A' }}</td>
                            <td class="text-left">
                                <a class="text-success me-2" href="{{ route('loans.batch.transactions.edit', [$batch->batch_id, $transaction->batch_trans_id]) }}">
                                    <i class="nav-icon i-Pen-2 fw-bold"></i>
                                </a>
                                <a class="text-danger me-2" href="{{ route('loans.batch.transactions.delete', $transaction->batch_trans_id) }}" onclick="return confirm('Are you sure you want to delete this transaction?')">
                                    <i class="nav-icon i-Close-Window fw-bold"></i>
                                </a>
                            </td>
                        </tr>
                    @endforeach
                    <tr class="fw-bold bg-light">
                        <td colspan="4" class="text-end">TOTALS:</td>
                        <td class="text-right">{{ number_format($transactions->sum('batch_trans_loan_amount'), 2) }}</td>
                        <td class="text-right">{{ number_format($transactions->sum('batch_trans_interest_amount'), 2) }}</td>
                        <td class="text-right">{{ number_format($transactions->sum('batch_trans_total_payable'), 2) }}</td>
                        <td class="text-right">{{ number_format($transactions->sum('batch_trans_insurance'), 2) }}</td>
                        <td class="text-right">{{ number_format($transactions->sum('batch_trans_commission'), 2) }}</td>
                        <td colspan="10"></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection