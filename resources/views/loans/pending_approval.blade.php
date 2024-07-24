@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>List of Loans Taken by a Member</h1>
    <div class="header-part-right">
        <ul>
            @if(Auth::check())
                <li>{{ Auth::user()->member_name }}</li>
            @endif
            @if(isset($currentPeriod))
                <li><a href="{{ route('admin.periods') }}">{{ $currentPeriod->period_name }}</a></li>
            @endif
            <li><i class="i-Full-Screen header-icon d-none d-sm-inline-block" data-fullscreen=""></i></li>
        </ul>
    </div>
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
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="col-md-12 mb-3 d-flex justify-content-center">
        <small>
            <a href="{{ route('loans.guarantee.requests') }}">Guarantee requests</a> |
            <a href="{{ route('loans.pending.approval') }}">List loans pending approval</a> |
            <a href="{{ route('admin.loans.pending.approval') }}">[ADMIN] List all loans pending approval</a>
        </small>
    </div>

    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-body">
                <div class="card-title mb-3">Loans Pending Approval</div>
                <form id="form1" name="form1" method="post" action="">
                    @csrf
                    <table class="display table table-striped table-bordered" style="width: 100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Member</th>
                                <th>Sacco ID</th>
                                <th>Loan type</th>
                                <th>Loan Category</th>
                                <th>Loan Amount</th>
                                <th>Insurance</th>
                                <th>Commission</th>
                                <th>EMI</th>
                                <th>Period</th>
                                <th>Top-Up</th>
                                
                                <th>Delete</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loans as $index => $loan)
                                <tr class="{{ $index % 2 == 0 ? 'row-a' : 'row-b' }}">
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $loan->member_name }}</td>
                                    <td>{{ $loan->member_sacco_id }}</td>
                                    <td>{{ $loan->loan_type_name }}</td>
                                    <td>{{ $loan->loan_category_name }}</td>
                                    <td>{{ number_format($loan->batch_trans_loan_amount, 2) }}</td>
                                    <td>{{ number_format($loan->batch_trans_insurance, 2) }}</td>
                                    <td>{{ number_format($loan->batch_trans_commission, 2) }}</td>
                                    <td>{{ number_format($loan->batch_trans_monthly_payment, 2) }}</td>
                                    <td>{{ $loan->batch_trans_loan_duration }}</td>
                                    <td>
                                        @if(is_numeric($loan->batch_trans_loan_to_top_up) && $loan->batch_trans_loan_to_top_up > 0)
                                            @php
                                                $topUpLoan = DB::table('sacco_loans')
                                                    ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
                                                    ->where('loan_member', Auth::id())
                                                    ->where('loan_id', $loan->batch_trans_loan_to_top_up)
                                                    ->first();
                                            @endphp
                                            {{ $topUpLoan->loan_type_name ?? '' }} - ({{ $loan->batch_trans_loan_to_top_up }})
                                        @endif
                                    </td>
                                     
                                    <td>
                                        <a href="{{ route('loans.pending.approval', ['did' => $loan->batch_trans_id]) }}">
                                            <i class="i-Close"></i>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    function doYouWantTo(route, memberId, loanId, action) {
        let confirmMessage = (action === 'edit') ? 'Do you want to edit this loan?' : 'Do you want to delete this loan?';
        if (confirm(confirmMessage)) {
            // Redirect to the respective route
            window.location.href = `${route}?memberId=${memberId}&loanId=${loanId}&action=${action}`;
        }
    }
</script>
@endsection
