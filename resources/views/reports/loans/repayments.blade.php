@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Loan Repayments Report</h1>
    <div class="header-part-right">
        <ul>
            @if(Auth::check())
                <li>{{ Auth::user()->member_name }}</li>
            @endif

            @if(isset($currentPeriod) && $currentPeriod)
                <li>
                    <a href="{{ route('admin.periods') }}">{{ $currentPeriod->period_name }}</a>
                </li>
            @endif

            <li>
                <i class="i-Full-Screen header-icon d-none d-sm-inline-block" data-fullscreen=""></i>
            </li>
        </ul>
    </div>
</div>

<div class="separator-breadcrumb border-top"></div>

<div class="row">
    <div class="col-md-12">

        <div class="card o-hidden mb-4">
            <div class="card-header d-flex align-items-center">
                <h3 class="w-50 float-start card-title m-0">Search Loan Repayments</h3>
            </div>
            <div class="card-body">

                <form method="GET" action="{{ url()->current() }}">
                    <div class="row">

                        <div class="col-md-3 form-group mb-3">
                            <label for="startPeriod">Start Period</label>
                            <input
                                type="text"
                                class="form-control"
                                id="startPeriod"
                                name="startPeriod"
                                placeholder="YYYYMM"
                                value="{{ old('startPeriod', $startPeriod ?? '') }}"
                            >
                            <small class="text-muted">Example: 202601</small>
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="endPeriod">End Period</label>
                            <input
                                type="text"
                                class="form-control"
                                id="endPeriod"
                                name="endPeriod"
                                placeholder="YYYYMM"
                                value="{{ old('endPeriod', $endPeriod ?? '') }}"
                            >
                            <small class="text-muted">Example: 202603</small>
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="searchName">Member Name</label>
                            <input
                                type="text"
                                class="form-control"
                                id="searchName"
                                name="searchName"
                                placeholder="Search member name"
                                value="{{ old('searchName', $searchName ?? '') }}"
                            >
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="searchCompany">Company Name</label>
                            <input
                                type="text"
                                class="form-control"
                                id="searchCompany"
                                name="searchCompany"
                                placeholder="Search company name"
                                value="{{ old('searchCompany', $searchCompany ?? '') }}"
                            >
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="searchLoanType">Loan Type</label>
                            <input
                                type="text"
                                class="form-control"
                                id="searchLoanType"
                                name="searchLoanType"
                                placeholder="Search loan type"
                                value="{{ old('searchLoanType', $searchLoanType ?? '') }}"
                            >
                        </div>

                        <div class="col-md-12 mt-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="i-Magnifi-Glass1 me-1"></i> Search Report
                            </button>

                            <a href="{{ url()->current() }}" class="btn btn-light ms-2">
                                Reset
                            </a>
                        </div>

                    </div>
                </form>

            </div>
        </div>

        <div class="card o-hidden mb-4">
            <div class="card-header d-flex align-items-center">
                <h3 class="w-50 float-start card-title m-0">Loan Repayment Records</h3>
            </div>
            <div class="card-body">

               <div class="table-responsive" style="overflow-x: auto; overflow-y: visible; -webkit-overflow-scrolling: touch;">
    <table class="table table-bordered table-striped table-sm mb-0 repayment-report-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Member Name</th>
                <th>Phone</th>
                <th>Sacco ID</th>
                <th>Company Name</th>
                <th>Loan Type</th>
                <th>Loan Category</th>
                <th class="text-end">Loan Amount</th>
                <th class="text-end">Amount Repaid So Far</th>
                <th class="text-end">Loan Balance</th>
                <th class="text-end">Insurance</th>
                <th class="text-end">Commission</th>
                <th class="text-end">Monthly Repayment</th>
                <th>Payment Period</th>
                <th>Paid On</th>
                <th>Document No.</th>
            </tr>
        </thead>
        <tbody>
            @forelse($loanRepayments as $key => $repayment)
                <tr>
                    <td>{{ $key + 1 }}</td>
                    <td>{{ $repayment->member_name ?? '-' }}</td>
                    <td>{{ $repayment->member_phone_no ?? '-' }}</td>
                    <td>{{ $repayment->member_sacco_id ?? '-' }}</td>
                    <td>{{ $repayment->company_name ?? '-' }}</td>
                    <td>{{ $repayment->loan_type_name ?? '-' }}</td>
                    <td>{{ $repayment->loan_category_name ?? '-' }}</td>

                    <td class="text-end">{{ number_format((float) ($repayment->loan_amount ?? 0), 2) }}</td>
                    <td class="text-end">{{ number_format((float) ($repayment->loan_loan_paid ?? 0), 2) }}</td>
                    <td class="text-end">{{ number_format((float) ($repayment->loan_balance ?? 0), 2) }}</td>
                    <td class="text-end">{{ number_format((float) ($repayment->loan_insurance ?? 0), 2) }}</td>
                    <td class="text-end">{{ number_format((float) ($repayment->loan_commision ?? 0), 2) }}</td>
                    <td class="text-end">{{ number_format((float) ($repayment->loan_monthly_repayment_amount ?? 0), 2) }}</td>
                    <td>{{ $repayment->loan_payments_period ?? '-' }}</td>
                    <td>
                        @if(!empty($repayment->loan_payments_paid_on))
                            {{ \Carbon\Carbon::parse($repayment->loan_payments_paid_on)->format('d-m-Y') }}
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $repayment->loan_payments_docno ?? '-' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="16" class="text-center text-muted">
                        No loan repayment records found for the selected filters.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

            </div>
        </div>

    </div>
</div>
@endsection