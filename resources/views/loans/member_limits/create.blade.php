@extends('layouts.app')

@section('content')

<div class="main-content pt-4">

    {{-- Page heading --}}
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <div>
            <h1 class="mb-1">Add Individual Loan Limit</h1>

            <ul class="mb-0">
                <li>
                    <a href="{{ url('/dashboard') }}">Dashboard</a>
                </li>

                <li>
                    <a href="{{ route('loans.member_limits.index') }}">
                        Individual Loan Limits
                    </a>
                </li>

                <li>Add Limit</li>
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
            <strong>The record could not be saved.</strong>

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
                        Member Loan Limit Details
                    </div>

                    <p class="text-muted mb-4">
                        Select the member and loan type, then enter the
                        lower maximum amount that applies specifically to
                        that member.
                    </p>

                    <form
                        method="POST"
                        action="{{ route('loans.member_limits.store') }}"
                        id="memberLoanLimitForm"
                    >
                        @csrf

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
                                        <option
                                            value="{{ $member->member_id }}"
                                            {{ (string) old('member_loan_limit_member_id') === (string) $member->member_id ? 'selected' : '' }}
                                        >
                                            {{ $member->member_name }}
                                            — {{ $member->member_sacco_id ?: 'No SACCO number' }}

                                            @if (!empty($member->member_national_id))
                                                — ID {{ $member->member_national_id }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>

                                @error('member_loan_limit_member_id')
                                    <div class="invalid-feedback">
                                        {{ $message }}
                                    </div>
                                @enderror

                                <small class="form-text text-muted">
                                    Only active, non-deleted members are available.
                                </small>
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
                                        <option
                                            value="{{ $loanType->loan_type_id }}"
                                            data-name="{{ $loanType->loan_type_name }}"
                                            data-code="{{ $loanType->loan_type_code }}"
                                            data-max="{{ number_format((float) $loanType->loan_type_max_amount, 2, '.', '') }}"
                                            {{ (string) old('member_loan_limit_loan_type_id') === (string) $loanType->loan_type_id ? 'selected' : '' }}
                                        >
                                            {{ $loanType->loan_type_name }}

                                            @if (!empty($loanType->loan_type_code))
                                                — {{ $loanType->loan_type_code }}
                                            @endif

                                            — Maximum KES
                                            {{ number_format((float) $loanType->loan_type_max_amount, 2) }}
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
                                        placeholder="Select loan type"
                                        readonly
                                    >
                                </div>
                            </div>

                            {{-- Individual amount --}}
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
                                        value="{{ old('member_loan_limit_amount') }}"
                                        min="0.01"
                                        step="0.01"
                                        placeholder="Enter member's special maximum"
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
                                        placeholder="0.00"
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
                                                When inactive, the normal loan-type
                                                maximum will apply to the member.
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
                                                    {{ old('member_loan_limit_active', 1) ? 'checked' : '' }}
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
                                        id="saveButton"
                                    >
                                        <i class="i-Disk me-1"></i>
                                        Save Individual Limit
                                    </button>

                                </div>
                            </div>

                        </div>
                    </form>

                </div>
            </div>

        </div>

        {{-- Guidance --}}
        <div class="col-lg-4">

            <div class="card mb-4">
                <div class="card-body">

                    <h5 class="card-title">
                        How It Works
                    </h5>

                    <div class="limit-guide-item">
                        <div class="guide-number">1</div>

                        <div>
                            <strong>Select a member</strong>

                            <p class="text-muted mb-0">
                                Only members requiring a special limit need
                                to be configured.
                            </p>
                        </div>
                    </div>

                    <div class="limit-guide-item">
                        <div class="guide-number">2</div>

                        <div>
                            <strong>Select a loan type</strong>

                            <p class="text-muted mb-0">
                                The standard maximum will be displayed
                                automatically.
                            </p>
                        </div>
                    </div>

                    <div class="limit-guide-item">
                        <div class="guide-number">3</div>

                        <div>
                            <strong>Enter a lower limit</strong>

                            <p class="text-muted mb-0">
                                The member-specific amount must be below the
                                loan-type maximum.
                            </p>
                        </div>
                    </div>

                </div>
            </div>

            <div class="card border-warning mb-4">
                <div class="card-body">
                    <div class="d-flex">
                        <i class="i-Warning-Window text-warning text-24 me-3"></i>

                        <div>
                            <h6 class="mb-1">
                                Important
                            </h6>

                            <p class="text-muted mb-0">
                                Deleting or deactivating this record causes
                                the member to revert to the standard maximum
                                configured for the loan type.
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

    .limit-guide-item {
        display: flex;
        gap: 12px;
        padding: 15px 0;
        border-bottom: 1px solid #eeeeee;
    }

    .limit-guide-item:last-child {
        border-bottom: 0;
    }

    .guide-number {
        width: 32px;
        height: 32px;
        min-width: 32px;
        border-radius: 50%;
        background: #663399;
        color: #ffffff;
        display: flex;
        justify-content: center;
        align-items: center;
        font-weight: 700;
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

                /*
                 * Strictly lower than the standard maximum.
                 */
                limitInput.max = Math.max(0.01, maximum - 0.01).toFixed(2);

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