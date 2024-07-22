@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Approve Loans</h1>
</div>
<div class="separator-breadcrumb border-top"></div>

<div class="row mb-4">
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-body">
                <div class="card-title mb-3">Loans Pending Approval</div>
                <div class="table-responsive">
                    <table class="display table table-striped table-bordered" style="width: 100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Member</th>
                                <th>Sacco ID</th>
                                <th>Loan Type</th>
                                <th>Loan Category</th>
                                <th>Loan Amount</th>
                                <th>Insurance</th>
                                <th>Commission</th>
                                <th>EMI</th>
                                <th>Period</th>
                                <th>Top-Up</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loans as $index => $loan)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $loan->member_name }}</td>
                                    <td>{{ $loan->member_sacco_id }}</td>
                                    <td>{{ $loan->loan_type_name }}</td>
                                    <td>{{ $loan->loan_category_name }}</td>
                                    <td>{{ number_format($loan->batch_trans_loan_amount, 2) }}</td>
                                    <td>{{ number_format($loan->batch_trans_insurance, 2) }}</td>
                                    <td>{{ number_format($loan->batch_trans_commission, 2) }}</td>
                                    <td>{{ number_format($loan->batch_trans_monthly_payment, 2) }}</td>
                                    <td>{{ $loan->batch_trans_loan_duration }} months</td>
                                    <td>{{ $loan->batch_trans_loan_to_top_up }}</td>
                                    <td>
                                        <a href="{{ route('loans.approve', $loan->batch_trans_id) }}" class="btn btn-success btn-sm">Approve</a>
                                        <a href="{{ route('loans.delete', $loan->batch_trans_id) }}" class="btn btn-danger btn-sm">Delete</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
