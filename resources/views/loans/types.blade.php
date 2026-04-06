@extends('layouts.app')

@section('styles')
<style>
    .loan-types-table-wrap {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        -webkit-overflow-scrolling: touch;
        border: 1px solid #dee2e6;
        border-radius: 6px;
        background: #fff;
    }

    .loan-types-table {
        width: 100%;
        min-width: 1450px;
        margin-bottom: 0;
    }

    .loan-types-table th,
    .loan-types-table td {
        vertical-align: top;
    }

    .loan-types-table thead th {
        white-space: nowrap;
        vertical-align: middle;
        background: #fff;
    }

    .loan-types-table .cell-title {
        font-weight: 700;
        font-size: 1.05rem;
        color: #1f2937;
        margin-bottom: 8px;
    }

    .loan-types-table .cell-line {
        display: block;
        line-height: 1.55;
        margin-bottom: 6px;
        color: #374151;
    }

    .loan-types-table .cell-line:last-child {
        margin-bottom: 0;
    }

    .loan-types-table .cell-line strong {
        color: #111827;
        font-weight: 700;
        display: inline-block;
        margin-right: 4px;
    }

    .loan-types-table .group-cell {
        min-width: 250px;
    }

    .loan-types-table .sticky-left {
        position: sticky;
        left: 0;
        background: #fff;
        z-index: 3;
        min-width: 55px;
        text-align: center;
        vertical-align: middle;
    }

    .loan-types-table tbody tr:nth-child(even) .sticky-left {
        background: #f8f9fa;
    }

    .loan-types-table thead .sticky-left {
        z-index: 4;
        background: #fff;
    }

    .loan-types-table .sticky-action {
        position: sticky;
        right: 0;
        background: #fff;
        z-index: 3;
        min-width: 90px;
        text-align: center;
        vertical-align: middle;
        box-shadow: -4px 0 6px rgba(0,0,0,0.05);
    }

    .loan-types-table tbody tr:nth-child(even) .sticky-action {
        background: #f8f9fa;
    }

    .loan-types-table thead .sticky-action {
        z-index: 4;
        background: #fff;
    }

    .loan-types-table .action-icons {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        min-height: 100%;
    }

    .loan-types-table small {
        font-size: 11px;
    }
</style>
@endsection

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Loan Types</h1>
    <div class="header-part-right">
        <ul>
            @if(Auth::check())
                <li>{{ Auth::user()->member_name }}</li>
            @endif
            @if(isset($currentPeriod) && $currentPeriod)
                <li><a href="{{ route('admin.periods') }}">{{ $currentPeriod->period_name }}</a></li>
            @endif
            <li><i class="i-Full-Screen header-icon d-none d-sm-inline-block" data-fullscreen=""></i></li>
        </ul>
    </div>
</div>

<div class="separator-breadcrumb border-top"></div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row">
    <div class="col-md-12">
        <div class="card o-hidden mb-4">
            <div class="card-header">
                <h3 class="w-50 float-start card-title m-0">All Loan Types</h3>

                <div class="dropdown dropleft text-end w-50 float-end">
                    <button class="btn bg-gray-100" type="button" id="dropdownMenuButton_table2" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="nav-icon i-Gear-2"></i>
                    </button>
                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_table2">
                        <a class="dropdown-item" href="{{ route('loans.types.add') }}">Add New Loan Type</a>
                        <a class="dropdown-item" href="{{ route('loans.types') }}">Refresh List</a>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="mb-3">
                    <a href="{{ route('loans.types.add') }}" class="btn btn-primary">Add New Loan Type</a>
                </div>

                <div class="loan-types-table-wrap">
                    <table class="table table-striped table-bordered loan-types-table">
                        <thead>
                            <tr>
                                <th class="sticky-left">#</th>
                                <th>Loan Type</th>
                                <th>Pricing &amp; Repayment</th>
                                <th>Eligibility Rules</th>
                                <th>Compliance &amp; Charges</th>
                                <th>Accounts</th>
                                <th class="sticky-action">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($loanTypes as $index => $loanType)
                                @php
                                    $loanAcc = $subAccountDetails[$loanType->loan_type_acount] ?? null;
                                    $intAcc = $subAccountDetails[$loanType->loan_type_int_account] ?? null;
                                    $commAcc = $subAccountDetails[$loanType->loan_type_comm_account] ?? null;
                                @endphp

                                <tr>
                                    <td class="sticky-left">{{ $index + 1 }}</td>

                                    <td class="group-cell">
                                        <div class="cell-title">{{ $loanType->loan_type_name }}</div>

                                        <span class="cell-line">
                                            <strong>Code:</strong> {{ $loanType->loan_type_code }}
                                        </span>

                                        <span class="cell-line">
                                            <strong>Status:</strong>
                                            @if((int)($loanType->loan_type_active ?? 1) === 1)
                                                <span class="badge text-bg-success">Active</span>
                                            @else
                                                <span class="badge text-bg-warning">Inactive</span>
                                            @endif
                                        </span>
                                    </td>

                                    <td class="group-cell">
                                        <span class="cell-line">
                                            <strong>Interest:</strong> {{ number_format((float)$loanType->loan_type_interest, 2) }}%
                                        </span>

                                        <span class="cell-line">
                                            <strong>Type:</strong> {{ $loanType->loan_type_interest_type }}
                                        </span>

                                        <span class="cell-line">
                                            <strong>Duration:</strong> {{ (int)$loanType->loan_type_duration }} months
                                        </span>

                                        <span class="cell-line">
                                            <strong>Max Amount:</strong> {{ number_format((float)$loanType->loan_type_max_amount, 2) }}
                                        </span>
                                    </td>

                                    <td class="group-cell">
                                        <span class="cell-line">
                                            <strong>Share Factor:</strong> {{ $loanType->loan_type_share_factor ?? 0 }}
                                        </span>

                                        <span class="cell-line">
                                            <strong>Guarantee:</strong> {{ (int)$loanType->loan_type_guaranteable_percent }}%
                                        </span>

                                        <span class="cell-line">
                                            <strong>Min Months:</strong> {{ (int)$loanType->loan_type_qualification_period }}
                                        </span>

                                        <span class="cell-line">
                                            <strong>Max Months:</strong>
                                            @if(!is_null($loanType->loan_type_max_qualification_period))
                                                {{ (int)$loanType->loan_type_max_qualification_period }}
                                            @else
                                                No limit
                                            @endif
                                        </span>

                                        <span class="cell-line">
                                            <strong>Instant:</strong>
                                            @if((int)($loanType->loan_type_instant_qualification ?? 0) === 1)
                                                <span class="badge text-bg-primary">Yes</span>
                                            @else
                                                <span class="badge text-bg-secondary">No</span>
                                            @endif
                                        </span>
                                    </td>

                                    <td class="group-cell">
                                        <span class="cell-line">
                                            <strong>Insurable:</strong>
                                            @if(($loanType->loan_type_insurable ?? 'N') === 'Y')
                                                <span class="badge text-bg-success">Yes</span>
                                            @else
                                                <span class="badge text-bg-danger">No</span>
                                            @endif
                                        </span>

                                        <span class="cell-line">
                                            <strong>Insurance Effect:</strong>
                                            @if(($loanType->loan_type_insurance_effect ?? '') === 'ADD_TO_LOAN')
                                                Add to Loan
                                            @elseif(($loanType->loan_type_insurance_effect ?? '') === 'DEDUCT_FROM_DISBURSEMENT')
                                                Deduct from Payout
                                            @else
                                                N/A
                                            @endif
                                        </span>

                                        <span class="cell-line">
                                            <strong>CRB Required:</strong>
                                            @if(($loanType->loan_type_crb_required ?? 'N') === 'Y')
                                                <span class="badge text-bg-info">Yes</span>
                                            @else
                                                <span class="badge text-bg-secondary">No</span>
                                            @endif
                                        </span>

                                        <span class="cell-line">
                                            <strong>CRB Charge:</strong> {{ number_format((float)($loanType->loan_type_crb_charge ?? 0), 2) }}
                                        </span>

                                        <span class="cell-line">
                                            <strong>CRB Effect:</strong>
                                            @if(($loanType->loan_type_crb_effect ?? '') === 'ADD_TO_LOAN')
                                                Add to Loan
                                            @elseif(($loanType->loan_type_crb_effect ?? '') === 'DEDUCT_FROM_DISBURSEMENT')
                                                Deduct from Payout
                                            @else
                                                N/A
                                            @endif
                                        </span>

                                        <span class="cell-line">
                                            <strong>Commission Required:</strong>
                                            @if(($loanType->loan_type_commission_required ?? 'N') === 'Y')
                                                <span class="badge text-bg-info">Yes</span>
                                            @else
                                                <span class="badge text-bg-secondary">No</span>
                                            @endif
                                        </span>

                                        <span class="cell-line">
                                            <strong>Commission Type:</strong> {{ $loanType->loan_type_commission_type ?? 'N/A' }}
                                        </span>

                                        <span class="cell-line">
                                            <strong>Commission Value:</strong> {{ number_format((float)($loanType->loan_type_commission_value ?? 0), 2) }}
                                        </span>

                                        <span class="cell-line">
                                            <strong>Commission Effect:</strong>
                                            @if(($loanType->loan_type_commission_effect ?? '') === 'ADD_TO_LOAN')
                                                Add to Loan
                                            @elseif(($loanType->loan_type_commission_effect ?? '') === 'DEDUCT_FROM_DISBURSEMENT')
                                                Deduct from Payout
                                            @else
                                                N/A
                                            @endif
                                        </span>
                                    </td>

                                    <td class="group-cell">
                                        <span class="cell-line">
                                            <strong>Loan Account:</strong><br>
                                            @if($loanAcc)
                                                {{ $loanAcc->sub_account_name }}<br>
                                                <small class="text-muted">{{ $loanAcc->main_account_code }}/{{ $loanAcc->sub_account_code }}</small>
                                            @else
                                                <span class="text-muted">None</span>
                                            @endif
                                        </span>

                                        <span class="cell-line">
                                            <strong>Interest Account:</strong><br>
                                            @if($intAcc)
                                                {{ $intAcc->sub_account_name }}<br>
                                                <small class="text-muted">{{ $intAcc->main_account_code }}/{{ $intAcc->sub_account_code }}</small>
                                            @else
                                                <span class="text-muted">None</span>
                                            @endif
                                        </span>

                                        <span class="cell-line">
                                            <strong>Commission Account:</strong><br>
                                            @if($commAcc)
                                                {{ $commAcc->sub_account_name }}<br>
                                                <small class="text-muted">{{ $commAcc->main_account_code }}/{{ $commAcc->sub_account_code }}</small>
                                            @else
                                                <span class="text-muted">None</span>
                                            @endif
                                        </span>
                                    </td>

                                    <td class="sticky-action">
                                        <div class="action-icons">
                                            <a href="{{ route('loans.types.edit', $loanType->loan_type_id) }}" class="text-success" title="Edit">
                                                <i class="nav-icon i-Pen-2 font-weight-bold"></i>
                                            </a>

                                            <form action="{{ route('loans.types.delete', $loanType->loan_type_id) }}" method="POST" style="display:inline;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-danger border-0 bg-transparent p-0" title="Delete" onclick="return confirm('Delete this loan type?');">
                                                    <i class="nav-icon i-Close-Window font-weight-bold"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No loan types found.</td>
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