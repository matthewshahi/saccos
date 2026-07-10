@extends('layouts.app')

@section('content')

<div class="main-content pt-4">

    {{-- Page heading --}}
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <div>
            <h1 class="mb-1">Edit Individual Loan Limit</h1>

            <ul class="mb-0">
                <li>
                    <a href="{{ url('/dashboard') }}">Dashboard</a>
                </li>

                <li>
                    <a href="{{ route('loans.member_limits.index') }}">
                        Individual Loan Limits
                    </a>
                </li>

                <li>Edit Limit</li>
            </ul>
        </div>

        <a
            href="{{ route('loans.member_limits.index') }}"
            class="btn btn-outline-secondary btn-rounded"
        >
            <i class="i-Arrow-Back-3 me-1"></i>
            Back to List
        </a>
    </div>

    <div class="separator-breadcrumb border-top mb-4"></div>

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>The record could not be updated.</strong>

            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">

        {{-- Main form --}}
        <div class="col-lg-8">

            <div class="card mb-4">
                <div class="card-body">

                    <div class="card-title mb-1">
                        Update Member Loan Limit
                    </div>

                    <p class="text-muted mb-4">
                        Modify the member, loan type, individual amount or
                        activation status.
                    </p>

                    <form
                        method="POST"
                        action="{{ route(
                            'loans.member_limits.update',
                            $memberLoanLimit->member_loan_limit_id
                        ) }}"
                        id="memberLoanLimitForm"
                    >
                        @csrf
                        @method('PUT')

                        <div class="row">

                            {{-- Member --}}
                            <div class="col-md-12 form-group mb-4">
                                <label
                                    for="member_loan_limit_member_id"
                                    class="form-label fw-semibold"
                                >
                                    Member
                                    <span class="text-danger">*</span>
                                </label>

                                <select
                                    name="member_loan_limit_member_id"
                                    id="member_loan_limit_member_id"
                                    class="form-select @error('member_loan_limit_member_id') is-invalid @enderror"
                                    required
                                >
                                    <option value="">
                                        Select member
                                    </option>

                                    @foreach ($members as $member)

                                        @php
                                            $selectedMemberId = old(
                                                'member_loan_limit_member_id',
                                                $memberLoanLimit->member_loan_limit_member_id
                                            );
                                        @endphp

                                        <option
                                            value="{{ $member->member_id }}"
                                            {{ (string) $selectedMemberId === (string) $member->member_id ? 'selected' : '' }}
                                        >
                                            {{ $member->member_name }}
                                            — {{ $member->member_sacco_id ?: 'No SACCO number' }}

                                            @if (!empty($member->member_national_id))
                                                — ID {{ $member->member_national_id }}
                                            @endif

                                            @if ((string) ($member->member_active ?? 'Y') !== 'Y')
                                                — INACTIVE
                                            @endif
                                        </option>
                                    @endforeach
                                </select>

                                @error('member_loan_limit_member_id')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            {{-- Loan type --}}
                            <div class="col-md-7 form-group mb-4">
                                <label
                                    for="member_loan_limit_loan_type_id"
                                    class="form-label fw-semibold"
                                >
                                    Loan Type
                                    <span class="text-danger">*</span>
                                </label>

                                <select
                                    name="member_loan_limit_loan_type_id"
                                    id="member_loan_limit_loan_type_id"
                                    class="form-select @error('member_loan_limit_loan_type_id') is-invalid @enderror"
                                    required
                                >
                                    <option value="">
                                        Select loan type
                                    </option>

                                    @foreach ($loanTypes as $loanType)

                                        @php
                                            $selectedLoanTypeId = old(
                                                'member_loan_limit_loan_type_id',
                                                $memberLoanLimit->member_loan_limit_loan_type_id
                                            );
                                        @endphp

                                        <option
                                            value="{{ $loanType->loan_type_id }}"
                                            data-name="{{ $loanType->loan_type_name }}"
                                            data-code="{{ $loanType->loan_type_code }}"
                                            data-max="{{ number_format((float) $loanType->loan_type_max_amount, 2, '.', '') }}"
                                            {{ (string) $selectedLoanTypeId === (string) $loanType->loan_type_id ? 'selected' : '' }}
                                        >
                                            {{ $loanType->loan_type_name }}

                                            @if (!empty($loanType->loan_type_code))
                                                — {{ $loanType->loan_type_code }}
                                            @endif

                                            — Maximum KES
                                            {{ number_format((float) $loanType->loan_type_max_amount, 2) }}

                                            @if ((int) ($loanType->loan_type_active ?? 1) !== 1)
                                                — INACTIVE
                                            @endif
                                        </option>
                                    @endforeach
                                </select>

                                @error('member_loan_limit_loan_type_id')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            {{-- Standard maximum --}}
                            <div class="col-md-5 form-group mb-4">
                                <label
                                    for="standardMaximumDisplay"
                                    class="form-label fw-semibold"
                                >
                                    Standard Loan-Type Maximum
                                </label>

                                <div class="input-group">
                                    <span class="input-group-text">
                                        KES
                                    </span>

                                    <input
                                        type="text"
                                        id="standardMaximumDisplay"
                                        class="form-control bg-light"
                                        value=""
                                        readonly
                                    >
                                </div>
                            </div>

                            {{-- Individual limit --}}
                            <div class="col-md-7 form-group mb-4">
                                <label
                                    for="member_loan_limit_amount"
                                    class="form-label fw-semibold"
                                >
                                    Individual Maximum Limit
                                    <span class="text-danger">*</span>
                                </label>

                                <div class="input-group">
                                    <span class="input-group-text">
                                        KES
                                    </span>

                                    <input
                                        type="number"
                                        name="member_loan_limit_amount"
                                        id="member_loan_limit_amount"
                                        class="form-control @error('member_loan_limit_amount') is-invalid @enderror"
                                        value="{{ old(
                                            'member_loan_limit_amount',
                                            number_format(
                                                (float) $memberLoanLimit->member_loan_limit_amount,
                                                2,
                                                '.',
                                                ''
                                            )
                                        ) }}"
                                        min="0.01"
                                        step="0.01"
                                        required
                                    >

                                    @error('member_loan_limit_amount')
                                        <div class="invalid-feedback">
                                            {{ $message }}
                                        </div>
                                    @enderror
                                </div>

                                <small
                                    id="limitHelp"
                                    class="form-text text-muted"
                                >
                                    The amount must be lower than the selected
                                    loan type maximum.
                                </small>
                            </div>

                            {{-- Difference --}}
                            <div class="col-md-5 form-group mb-4">
                                <label
                                    for="differenceDisplay"
                                    class="form-label fw-semibold"
                                >
                                    Reduction from Standard Limit
                                </label>

                                <div class="input-group">
                                    <span class="input-group-text">
                                        KES
                                    </span>

                                    <input
                                        type="text"
                                        id="differenceDisplay"
                                        class="form-control bg-light"
                                        value=""
                                        readonly
                                    >
                                </div>
                            </div>

                            {{-- Active --}}
                            <div class="col-md-12 form-group mb-4">
                                <div class="border rounded p-3 bg-light">
                                    <div class="d-flex justify-content-between align-items-center">

                                        <div>
                                            <div class="fw-semibold">
                                                Activate this individual limit
                                            </div>

                                            <div class="text-muted text-small">
                                                When inactive, the member uses the
                                                normal maximum configured under the
                                                selected loan type.
                                            </div>
                                        </div>

                                        <div>
                                            <input
                                                type="hidden"
                                                name="member_loan_limit_active"
                                                value="0"
                                            >

                                            <label class="switch switch-primary mb-0">
                                                <input
                                                    type="checkbox"
                                                    name="member_loan_limit_active"
                                                    value="1"
                                                    {{ (int) old(
                                                        'member_loan_limit_active',
                                                        $memberLoanLimit->member_loan_limit_active
                                                    ) === 1 ? 'checked' : '' }}
                                                >

                                                <span class="slider"></span>
                                            </label>
                                        </div>

                                    </div>
                                </div>

                                @error('member_loan_limit_active')
                                    <div class="text-danger text-small mt-1">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            {{-- Audit details --}}
                            <div class="col-md-12 mb-4">
                                <div class="border rounded p-3">
                                    <div class="row">

                                        <div class="col-md-4">
                                            <div class="text-muted text-small">
                                                Record ID
                                            </div>

                                            <strong>
                                                {{ $memberLoanLimit->member_loan_limit_id }}
                                            </strong>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="text-muted text-small">
                                                Last saved
                                            </div>

                                            <strong>
                                                {{ $memberLoanLimit->member_loan_limit_transdate
                                                    ? \Carbon\Carbon::parse(
                                                        $memberLoanLimit->member_loan_limit_transdate
                                                    )->format('d M Y H:i')
                                                    : 'N/A' }}
                                            </strong>
                                        </div>

                                        <div class="col-md-4">
                                            <div class="text-muted text-small">
                                                Last IP address
                                            </div>

                                            <strong>
                                                {{ $memberLoanLimit->member_loan_limit_ip ?: 'N/A' }}
                                            </strong>
                                        </div>

                                    </div>
                                </div>
                            </div>

                            {{-- Actions --}}
                            <div class="col-md-12">
                                <div class="d-flex justify-content-end gap-2 border-top pt-4">

                                    <a
                                        href="{{ route('loans.member_limits.index') }}"
                                        class="btn btn-outline-secondary"
                                    >
                                        Cancel
                                    </a>

                                    <button
                                        type="submit"
                                        class="btn btn-primary"
                                    >
                                        <i class="i-Disk me-1"></i>
                                        Update Individual Limit
                                    </button>

                                </div>
                            </div>

                        </div>
                    </form>

                </div>
            </div>

        </div>

        {{-- Current record summary --}}
        <div class="col-lg-4">

            <div class="card mb-4">
                <div class="card-body">

                    <h5 class="card-title">
                        Current Limit Summary
                    </h5>

                    <div class="summary-row">
                        <span class="text-muted">
                            Individual limit
                        </span>

                        <strong class="text-primary">
                            KES
                            {{ number_format(
                                (float) $memberLoanLimit->member_loan_limit_amount,
                                2
                            ) }}
                        </strong>
                    </div>

                    <div class="summary-row">
                        <span class="text-muted">
                            Status
                        </span>

                        @if ((int) $memberLoanLimit->member_loan_limit_active === 1)
                            <span class="badge bg-success">
                                Active
                            </span>
                        @else
                            <span class="badge bg-secondary">
                                Inactive
                            </span>
                        @endif
                    </div>

                    <div class="summary-row">
                        <span class="text-muted">
                            Deleted
                        </span>

                        <strong>
                            {{ $memberLoanLimit->member_loan_limit_deleted }}
                        </strong>
                    </div>

                </div>
            </div>

            <div class="card border-warning mb-4">
                <div class="card-body">
                    <div class="d-flex">
                        <i class="i-Warning-Window text-warning text-24 me-3"></i>

                        <div>
                            <h6 class="mb-1">
                                Limit validation
                            </h6>

                            <p class="text-muted mb-0">
                                The updated amount must remain strictly lower
                                than the standard maximum of the selected
                                loan type.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            @if ($currentPeriod)
                <div class="card">
                    <div class="card-body">
                        <div class="text-muted text-small">
                            Active accounting period
                        </div>

                        <h5 class="mb-0">
                            {{ $currentPeriod->period_name }}
                        </h5>
                    </div>
                </div>
            @endif

        </div>

    </div>

</div>

<style>
    .text-small {
        font-size: 12px;
    }

    .btn-rounded {
        border-radius: 50px;
    }

    .summary-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 13px 0;
        border-bottom: 1px solid #eeeeee;
    }

    .summary-row:last-child {
        border-bottom: 0;
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const loanTypeSelect = document.getElementById(
            'member_loan_limit_loan_type_id'
        );

        const limitInput = document.getElementById(
            'member_loan_limit_amount'
        );

        const standardMaximumDisplay = document.getElementById(
            'standardMaximumDisplay'
        );

        const differenceDisplay = document.getElementById(
            'differenceDisplay'
        );

        const limitHelp = document.getElementById('limitHelp');

        function getSelectedMaximum() {
            const selectedOption =
                loanTypeSelect.options[loanTypeSelect.selectedIndex];

            if (!selectedOption || !selectedOption.dataset.max) {
                return 0;
            }

            return parseFloat(selectedOption.dataset.max) || 0;
        }

        function formatAmount(amount) {
            return Number(amount).toLocaleString('en-KE', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function updateMaximum() {
            const maximum = getSelectedMaximum();

            if (maximum > 0) {
                standardMaximumDisplay.value = formatAmount(maximum);

                limitInput.max = Math.max(
                    0.01,
                    maximum - 0.01
                ).toFixed(2);

                limitHelp.textContent =
                    'The highest permitted individual limit is KES ' +
                    formatAmount(maximum - 0.01) +
                    '.';
            } else {
                standardMaximumDisplay.value = '';
                limitInput.removeAttribute('max');

                limitHelp.textContent =
                    'The amount must be lower than the selected loan type maximum.';
            }

            updateDifference();
        }

        function updateDifference() {
            const maximum = getSelectedMaximum();
            const individualLimit = parseFloat(limitInput.value) || 0;

            if (maximum > 0 && individualLimit > 0) {
                differenceDisplay.value = formatAmount(
                    Math.max(0, maximum - individualLimit)
                );
            } else {
                differenceDisplay.value = '';
            }
        }

        loanTypeSelect.addEventListener('change', updateMaximum);
        limitInput.addEventListener('input', updateDifference);

        updateMaximum();
    });
</script>

@endsection