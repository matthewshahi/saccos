@extends('layouts.app')

@section('content')
@include("member_name")
@include("dashboard.junior-context-banner")
<div class="row">
    <div class="col-md-12">
        <h3 class="mb-4">Loans Taken</h3>

        @foreach ($data['loans'] as $loan)
        <!-- Loan Header -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div>
                        <h5 class="mb-0">{{ $loan->loan_type_name }} ({{ $loan->loan_id }})</h5>
                        <p class="text-muted mb-0">{{ $loan->loan_category_name }}</p>
                    </div>
                    <div class="text-end">
                        <p class="mb-0"><strong>Loan Taken:</strong> Ksh {{ number_format($loan->loan_amount, 2) }}</p>
                        <p class="mb-0"><strong>Loan Paid:</strong> Ksh {{ number_format($loan->loan_loan_paid, 2) }}</p>
                        <p class="mb-0"><strong>Commission:</strong> Ksh {{ number_format($loan->loan_commision, 2) }}</p>
                        <p class="mb-0"><strong>Insurance:</strong> Ksh {{ number_format($loan->loan_insurance, 2) }}</p>
                        <p class="mb-0"><strong>Balance:</strong> Ksh {{ number_format($loan->loan_balance, 2) }}</p>
                    </div>
                </div>
                <div class="text-muted mb-3">
                    <strong>Description:</strong> {{ $loan->loan_description }}<br>
                    <strong>Period Taken:</strong> {{ substr($loan->loan_taken_period, 0, 4) . '-' . substr($loan->loan_taken_period, 4) }}<br>
                    <strong>Doc No:</strong> {{ $loan->loan_doc_no }}
                </div>

                <!-- Repayment Table -->
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Period</th>
                                <th>Date Paid</th>
                                <th>Document No</th>
                                <th>Description</th>
                                <th class="text-end">Principal (Ksh)</th>
                                <th class="text-end">Interest (Ksh)</th>
                                <th class="text-end">Total Paid (Ksh)</th>
                                <th class="text-end">Outstanding Loan (Ksh)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @if ($loan->repayments->isNotEmpty())
                                @foreach ($loan->repayments as $index => $repayment)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ substr($repayment->loan_payments_period, 0, 4) . '-' . substr($repayment->loan_payments_period, 4) }}</td>
                                        <td>{{ date('d-m-Y', strtotime($repayment->loan_payments_paid_on)) }}</td>
                                        <td>{{ $repayment->loan_payments_docno }}</td>
                                        <td>{{ $repayment->loan_payments_description }}</td>
                                        <td class="text-end">{{ number_format($repayment->loan_payments_amount, 2) }}</td>
                                        <td class="text-end">{{ number_format($repayment->loan_payments_interest, 2) }}</td>
                                        <td class="text-end">{{ number_format($repayment->loan_payments_amount + $repayment->loan_payments_interest, 2) }}</td>
                                        <td class="text-end">{{ number_format($repayment->outstanding_balance, 2) }}</td>
                                    </tr>
                                @endforeach
                            @else
                                <tr>
                                    <td colspan="9" class="text-muted text-center">No repayments available</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endforeach

        @if ($data['loans']->isEmpty())
        <div class="text-center text-muted">No loans available.</div>
        @endif
    </div>
</div>
@endsection