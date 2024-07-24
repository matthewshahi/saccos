@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Pending Loans for Approval</h1>
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
    <div class="col-md-12 mb-4">
        <div class="card text-start">
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                
                <div class="table-responsive">
                    <form method="post" action="">
                        <table class="table">
                            <thead>
                               
                                <tr class="row-a">
                                    <th>&nbsp;</th>
                                    <th>Member</th>
                                    <th>Sacco ID</th>
                                    <th>Loan type</th>
                                    <th>Loan Category</th>
                                    <th align="right">Loan Amount</th>
                                    <th align="right">Insurance</th>
                                    <th align="right">Commission</th>
                                    <th align="right">EMI</th>
                                    <th align="right">Period</th>
                                    <th>Top-Up</th>
                                    <th align="center">Approve &amp; Update</th>
                                    <th>&nbsp;</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($loans as $index => $loan)
                                    <tr class="{{ $index % 2 == 1 ? 'row-b' : 'row-a' }}">
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $loan->member_name }}</td>
                                        <td>{{ $loan->member_sacco_id }}</td>
                                        <td>{{ $loan->loan_type_name }}</td>
                                        <td>{{ $loan->loan_category_name }}</td>
                                        <td align="right">{{ number_format(round($loan->batch_trans_loan_amount, 2), 2) }}</td>
                                        <td align="right">{{ number_format(round($loan->batch_trans_insurance, 2), 2) }}</td>
                                        <td align="right">{{ number_format(round($loan->batch_trans_commission, 2), 2) }}</td>
                                        <td align="right">{{ number_format(round($loan->batch_trans_monthly_payment, 2), 2) }}</td>
                                        <td align="right">{{ $loan->batch_trans_loan_duration }}</td>
                                        <td>
                                            @if(is_numeric($loan->batch_trans_loan_to_top_up) && $loan->batch_trans_loan_to_top_up > 0)
                                                <?php
                                                $top_up_loan = DB::table('sacco_loans')
                                                    ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
                                                    ->where('loan_id', $loan->batch_trans_loan_to_top_up)
                                                    ->first();
                                                ?>
                                                {{ $top_up_loan->loan_type_name }} - ({{ $loan->batch_trans_loan_to_top_up }})
                                            @endif
                                        </td>
                                        <td align="center" nowrap="nowrap">
                                            <a href="?app={{ addslashes(trim(request()->query('app'))) }}&amp;id={{ $loan->batch_trans_member_id }}&amp;update={{ $loan->batch_trans_id }}">Y</a>
                                        </td>
                                        <td nowrap="nowrap">
                                            <a href="#" onclick="doYouWantTo('edit_loan_loans_member', {{ $loan->batch_trans_member_id }}, {{ $loan->batch_trans_id }}, 'edit')">
                                                <i class="nav-icon i-Bank-2 fw-bold"></i>
                                            </a>
                                        </td>
                                        <td nowrap="nowrap">
                                            <a href="#" onclick="doYouWantTo('{{ addslashes(trim(request()->query('app'))) }}', {{ $loan->batch_trans_member_id }}, {{ $loan->batch_trans_id }}, '9999')">
                                                <i class="nav-icon i-Close-Window fw-bold"></i>
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
</div>
@endsection
