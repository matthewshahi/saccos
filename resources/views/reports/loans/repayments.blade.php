@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Loan Repayments Report</h1>
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
                <div class="card-title mb-3">Loan Repayments Report</div>
                <div class="col-md-12">
                    <div class="card mb-4">
                        <div class="card-body">
                            <div class="card-title mb-3">Search Filters</div>
                            <form id="searchForm" method="GET" action="{{ route('reports.loans.repayments') }}">
                                <div class="row">
                                    <div class="col-md-3 form-group mb-3">
                                        <label for="startPeriod">Start Period (YYYYMM)</label>
                                        <input class="form-control" id="startPeriod" name="startPeriod" type="text" placeholder="Start Period" value="{{ request('startPeriod', $startPeriod) }}">
                                    </div>
                                    <div class="col-md-3 form-group mb-3">
                                        <label for="endPeriod">End Period (YYYYMM)</label>
                                        <input class="form-control" id="endPeriod" name="endPeriod" type="text" placeholder="End Period" value="{{ request('endPeriod', $endPeriod) }}">
                                    </div>
                                    <div class="col-md-3 form-group mb-3">
                                        <label for="searchName">Member Name</label>
                                        <input class="form-control" id="searchName" name="searchName" type="text" placeholder="Search by Member Name" value="{{ request('searchName', $searchName) }}">
                                    </div>
                                    <div class="col-md-3 form-group mb-3">
                                        <label for="searchCompany">Company</label>
                                        <input class="form-control" id="searchCompany" name="searchCompany" type="text" placeholder="Search by Company" value="{{ request('searchCompany', $searchCompany) }}">
                                    </div>
                                    <div class="col-md-3 form-group mb-3">
                                        <label for="searchLoanType">Loan Type</label>
                                        <input class="form-control" id="searchLoanType" name="searchLoanType" type="text" placeholder="Search by Loan Type" value="{{ request('searchLoanType', $searchLoanType) }}">
                                    </div>
                                    <div class="col-md-12">
                                        <button type="submit" class="btn btn-primary">Search</button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div id="loan-repayment-table-container" style="overflow-x: auto;">
                    <table class="table table-bordered" id="loan-repayment-table" style="white-space: nowrap;">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Member Name</th>
                                <th>Phone Number</th>
                                <th>Sacco ID</th>
                                <th>Loan Type</th>
                                <th class="text-right">Loan Amount</th>
                                <th class="text-right">Loan Balance</th>
                                <th class="text-right">Amount Paid</th>
                                <th>Period Paid</th>
                                <th>Paid On</th>
                                <th>Document Number</th>
                            </tr>
                        </thead>
                        <tbody id="loan-repayment-data">
                            @php
                                $totalLoanAmount = 0;
                                $totalLoanBalance = 0;
                                $totalAmountPaid = 0;
                            @endphp
                            @foreach($loanRepayments as $index => $repayment)
                                @php
                                    $loanBalance = $repayment->loan_amount - $repayment->loan_loan_paid;
                                    $totalLoanAmount += $repayment->loan_amount;
                                    $totalLoanBalance += $loanBalance;
                                    $totalAmountPaid += $repayment->loan_payments_amount;
                                @endphp
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $repayment->member_name }}</td>
                                    <td>{{ $repayment->member_phone_no }}</td>
                                    <td>{{ $repayment->member_sacco_id }}</td>
                                    <td>{{ $repayment->loan_type_name }}</td>
                                    <td class="text-right">{{ number_format($repayment->loan_amount, 2) }}</td>
                                    <td class="text-right">{{ number_format($loanBalance, 2) }}</td>
                                    <td class="text-right">{{ number_format($repayment->loan_payments_amount, 2) }}</td>
                                    <td>{{ $repayment->loan_payments_period }}</td>
                                    <td>{{ \Carbon\Carbon::parse($repayment->loan_payments_paid_on)->format('Y-m-d') }}</td>
                                    <td>{{ $repayment->loan_payments_docno }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="5" class="text-right">Totals:</th>
                                <th class="text-right">{{ number_format($totalLoanAmount, 2) }}</th>
                                <th class="text-right">{{ number_format($totalLoanBalance, 2) }}</th>
                                <th class="text-right">{{ number_format($totalAmountPaid, 2) }}</th>
                                <th colspan="3"></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div id="loading-status" class="loading-status" style="display: none;">Loading...</div>
            </div>
        </div>
    </div>
</div>

<style>
    .loading-status {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 36px;
        font-weight: bold;
        color: #000;
        z-index: 9999;
        animation: blinkingText 1.2s infinite;
    }

    @keyframes blinkingText {
        0% { color: #000; }
        49% { color: #000; }
        50% { color: transparent; }
        99% { color: transparent; }
        100% { color: #000; }
    }

    .table {
        position: relative;
        z-index: 1;
    }
</style>

@endsection
