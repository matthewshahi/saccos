@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Loan Issued</h1>
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

<div class="container">
    <div class="row">
        {{-- Filters --}}
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" action="{{ route('reports.loans.issued') }}">
                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label for="search_name">Member Name</label>
                                <input class="form-control" id="search_name" type="text" name="search_name"
                                    placeholder="Enter member name" value="{{ request('search_name') }}">
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="search_company_name">Company Name</label>
                                <input class="form-control" id="search_company_name" type="text" name="search_company_name"
                                    placeholder="Enter company name" value="{{ request('search_company_name') }}">
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="search_sacco_id">Sacco ID</label>
                                <input class="form-control" id="search_sacco_id" type="text" name="search_sacco_id"
                                    placeholder="Enter sacco ID" value="{{ request('search_sacco_id') }}">
                            </div>

                            <div class="col-md-3 form-group mb-3">
                                <label for="start_period">Start Period (YYYYmm)</label>
                                <input class="form-control" id="start_period" type="text" name="start_period"
                                    placeholder="e.g., 202301" value="{{ request('start_period') }}">
                            </div>

                            <div class="col-md-3 form-group mb-3">
                                <label for="end_period">End Period (YYYYmm)</label>
                                <input class="form-control" id="end_period" type="text" name="end_period"
                                    placeholder="e.g., 202312" value="{{ request('end_period') }}">
                            </div>

                            <div class="col-md-12 d-flex align-items-center gap-2">
                                <button class="btn btn-primary">Search</button>

                                {{-- Optional quick clear --}}
                                <a class="btn btn-outline-secondary" href="{{ route('reports.loans.issued') }}">
                                    Clear
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        {{-- Table --}}
        <div class="col-md-12">
            <div class="card o-hidden mb-4">
                <div class="card-header d-flex align-items-center border-0">
                    <h3 class="w-50 float-start card-title m-0">Loans Issued</h3>

                    <div class="w-50 float-end d-flex justify-content-end align-items-center gap-2">
                        {{-- Direct download button --}}
                        <a class="btn btn-success btn-sm"
                           href="{{ route('reports.loans.issued.download', request()->except('page')) }}">
                            Download Excel
                        </a>

                        {{-- Gear dropdown --}}
                        <div class="dropdown dropleft text-end">
                            <button class="btn bg-gray-100" id="dropdownMenuButton1" type="button"
                                data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="nav-icon i-Gear-2"></i>
                            </button>

                            <div class="dropdown-menu" aria-labelledby="dropdownMenuButton1">
                                <a class="dropdown-item"
                                   href="{{ route('reports.loans.issued.download', request()->except('page')) }}">
                                    Download Excel
                                </a>
                                <a class="dropdown-item" href="{{ route('reports.loans.issued') }}">
                                    Clear Filters
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover align-middle" id="loans_issued_table">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>

                                    {{-- Identity --}}
                                    <th scope="col">Member Name</th>
                                    <th scope="col">Sacco ID</th>
                                    <th scope="col">Company</th>

                                    {{-- Loan core --}}
                                    <th scope="col" class="text-end">Loan Amount</th>
                                    <th scope="col" class="text-end">Insurance</th>
                                    <th scope="col" class="text-end">Loan Paid</th>
                                    <th scope="col" class="text-end">Period (Months)</th>

                                    {{-- Periods --}}
                                    <th scope="col">Taken Period</th>
                                    <th scope="col">Start Deduction</th>

                                    {{-- References / description --}}
                                    <th scope="col">Doc No</th>
                                    <th scope="col">Description</th>

                                    {{-- Status / audit --}}
                                    <th scope="col">Stopped</th>
                                    <th scope="col">Issued On</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($loansIssued as $index => $loan)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>

                                        {{-- Identity --}}
                                        <td class="fw-semibold">{{ $loan->member_name }}</td>
                                        <td>{{ $loan->member_sacco_id }}</td>
                                        <td>{{ $loan->company_name ?? '-' }}</td>

                                        {{-- Loan core --}}
                                        <td class="text-end">{{ number_format((float)($loan->loan_amount ?? 0), 2) }}</td>
                                        <td class="text-end">{{ number_format((float)($loan->loan_insurance ?? 0), 2) }}</td>
                                        <td class="text-end">{{ number_format((float)($loan->loan_loan_paid ?? 0), 2) }}</td>
                                        <td class="text-end">{{ $loan->loan_payment_period ?? '-' }}</td>

                                        {{-- Periods --}}
                                        <td>{{ $loan->loan_taken_period ?? '-' }}</td>
                                        <td>{{ $loan->loan_start_deduction_period ?? '-' }}</td>

                                        {{-- References / description --}}
                                        <td>{{ $loan->loan_doc_no ?? '-' }}</td>
                                        <td style="min-width: 220px;">
                                            {{ $loan->loan_description ?? '-' }}
                                        </td>

                                        {{-- Status / audit --}}
                                        <td>
                                            @php $stopped = strtoupper((string)($loan->loan_stoped ?? 'N')); @endphp
                                            <span class="badge {{ $stopped === 'Y' ? 'bg-danger' : 'bg-success' }}">
                                                {{ $stopped === 'Y' ? 'Yes' : 'No' }}
                                            </span>
                                        </td>
                                        <td>
                                            {{ !empty($loan->loan_on) ? \Carbon\Carbon::parse($loan->loan_on)->format('d/m/Y') : '-' }}
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="14" class="text-center text-muted py-4">
                                            No loan records found for the selected filters.
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
</div>
@endsection
