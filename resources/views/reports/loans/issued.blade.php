@extends('layouts.app')

@section('content')

<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Loans Issued</h1>

    <div class="header-part-right">
        <ul>
            @if(Auth::check())
                <li>{{ Auth::user()->member_name }}</li>
            @endif

            @if(isset($currentPeriod) && $currentPeriod)
                <li>
                    <a href="{{ route('admin.periods') }}">
                        {{ $currentPeriod->period_name }}
                    </a>
                </li>
            @endif

            <li>
                <i
                    class="i-Full-Screen header-icon d-none d-sm-inline-block"
                    data-fullscreen="">
                </i>
            </li>
        </ul>
    </div>
</div>

<div class="separator-breadcrumb border-top"></div>


<div class="container-fluid">

    {{-- Validation errors --}}
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif


    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body">

            <form
                method="GET"
                action="{{ route('reports.loans.issued') }}">

                <div class="row">

                    {{-- Member --}}
                    <div class="col-lg-4 col-md-6 form-group mb-3">
                        <label for="member_search">
                            Member Search
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="member_search"
                            name="member_search"
                            value="{{ request('member_search') }}"
                            placeholder="Name, SACCO ID, National ID, phone or email">
                    </div>


                    {{-- Company --}}
                    <div class="col-lg-4 col-md-6 form-group mb-3">
                        <label for="search_company_name">
                            Company
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="search_company_name"
                            name="search_company_name"
                            value="{{ request('search_company_name') }}"
                            placeholder="Company name">
                    </div>


                    {{-- Loan search --}}
                    <div class="col-lg-4 col-md-6 form-group mb-3">
                        <label for="loan_search">
                            Loan / Reference Search
                        </label>

                        <input
                            type="text"
                            class="form-control"
                            id="loan_search"
                            name="loan_search"
                            value="{{ request('loan_search') }}"
                            placeholder="Loan type, loan ID, document no. or description">
                    </div>


                    {{-- Loan Type --}}
                    <div class="col-lg-4 col-md-6 form-group mb-3">
                        <label for="loan_type_id">
                            Loan Type
                        </label>

                        <select
                            class="form-control"
                            id="loan_type_id"
                            name="loan_type_id">

                            <option value="">
                                All Loan Types
                            </option>

                            @foreach($loanTypes as $loanType)

                                <option
                                    value="{{ $loanType->loan_type_id }}"
                                    {{ (string)request('loan_type_id') === (string)$loanType->loan_type_id ? 'selected' : '' }}>

                                    {{ $loanType->loan_type_name }}

                                </option>

                            @endforeach
                        </select>
                    </div>


                    {{-- Status --}}
                    <div class="col-lg-2 col-md-6 form-group mb-3">
                        <label for="loan_status">
                            Status
                        </label>

                        <select
                            class="form-control"
                            id="loan_status"
                            name="loan_status">

                            <option value="">
                                All
                            </option>

                            <option
                                value="N"
                                {{ request('loan_status') === 'N' ? 'selected' : '' }}>
                                Active
                            </option>

                            <option
                                value="Y"
                                {{ request('loan_status') === 'Y' ? 'selected' : '' }}>
                                Stopped
                            </option>

                        </select>
                    </div>


                    {{-- Per page --}}
                    <div class="col-lg-2 col-md-6 form-group mb-3">
                        <label for="per_page">
                            Rows
                        </label>

                        <select
                            class="form-control"
                            id="per_page"
                            name="per_page">

                            @foreach([25, 50, 100, 200] as $size)

                                <option
                                    value="{{ $size }}"
                                    {{ (int)request('per_page', 50) === $size ? 'selected' : '' }}>

                                    {{ $size }}

                                </option>

                            @endforeach

                        </select>
                    </div>


                    {{-- Actual Issue Date --}}
                    <div class="col-12">
                        <hr>

                        <h6 class="mb-3">
                            Issue Date Range
                        </h6>
                    </div>


                    <div class="col-lg-3 col-md-6 form-group mb-3">
                        <label for="start_date">
                            Date From
                        </label>

                        <input
                            type="date"
                            class="form-control"
                            id="start_date"
                            name="start_date"
                            value="{{ request('start_date') }}">
                    </div>


                    <div class="col-lg-3 col-md-6 form-group mb-3">
                        <label for="end_date">
                            Date To
                        </label>

                        <input
                            type="date"
                            class="form-control"
                            id="end_date"
                            name="end_date"
                            value="{{ request('end_date') }}">
                    </div>


                    {{-- Period range --}}
                    <div class="col-lg-3 col-md-6 form-group mb-3">
                        <label for="start_period">
                            Period From
                        </label>

                        <input
                            type="text"
                            maxlength="6"
                            class="form-control"
                            id="start_period"
                            name="start_period"
                            value="{{ request('start_period') }}"
                            placeholder="YYYYMM e.g. 202601">
                    </div>


                    <div class="col-lg-3 col-md-6 form-group mb-3">
                        <label for="end_period">
                            Period To
                        </label>

                        <input
                            type="text"
                            maxlength="6"
                            class="form-control"
                            id="end_period"
                            name="end_period"
                            value="{{ request('end_period') }}"
                            placeholder="YYYYMM e.g. 202609">
                    </div>


                    {{-- Buttons --}}
                    <div class="col-12">
                        <div class="d-flex flex-wrap gap-2">

                            <button
                                type="submit"
                                class="btn btn-primary">

                                Search
                            </button>

                            <a
                                class="btn btn-outline-secondary"
                                href="{{ route('reports.loans.issued') }}">

                                Clear Filters
                            </a>

                            <a
                                class="btn btn-success"
                                href="{{ route(
                                    'reports.loans.issued.download',
                                    request()->except('page')
                                ) }}">

                                Download Excel
                            </a>

                        </div>
                    </div>

                </div>

            </form>

        </div>
    </div>


    {{-- Report --}}
    <div class="card mb-4">

        <div class="card-body">

            <div
                class="d-flex flex-wrap justify-content-between align-items-center mb-3">

                <div>
                    <h4 class="mb-1">
                        Loans Issued
                    </h4>

                    <small class="text-muted">
                        Ordered by issue date, then accounting period.
                    </small>
                </div>


                <div class="text-muted">

                    @if($loansIssued->total() > 0)

                        Showing
                        <strong>{{ $loansIssued->firstItem() }}</strong>
                        -
                        <strong>{{ $loansIssued->lastItem() }}</strong>
                        of
                        <strong>{{ number_format($loansIssued->total()) }}</strong>
                        loans

                    @else

                        0 loans

                    @endif

                </div>

            </div>


            <div class="table-responsive">

                <table
                    class="table table-striped table-hover align-middle"
                    id="loans_issued_table">

                    <thead>
                        <tr>

                            <th>#</th>

                            <th>Member Name</th>
                            <th>SACCO ID</th>
                            <th>National ID</th>
                            <th>Company</th>

                            <th class="text-end">
                                Total Savings
                            </th>

                            <th class="text-end">
                                Other Contributions
                            </th>

                            <th class="text-end">
                                Capital Contributions
                            </th>

                            <th>Loan Type</th>

                            <th class="text-end">
                                Loan Amount
                            </th>

                            <th class="text-end">
                                Insurance
                            </th>

                            <th class="text-end">
                                Loan Paid
                            </th>

                            <th class="text-end">
                                Period (Months)
                            </th>

                            <th>Taken Period</th>

                            <th>Start Deduction</th>

                            <th>Doc No</th>

                            <th>Description</th>

                            <th>Stopped</th>

                            <th>Issued On</th>

                        </tr>
                    </thead>


                    <tbody>

                        @forelse($loansIssued as $index => $loan)

                            <tr>

                                {{-- Correct numbering across pages --}}
                                <td>
                                    {{
                                        ($loansIssued->firstItem() ?? 1)
                                        + $index
                                    }}
                                </td>


                                {{-- Member --}}
                                <td class="fw-semibold">
                                    {{ $loan->member_name }}
                                </td>

                                <td>
                                    {{ $loan->member_sacco_id }}
                                </td>

                                <td>
                                    {{ $loan->member_national_id ?? '-' }}
                                </td>

                                <td>
                                    {{ $loan->company_name ?? '-' }}
                                </td>


                                {{-- Member balances --}}
                                <td class="text-end">
                                    {{
                                        number_format(
                                            (float)($loan->member_total_share ?? 0),
                                            2
                                        )
                                    }}
                                </td>

                                <td class="text-end">
                                    {{
                                        number_format(
                                            (float)($loan->member_total_fosa ?? 0),
                                            2
                                        )
                                    }}
                                </td>

                                <td class="text-end">
                                    {{
                                        number_format(
                                            (float)($loan->member_total_share_capital ?? 0),
                                            2
                                        )
                                    }}
                                </td>


                                {{-- Loan --}}
                                <td>
                                    {{ $loan->loan_type_name ?? '-' }}
                                </td>

                                <td class="text-end">
                                    {{
                                        number_format(
                                            (float)($loan->loan_amount ?? 0),
                                            2
                                        )
                                    }}
                                </td>

                                <td class="text-end">
                                    {{
                                        number_format(
                                            (float)($loan->loan_insurance ?? 0),
                                            2
                                        )
                                    }}
                                </td>

                                <td class="text-end">
                                    {{
                                        number_format(
                                            (float)($loan->loan_loan_paid ?? 0),
                                            2
                                        )
                                    }}
                                </td>

                                <td class="text-end">
                                    {{ $loan->loan_payment_period ?? '-' }}
                                </td>


                                {{-- Period --}}
                                <td>
                                    {{ $loan->loan_taken_period ?? '-' }}
                                </td>

                                <td>
                                    {{ $loan->loan_start_deduction_period ?? '-' }}
                                </td>


                                {{-- References --}}
                                <td>
                                    {{ $loan->loan_doc_no ?? '-' }}
                                </td>

                                <td style="min-width: 220px;">
                                    {{ $loan->loan_description ?? '-' }}
                                </td>


                                {{-- Status --}}
                                <td>

                                    @php
                                        $stopped = strtoupper(
                                            (string)($loan->loan_stoped ?? 'N')
                                        );
                                    @endphp

                                    <span
                                        class="badge {{ $stopped === 'Y'
                                            ? 'bg-danger'
                                            : 'bg-success' }}">

                                        {{ $stopped === 'Y'
                                            ? 'Yes'
                                            : 'No' }}

                                    </span>

                                </td>


                                {{-- Date --}}
                                <td class="text-nowrap">

                                    @if(!empty($loan->loan_on))

                                        {{
                                            \Carbon\Carbon::parse(
                                                $loan->loan_on
                                            )->format('d/m/Y')
                                        }}

                                    @else

                                        -

                                    @endif

                                </td>

                            </tr>

                        @empty

                            <tr>

                                <td
                                    colspan="19"
                                    class="text-center text-muted py-4">

                                    No loan records found for the selected filters.

                                </td>

                            </tr>

                        @endforelse

                    </tbody>

                </table>

            </div>


            {{-- Pagination --}}
            @if($loansIssued->total() > 0)

                <div
                    class="d-flex flex-wrap justify-content-between align-items-center mt-3">

                    <div class="text-muted mb-2">

                        Showing
                        {{ $loansIssued->firstItem() }}
                        to
                        {{ $loansIssued->lastItem() }}
                        of
                        {{ number_format($loansIssued->total()) }}
                        records

                    </div>


                    <div>

                        {{
                            $loansIssued
                                ->onEachSide(1)
                                ->links('pagination::bootstrap-5')
                        }}

                    </div>

                </div>

            @endif

        </div>
    </div>

</div>

@endsection