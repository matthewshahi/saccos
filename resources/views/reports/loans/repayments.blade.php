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
                <li><a href="{{ route('admin.periods') }}">{{ $currentPeriod->period_name }}</a></li>
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
                <h3 class="w-50 float-start card-title m-0">Search Filters</h3>
            </div>
            <div class="card-body">

                <form method="GET" action="{{ url()->current() }}">
                    <div class="row">

                        <div class="col-md-3 form-group mb-3">
                            <label for="startPeriod">Start Period (YYYYMM)</label>
                            <input
                                class="form-control"
                                id="startPeriod"
                                name="startPeriod"
                                type="text"
                                value="{{ old('startPeriod', $startPeriod ?? '') }}"
                                placeholder="e.g. 202601"
                            >
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="endPeriod">End Period (YYYYMM)</label>
                            <input
                                class="form-control"
                                id="endPeriod"
                                name="endPeriod"
                                type="text"
                                value="{{ old('endPeriod', $endPeriod ?? '') }}"
                                placeholder="e.g. 202603"
                            >
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="searchName">Member Name</label>
                            <input
                                class="form-control"
                                id="searchName"
                                name="searchName"
                                type="text"
                                value="{{ old('searchName', $searchName ?? '') }}"
                                placeholder="Search by member name"
                            >
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="searchCompany">Company</label>
                            <input
                                class="form-control"
                                id="searchCompany"
                                name="searchCompany"
                                type="text"
                                value="{{ old('searchCompany', $searchCompany ?? '') }}"
                                placeholder="Search by company"
                            >
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="searchLoanType">Loan Type</label>
                            <input
                                class="form-control"
                                id="searchLoanType"
                                name="searchLoanType"
                                type="text"
                                value="{{ old('searchLoanType', $searchLoanType ?? '') }}"
                                placeholder="Search by loan type"
                            >
                        </div>

                        <div class="col-md-12 mt-2">
                            <button type="submit" class="btn btn-primary">
                                Search Report
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
                <h3 class="w-50 float-start card-title m-0">Loan Repayments</h3>
            </div>
            <div class="card-body">

                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-sm" style="white-space: nowrap;">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Member Name</th>
                                <th>Phone Number</th>
                                <th>Sacco ID</th>
                                <th>Company</th>
                                <th>Loan Type</th>
                                <th>Loan Category</th>
                                <th class="text-right">Loan Amount</th>
                                <th class="text-right">Insurance</th>
                                <th class="text-right">Commission</th>
                                <th class="text-right">EMI</th>
                                <th>Repayment Period</th>
                                <th class="text-right">Current Balance</th>
                                <th class="text-right">Amount Paid</th>
                                <th>Paid On</th>
                                <th>Document Number</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($loanRepayments as $index => $repayment)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $repayment->member_name ?? '-' }}</td>
                                    <td>{{ $repayment->member_phone_no ?? '-' }}</td>
                                    <td>{{ $repayment->member_sacco_id ?? '-' }}</td>
                                    <td>{{ $repayment->company_name ?? '-' }}</td>
                                    <td>{{ $repayment->loan_type_name ?? '-' }}</td>
                                    <td>{{ $repayment->loan_category_name ?? '-' }}</td>

                                    <td class="text-right">
                                        {{ number_format((float) ($repayment->loan_amount ?? 0), 2) }}
                                    </td>

                                    <td class="text-right">
                                        {{ number_format((float) ($repayment->loan_insurance ?? 0), 2) }}
                                    </td>

                                    <td class="text-right">
                                        {{ number_format((float) ($repayment->loan_commision ?? 0), 2) }}
                                    </td>

                                    <td class="text-right">
                                        {{ number_format((float) ($repayment->loan_monthly_repayment_amount ?? 0), 2) }}
                                    </td>

                                    <td>{{ $repayment->loan_payments_period ?? '-' }}</td>

                                    <td class="text-right">
                                        {{ number_format((float) ($repayment->current_balance ?? 0), 2) }}
                                    </td>

                                    <td class="text-right">
                                        {{ number_format((float) ($repayment->payment_amount ?? 0), 2) }}
                                    </td>

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
                                        No loan repayments found for the selected filters.
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