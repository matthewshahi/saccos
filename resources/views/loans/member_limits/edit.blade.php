@extends('layouts.app')

@section('content')

<div class="main-content pt-4">

    {{-- ============================================================
         Page Heading
    ============================================================ --}}
    <div class="breadcrumb d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
        <div>
            <h1 class="mb-1">Individual Loan Limits</h1>

            <ul class="mb-0">
                <li>
                    <a href="{{ url('/dashboard') }}">Dashboard</a>
                </li>
                <li>Loans</li>
                <li>Individual Loan Limits</li>
            </ul>
        </div>

        <div>
            <a
                href="{{ route('loans.member_limits.create') }}"
                class="btn btn-primary btn-rounded"
            >
                <i class="i-Add me-1"></i>
                Add Individual Limit
            </a>
        </div>
    </div>

    <div class="separator-breadcrumb border-top mb-4"></div>

    {{-- ============================================================
         Messages
    ============================================================ --}}
    @if (session('success'))
        <div
            class="alert alert-success alert-dismissible fade show"
            role="alert"
        >
            <strong>Success:</strong>
            {{ session('success') }}

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close"
            ></button>
        </div>
    @endif

    @if (session('error'))
        <div
            class="alert alert-danger alert-dismissible fade show"
            role="alert"
        >
            <strong>Error:</strong>
            {{ session('error') }}

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close"
            ></button>
        </div>
    @endif

    @if ($errors->any())
        <div
            class="alert alert-danger alert-dismissible fade show"
            role="alert"
        >
            <strong>Please correct the following:</strong>

            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
                aria-label="Close"
            ></button>
        </div>
    @endif

    {{-- ============================================================
         Module Explanation
    ============================================================ --}}
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-start">

                        <div class="me-3">
                            <span
                                class="avatar-sm rounded-circle bg-primary text-white d-flex align-items-center justify-content-center"
                            >
                                <i class="i-Lock-2 text-18"></i>
                            </span>
                        </div>

                        <div>
                            <h5 class="mb-1">
                                Member-specific loan controls
                            </h5>

                            <p class="text-muted mb-0">
                                Members listed here have a special maximum
                                amount for the selected loan type. A member
                                without an individual-limit record continues
                                using the standard maximum configured under
                                the loan type.
                            </p>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============================================================
         Search and Filters
    ============================================================ --}}
    <div class="card mb-4">
        <div class="card-body">

            <form
                method="GET"
                action="{{ route('loans.member_limits.index') }}"
            >
                <div class="row align-items-end">

                    <div class="col-xl-5 col-md-6 form-group mb-3">
                        <label
                            for="search"
                            class="form-label fw-semibold"
                        >
                            Search
                        </label>

                        <input
                            type="search"
                            name="search"
                            id="search"
                            class="form-control"
                            value="{{ old('search', $search ?? '') }}"
                            placeholder="Member name, SACCO number, ID, phone or loan type"
                            autocomplete="off"
                        >
                    </div>

                    <div class="col-xl-3 col-md-6 form-group mb-3">
                        <label
                            for="loan_type_id"
                            class="form-label fw-semibold"
                        >
                            Loan Type
                        </label>

                        <select
                            name="loan_type_id"
                            id="loan_type_id"
                            class="form-select"
                        >
                            <option value="">
                                All loan types
                            </option>

                            @foreach ($loanTypes as $loanType)
                                <option
                                    value="{{ $loanType->loan_type_id }}"
                                    {{ (string) ($loanTypeId ?? '') === (string) $loanType->loan_type_id
                                        ? 'selected'
                                        : '' }}
                                >
                                    {{ $loanType->loan_type_name }}

                                    @if (!empty($loanType->loan_type_code))
                                        — {{ $loanType->loan_type_code }}
                                    @endif
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-xl-2 col-md-6 form-group mb-3">
                        <label
                            for="status"
                            class="form-label fw-semibold"
                        >
                            Status
                        </label>

                        <select
                            name="status"
                            id="status"
                            class="form-select"
                        >
                            <option value="">
                                All statuses
                            </option>

                            <option
                                value="active"
                                {{ ($status ?? '') === 'active'
                                    ? 'selected'
                                    : '' }}
                            >
                                Active
                            </option>

                            <option
                                value="inactive"
                                {{ ($status ?? '') === 'inactive'
                                    ? 'selected'
                                    : '' }}
                            >
                                Inactive
                            </option>
                        </select>
                    </div>

                    <div class="col-xl-2 col-md-6 form-group mb-3">
                        <div class="d-flex gap-2">

                            <button
                                type="submit"
                                class="btn btn-primary flex-grow-1"
                            >
                                <i class="i-Search-People me-1"></i>
                                Filter
                            </button>

                            <a
                                href="{{ route('loans.member_limits.index') }}"
                                class="btn btn-outline-secondary"
                                title="Clear filters"
                                aria-label="Clear filters"
                            >
                                <i class="i-Refresh"></i>
                            </a>

                        </div>
                    </div>

                </div>
            </form>

        </div>
    </div>

    {{-- ============================================================
         Limits Listing
    ============================================================ --}}
    <div class="card mb-4">
        <div class="card-body">

            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">

                <div>
                    <h4 class="card-title mb-1">
                        Configured Individual Limits
                    </h4>

                    <p class="text-muted mb-0">
                        {{ number_format($memberLoanLimits->total()) }}

                        {{ $memberLoanLimits->total() === 1
                            ? 'record found'
                            : 'records found' }}
                    </p>
                </div>

                @if (
                    $currentPeriod
                    && !empty($currentPeriod->period_name)
                )
                    <div>
                        <span class="badge bg-light text-dark border px-3 py-2">
                            Active period:
                            <strong>{{ $currentPeriod->period_name }}</strong>
                        </span>
                    </div>
                @endif

            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">

                    <thead class="table-light">
                        <tr>
                            <th style="width: 55px;">
                                #
                            </th>

                            <th>
                                Member
                            </th>

                            <th>
                                Loan Type
                            </th>

                            <th class="text-end">
                                Standard Maximum
                            </th>

                            <th class="text-end">
                                Individual Limit
                            </th>

                            <th class="text-end">
                                Reduction
                            </th>

                            <th class="text-center">
                                Status
                            </th>

                            <th
                                class="text-end"
                                style="min-width: 190px;"
                            >
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse ($memberLoanLimits as $memberLoanLimit)

                            @php
                                $standardMaximum = round(
                                    (float) ($memberLoanLimit->loan_type_max_amount ?? 0),
                                    2
                                );

                                $individualLimit = round(
                                    (float) ($memberLoanLimit->member_loan_limit_amount ?? 0),
                                    2
                                );

                                $reduction = max(
                                    0,
                                    $standardMaximum - $individualLimit
                                );

                                $limitIsActive =
                                    (int) $memberLoanLimit->member_loan_limit_active === 1;

                                $memberIsActive =
                                    (string) $memberLoanLimit->member_active === 'Y';

                                $loanTypeIsActive =
                                    (int) $memberLoanLimit->loan_type_active === 1;
                            @endphp

                            <tr>

                                {{-- Row number --}}
                                <td>
                                    {{ ($memberLoanLimits->firstItem() ?? 1) + $loop->index }}
                                </td>

                                {{-- Member --}}
                                <td>
                                    <div class="fw-semibold text-dark">
                                        {{ $memberLoanLimit->member_name }}
                                    </div>

                                    <div class="text-muted text-small">
                                        SACCO No:
                                        {{ $memberLoanLimit->member_sacco_id ?: 'N/A' }}
                                    </div>

                                    @if (!empty($memberLoanLimit->member_national_id))
                                        <div class="text-muted text-small">
                                            National ID:
                                            {{ $memberLoanLimit->member_national_id }}
                                        </div>
                                    @endif

                                    @if (!empty($memberLoanLimit->member_phone_no))
                                        <div class="text-muted text-small">
                                            Phone:
                                            {{ $memberLoanLimit->member_phone_no }}
                                        </div>
                                    @endif

                                    @unless ($memberIsActive)
                                        <span class="badge bg-warning text-dark mt-1">
                                            Member inactive
                                        </span>
                                    @endunless
                                </td>

                                {{-- Loan type --}}
                                <td>
                                    <div class="fw-semibold text-dark">
                                        {{ $memberLoanLimit->loan_type_name }}
                                    </div>

                                    @if (!empty($memberLoanLimit->loan_type_code))
                                        <div class="text-muted text-small">
                                            Code:
                                            {{ $memberLoanLimit->loan_type_code }}
                                        </div>
                                    @endif

                                    @unless ($loanTypeIsActive)
                                        <span class="badge bg-warning text-dark mt-1">
                                            Loan type inactive
                                        </span>
                                    @endunless
                                </td>

                                {{-- Standard maximum --}}
                                <td class="text-end text-nowrap">
                                    <span class="text-muted">
                                        KES
                                    </span>

                                    <strong>
                                        {{ number_format($standardMaximum, 2) }}
                                    </strong>
                                </td>

                                {{-- Individual maximum --}}
                                <td class="text-end text-nowrap">
                                    <span class="text-muted">
                                        KES
                                    </span>

                                    <strong class="text-primary">
                                        {{ number_format($individualLimit, 2) }}
                                    </strong>
                                </td>

                                {{-- Reduction --}}
                                <td class="text-end text-nowrap">
                                    <span class="text-danger fw-semibold">
                                        KES {{ number_format($reduction, 2) }}
                                    </span>
                                </td>

                                {{-- Status --}}
                                <td class="text-center">
                                    @if ($limitIsActive)
                                        <span class="badge bg-success">
                                            Active
                                        </span>
                                    @else
                                        <span class="badge bg-secondary">
                                            Inactive
                                        </span>
                                    @endif
                                </td>

                                {{-- Actions --}}
                                <td class="text-end">
                                    <div class="d-flex justify-content-end align-items-center gap-1">

                                        {{-- Edit --}}
                                        <a
                                            href="{{ route(
                                                'loans.member_limits.edit',
                                                $memberLoanLimit->member_loan_limit_id
                                            ) }}"
                                            class="btn btn-sm btn-outline-primary"
                                            title="Edit individual limit"
                                        >
                                            <i class="i-Pen-2 me-1"></i>
                                            Edit
                                        </a>

                                        {{-- Activate or deactivate --}}
                                        <form
                                            method="POST"
                                            action="{{ route(
                                                'loans.member_limits.status',
                                                $memberLoanLimit->member_loan_limit_id
                                            ) }}"
                                            class="d-inline"
                                        >
                                            @csrf
                                            @method('PATCH')

                                            <input
                                                type="hidden"
                                                name="member_loan_limit_active"
                                                value="{{ $limitIsActive ? 0 : 1 }}"
                                            >

                                            <button
                                                type="submit"
                                                class="btn btn-sm {{ $limitIsActive
                                                    ? 'btn-outline-warning'
                                                    : 'btn-outline-success' }}"
                                                title="{{ $limitIsActive
                                                    ? 'Deactivate individual limit'
                                                    : 'Activate individual limit' }}"
                                                onclick="return confirm(
                                                    '{{ $limitIsActive
                                                        ? 'Deactivate this individual loan limit? The member will temporarily use the standard loan-type maximum.'
                                                        : 'Activate this individual loan limit?' }}'
                                                );"
                                            >
                                                @if ($limitIsActive)
                                                    <i class="i-Close-Window"></i>
                                                @else
                                                    <i class="i-Yes"></i>
                                                @endif
                                            </button>
                                        </form>

                                        {{-- Delete --}}
                                        <form
                                            method="POST"
                                            action="{{ route(
                                                'loans.member_limits.destroy',
                                                $memberLoanLimit->member_loan_limit_id
                                            ) }}"
                                            class="d-inline"
                                        >
                                            @csrf
                                            @method('DELETE')

                                            <button
                                                type="submit"
                                                class="btn btn-sm btn-outline-danger"
                                                title="Delete individual limit"
                                                onclick="return confirm(
                                                    'Delete this individual loan limit? The member will revert to the standard loan-type maximum.'
                                                );"
                                            >
                                                <i class="i-Close"></i>
                                            </button>
                                        </form>

                                    </div>
                                </td>

                            </tr>

                        @empty

                            <tr>
                                <td
                                    colspan="8"
                                    class="text-center py-5"
                                >
                                    <div class="text-muted">

                                        <i class="i-File-Clipboard-File--Text text-40 d-block mb-3"></i>

                                        <h5>
                                            No individual loan limits found
                                        </h5>

                                        <p class="mb-3">
                                            No member currently matches the
                                            selected filters.
                                        </p>

                                        @if (
                                            !empty($search)
                                            || !empty($loanTypeId)
                                            || !empty($status)
                                        )
                                            <a
                                                href="{{ route('loans.member_limits.index') }}"
                                                class="btn btn-outline-secondary me-2"
                                            >
                                                Clear Filters
                                            </a>
                                        @endif

                                        <a
                                            href="{{ route('loans.member_limits.create') }}"
                                            class="btn btn-primary btn-rounded"
                                        >
                                            Add Individual Limit
                                        </a>

                                    </div>
                                </td>
                            </tr>

                        @endforelse
                    </tbody>

                </table>
            </div>

            {{-- ====================================================
                 Pagination
            ==================================================== --}}
            @if ($memberLoanLimits->hasPages())
                <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mt-4">

                    <div class="text-muted text-small">
                        Showing
                        {{ number_format($memberLoanLimits->firstItem()) }}
                        to
                        {{ number_format($memberLoanLimits->lastItem()) }}
                        of
                        {{ number_format($memberLoanLimits->total()) }}
                        records
                    </div>

                    <div>
                        {{ $memberLoanLimits->links('pagination::bootstrap-5') }}
                    </div>

                </div>
            @endif

        </div>
    </div>

</div>

<style>
    .avatar-sm {
        width: 44px;
        height: 44px;
        min-width: 44px;
    }

    .text-small {
        font-size: 12px;
    }

    .table th {
        white-space: nowrap;
        font-size: 13px;
        font-weight: 700;
        vertical-align: middle;
    }

    .table td {
        vertical-align: middle;
    }

    .btn-rounded {
        border-radius: 50px;
    }

    .table-responsive {
        min-height: 140px;
    }

    @media (max-width: 767.98px) {
        .breadcrumb .btn {
            width: 100%;
        }

        .table td,
        .table th {
            min-width: 120px;
        }

        .table td:first-child,
        .table th:first-child {
            min-width: 50px;
        }
    }
</style>

@endsection