@extends('layouts.app')

@section('styles')
<style>
    .loan-type-page {
        max-width: 1500px;
        margin: 0 auto;
    }

    .loan-form-card {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.03);
        overflow: hidden;
    }

    .loan-form-card .card-header {
        background: #fafafa;
        border-bottom: 1px solid #e5e7eb;
        padding: 16px 20px;
    }

    .loan-form-card .card-body {
        padding: 22px 20px;
    }

    .section-number {
        display: inline-flex;
        width: 28px;
        height: 28px;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: #f1f3f5;
        color: #343a40;
        font-size: 13px;
        font-weight: 700;
        margin-right: 9px;
    }

    .section-title {
        display: flex;
        align-items: center;
        margin: 0;
        font-size: 17px;
        font-weight: 700;
        color: #1f2937;
    }

    .section-description {
        margin: 5px 0 0 38px;
        color: #6b7280;
        font-size: 12px;
        line-height: 1.5;
    }

    .form-label-title {
        font-weight: 600;
        color: #30343b;
        margin-bottom: 6px;
    }

    .form-text {
        font-size: 11px;
        line-height: 1.45;
    }

    .subsection-title {
        font-size: 13px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .025em;
        color: #4b5563;
        padding-bottom: 9px;
        margin-bottom: 16px;
        border-bottom: 1px solid #eceff2;
    }

    .default-interest-panel {
        background: #fafbfc;
        border: 1px solid #dde2e7;
        border-radius: 7px;
        padding: 18px;
    }

    .default-interest-note {
        border-left: 3px solid #6c757d;
        padding: 10px 12px;
        margin-top: 4px;
        background: #fff;
        color: #59616a;
        font-size: 12px;
        line-height: 1.55;
    }

    .field-group-muted {
        opacity: .62;
    }

    .account-help {
        min-height: 34px;
    }

    .required-star {
        color: #dc3545;
    }

    .form-actions {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 16px 18px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        margin-bottom: 30px;
    }

    .form-actions .btn {
        min-width: 145px;
    }

    @media (max-width: 767.98px) {
        .loan-form-card .card-body {
            padding: 18px 15px;
        }

        .loan-form-card .card-header {
            padding: 14px 15px;
        }

        .section-description {
            margin-left: 0;
            margin-top: 8px;
        }

        .default-interest-panel {
            padding: 14px;
        }

        .form-actions {
            flex-direction: column;
            align-items: stretch;
        }

        .form-actions .btn {
            width: 100%;
        }
    }
</style>
@endsection


@section('content')

<div class="loan-type-page">

    {{-- ===============================================================
    | PAGE HEADER
    ================================================================ --}}
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <div>
            <h1 class="mb-1">Edit Loan Type</h1>

            <div class="text-muted small">
                {{ $loanType->loan_type_name ?? 'Loan Product' }}
                @if(!empty($loanType->loan_type_code))
                    &nbsp;·&nbsp; {{ $loanType->loan_type_code }}
                @endif
            </div>
        </div>

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
                        data-fullscreen=""
                    ></i>
                </li>
            </ul>
        </div>
    </div>

    <div class="separator-breadcrumb border-top"></div>


    {{-- ===============================================================
    | MESSAGES
    ================================================================ --}}
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <strong>The loan type could not be updated.</strong>

            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif


    <form
        action="{{ route('loans.types.update', $loanType->loan_type_id) }}"
        method="POST"
        id="loanTypeForm"
        autocomplete="off"
    >
        @csrf
        @method('PUT')


        {{-- ===============================================================
        | 1. PRODUCT IDENTITY
        ================================================================ --}}
        <div class="card loan-form-card mb-4">

            <div class="card-header">
                <h3 class="section-title">
                    <span class="section-number">1</span>
                    Product Identity
                </h3>

                <p class="section-description">
                    Basic product identification and whether the loan is currently available for use.
                </p>
            </div>

            <div class="card-body">

                <div class="row">

                    <div class="col-lg-6 col-md-6 form-group mb-3">
                        <label
                            class="form-label-title"
                            for="loan_type_name"
                        >
                            Loan Type Name
                            <span class="required-star">*</span>
                        </label>

                        <input
                            type="text"
                            class="form-control @error('loan_type_name') is-invalid @enderror"
                            id="loan_type_name"
                            name="loan_type_name"
                            value="{{ old('loan_type_name', $loanType->loan_type_name) }}"
                            placeholder="e.g. Emergency Loan"
                            maxlength="250"
                            required
                        >

                        @error('loan_type_name')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>


                    <div class="col-lg-3 col-md-3 form-group mb-3">

                        <label
                            class="form-label-title"
                            for="loan_type_code"
                        >
                            Loan Type Code
                            <span class="required-star">*</span>
                        </label>

                        <input
                            type="text"
                            class="form-control @error('loan_type_code') is-invalid @enderror"
                            id="loan_type_code"
                            name="loan_type_code"
                            value="{{ old('loan_type_code', $loanType->loan_type_code) }}"
                            placeholder="e.g. EML"
                            maxlength="100"
                            required
                        >

                        @error('loan_type_code')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                        <small class="form-text text-muted">
                            Short internal code used to identify the product.
                        </small>
                    </div>


                    <div class="col-lg-3 col-md-3 form-group mb-3">

                        <label
                            class="form-label-title"
                            for="loan_type_active"
                        >
                            Product Status
                            <span class="required-star">*</span>
                        </label>

                        <select
                            class="form-control @error('loan_type_active') is-invalid @enderror"
                            id="loan_type_active"
                            name="loan_type_active"
                            required
                        >
                            <option
                                value="1"
                                {{ (string) old(
                                    'loan_type_active',
                                    $loanType->loan_type_active ?? 1
                                ) === '1' ? 'selected' : '' }}
                            >
                                Active
                            </option>

                            <option
                                value="0"
                                {{ (string) old(
                                    'loan_type_active',
                                    $loanType->loan_type_active ?? 1
                                ) === '0' ? 'selected' : '' }}
                            >
                                Inactive
                            </option>
                        </select>

                        @error('loan_type_active')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                        <small class="form-text text-muted">
                            Inactive products should not accept new applications.
                        </small>

                    </div>

                </div>
            </div>
        </div>



        {{-- ===============================================================
        | 2. PRICING & REPAYMENT
        ================================================================ --}}
        <div class="card loan-form-card mb-4">

            <div class="card-header">
                <h3 class="section-title">
                    <span class="section-number">2</span>
                    Pricing &amp; Repayment
                </h3>

                <p class="section-description">
                    Normal loan interest, maximum repayment period and maximum amount available under this product.
                </p>
            </div>

            <div class="card-body">

                <div class="row">

                    <div class="col-lg-3 col-md-6 form-group mb-3">

                        <label
                            class="form-label-title"
                            for="loan_type_interest"
                        >
                            Normal Interest Rate
                            <span class="required-star">*</span>
                        </label>

                        <div class="input-group">
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                class="form-control @error('loan_type_interest') is-invalid @enderror"
                                id="loan_type_interest"
                                name="loan_type_interest"
                                value="{{ old(
                                    'loan_type_interest',
                                    $loanType->loan_type_interest
                                ) }}"
                                placeholder="e.g. 5"
                                required
                            >

                            <div class="input-group-append">
                                <span class="input-group-text">%</span>
                            </div>
                        </div>

                        @error('loan_type_interest')
                            <div class="text-danger small mt-1">
                                {{ $message }}
                            </div>
                        @enderror

                        <small class="form-text text-muted">
                            Standard interest rate for this loan product.
                        </small>

                    </div>


                    <div class="col-lg-3 col-md-6 form-group mb-3">

                        <label
                            class="form-label-title"
                            for="loan_type_interest_type"
                        >
                            Interest Calculation
                            <span class="required-star">*</span>
                        </label>

                        <select
                            class="form-control @error('loan_type_interest_type') is-invalid @enderror"
                            id="loan_type_interest_type"
                            name="loan_type_interest_type"
                            required
                        >
                            <option value="">
                                Select calculation method
                            </option>

                            <option
                                value="REDUCING BALANCE"
                                {{ old(
                                    'loan_type_interest_type',
                                    $loanType->loan_type_interest_type
                                ) === 'REDUCING BALANCE' ? 'selected' : '' }}
                            >
                                Reducing Balance
                            </option>

                            <option
                                value="FIXED INTEREST"
                                {{ old(
                                    'loan_type_interest_type',
                                    $loanType->loan_type_interest_type
                                ) === 'FIXED INTEREST' ? 'selected' : '' }}
                            >
                                Fixed Interest
                            </option>
                        </select>

                        @error('loan_type_interest_type')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                    </div>


                    <div class="col-lg-3 col-md-6 form-group mb-3">

                        <label
                            class="form-label-title"
                            for="loan_type_duration"
                        >
                            Maximum Duration
                            <span class="required-star">*</span>
                        </label>

                        <div class="input-group">

                            <input
                                type="number"
                                min="1"
                                class="form-control @error('loan_type_duration') is-invalid @enderror"
                                id="loan_type_duration"
                                name="loan_type_duration"
                                value="{{ old(
                                    'loan_type_duration',
                                    $loanType->loan_type_duration
                                ) }}"
                                placeholder="e.g. 12"
                                required
                            >

                            <div class="input-group-append">
                                <span class="input-group-text">
                                    Months
                                </span>
                            </div>

                        </div>

                        @error('loan_type_duration')
                            <div class="text-danger small mt-1">
                                {{ $message }}
                            </div>
                        @enderror

                    </div>


                    <div class="col-lg-3 col-md-6 form-group mb-3">

                        <label
                            class="form-label-title"
                            for="loan_type_max_amount"
                        >
                            Maximum Loan Amount
                            <span class="required-star">*</span>
                        </label>

                        <div class="input-group">

                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    KES
                                </span>
                            </div>

                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                class="form-control @error('loan_type_max_amount') is-invalid @enderror"
                                id="loan_type_max_amount"
                                name="loan_type_max_amount"
                                value="{{ old(
                                    'loan_type_max_amount',
                                    $loanType->loan_type_max_amount
                                ) }}"
                                placeholder="e.g. 50000"
                                required
                            >

                        </div>

                        @error('loan_type_max_amount')
                            <div class="text-danger small mt-1">
                                {{ $message }}
                            </div>
                        @enderror

                    </div>

                </div>

            </div>
        </div>



        {{-- ===============================================================
        | 3. DEFAULT & DELINQUENCY
        ================================================================ --}}
        <div class="card loan-form-card mb-4">

            <div class="card-header">
                <h3 class="section-title">
                    <span class="section-number">3</span>
                    Default &amp; Delinquency
                </h3>

                <p class="section-description">
                    Controls when an overdue loan becomes eligible for automatic default interest.
                </p>
            </div>

            <div class="card-body">

                <div class="default-interest-panel">

                    <div class="row">

                        <div class="col-lg-4 col-md-6 form-group mb-3">

                            <label
                                class="form-label-title"
                                for="loan_type_auto_interest_on_period_change"
                            >
                                Automatic Default Interest
                                <span class="required-star">*</span>
                            </label>

                            <select
                                class="form-control @error('loan_type_auto_interest_on_period_change') is-invalid @enderror"
                                id="loan_type_auto_interest_on_period_change"
                                name="loan_type_auto_interest_on_period_change"
                                required
                            >
                                <option
                                    value="0"
                                    {{ (string) old(
                                        'loan_type_auto_interest_on_period_change',
                                        $loanType->loan_type_auto_interest_on_period_change ?? 0
                                    ) === '0' ? 'selected' : '' }}
                                >
                                    Disabled
                                </option>

                                <option
                                    value="1"
                                    {{ (string) old(
                                        'loan_type_auto_interest_on_period_change',
                                        $loanType->loan_type_auto_interest_on_period_change ?? 0
                                    ) === '1' ? 'selected' : '' }}
                                >
                                    Enabled
                                </option>
                            </select>

                            @error('loan_type_auto_interest_on_period_change')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror

                            <small class="form-text text-muted">
                                When disabled, automatic default-interest processing will ignore this product.
                            </small>

                        </div>


                        <div
                            class="col-lg-4 col-md-6 form-group mb-3 dfi-dependent"
                        >

                            <label
                                class="form-label-title"
                                for="loan_type_grace_days_after_due"
                            >
                                Grace Period After Due Date
                                <span class="required-star">*</span>
                            </label>

                            <div class="input-group">

                                <input
                                    type="number"
                                    min="0"
                                    max="3650"
                                    step="1"
                                    class="form-control @error('loan_type_grace_days_after_due') is-invalid @enderror"
                                    id="loan_type_grace_days_after_due"
                                    name="loan_type_grace_days_after_due"
                                    value="{{ old(
                                        'loan_type_grace_days_after_due',
                                        $loanType->loan_type_grace_days_after_due ?? 45
                                    ) }}"
                                    required
                                >

                                <div class="input-group-append">
                                    <span class="input-group-text">
                                        Days
                                    </span>
                                </div>

                            </div>

                            @error('loan_type_grace_days_after_due')
                                <div class="text-danger small mt-1">
                                    {{ $message }}
                                </div>
                            @enderror

                            <small class="form-text text-muted">
                                Calendar days allowed after the contractual repayment due date.
                            </small>

                        </div>


                        <div
                            class="col-lg-4 col-md-6 form-group mb-3 dfi-dependent"
                        >

                            <label
                                class="form-label-title"
                                for="loan_type_default_interest"
                            >
                                Default Interest Rate
                            </label>

                            <div class="input-group">

                                <input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="form-control @error('loan_type_default_interest') is-invalid @enderror"
                                    id="loan_type_default_interest"
                                    name="loan_type_default_interest"
                                    value="{{ old(
                                        'loan_type_default_interest',
                                        $loanType->loan_type_default_interest
                                    ) }}"
                                    placeholder="Leave blank to use normal rate"
                                >

                                <div class="input-group-append">
                                    <span class="input-group-text">
                                        %
                                    </span>
                                </div>

                            </div>

                            @error('loan_type_default_interest')
                                <div class="text-danger small mt-1">
                                    {{ $message }}
                                </div>
                            @enderror

                            <small class="form-text text-muted">
                                Optional. Leave blank to use the normal loan interest rate.
                            </small>

                        </div>

                    </div>


                    <div class="default-interest-note">

                        <strong>How this works:</strong>

                        the loan first reaches its contractual repayment due date.
                        The grace period is then counted from that due date.

                        If the repayment remains unpaid after the grace period,
                        the automatic default-interest process may apply interest.

                        <br><br>

                        <strong>Example:</strong>
                        payment due on the 12th + 15 grace days =
                        default eligibility from the 27th.

                        <br>

                        If Default Interest Rate is blank, the system uses the
                        product's normal interest rate of

                        <strong id="normalRatePreview">
                            {{ number_format(
                                (float) old(
                                    'loan_type_interest',
                                    $loanType->loan_type_interest ?? 0
                                ),
                                2
                            ) }}%
                        </strong>.

                    </div>

                </div>

            </div>
        </div>



        {{-- ===============================================================
        | 4. ELIGIBILITY & AUTOMATION
        ================================================================ --}}
        <div class="card loan-form-card mb-4">

            <div class="card-header">
                <h3 class="section-title">
                    <span class="section-number">4</span>
                    Eligibility &amp; Application Automation
                </h3>

                <p class="section-description">
                    Determines member qualification, guarantor requirements and how far the loan application can proceed automatically.
                </p>
            </div>

            <div class="card-body">

                <div class="subsection-title">
                    Eligibility Rules
                </div>

                <div class="row">

                    <div class="col-lg-3 col-md-6 form-group mb-3">

                        <label
                            class="form-label-title"
                            for="loan_type_share_factor"
                        >
                            Share Factor
                            <span class="required-star">*</span>
                        </label>

                        <input
                            type="number"
                            step="1"
                            min="0"
                            class="form-control @error('loan_type_share_factor') is-invalid @enderror"
                            id="loan_type_share_factor"
                            name="loan_type_share_factor"
                            value="{{ old(
                                'loan_type_share_factor',
                                $loanType->loan_type_share_factor ?? 3
                            ) }}"
                            required
                        >

                        @error('loan_type_share_factor')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                        <small class="form-text text-muted">
                            Lending multiplier applied against qualifying shares.
                        </small>

                    </div>


                    <div class="col-lg-3 col-md-6 form-group mb-3">

                        <label
                            class="form-label-title"
                            for="loan_type_guaranteable_percent"
                        >
                            Guarantee Required
                            <span class="required-star">*</span>
                        </label>

                        <div class="input-group">

                            <input
                                type="number"
                                step="1"
                                min="0"
                                max="100"
                                class="form-control @error('loan_type_guaranteable_percent') is-invalid @enderror"
                                id="loan_type_guaranteable_percent"
                                name="loan_type_guaranteable_percent"
                                value="{{ old(
                                    'loan_type_guaranteable_percent',
                                    $loanType->loan_type_guaranteable_percent
                                ) }}"
                                required
                            >

                            <div class="input-group-append">
                                <span class="input-group-text">
                                    %
                                </span>
                            </div>

                        </div>

                        @error('loan_type_guaranteable_percent')
                            <div class="text-danger small mt-1">
                                {{ $message }}
                            </div>
                        @enderror

                        <small class="form-text text-muted">
                            Set to 0 where guarantors are not required.
                        </small>

                    </div>


                    <div class="col-lg-3 col-md-6 form-group mb-3">

                        <label
                            class="form-label-title"
                            for="loan_type_qualification_period"
                        >
                            Minimum Membership
                            <span class="required-star">*</span>
                        </label>

                        <div class="input-group">

                            <input
                                type="number"
                                min="0"
                                class="form-control @error('loan_type_qualification_period') is-invalid @enderror"
                                id="loan_type_qualification_period"
                                name="loan_type_qualification_period"
                                value="{{ old(
                                    'loan_type_qualification_period',
                                    $loanType->loan_type_qualification_period
                                ) }}"
                                required
                            >

                            <div class="input-group-append">
                                <span class="input-group-text">
                                    Months
                                </span>
                            </div>

                        </div>

                        @error('loan_type_qualification_period')
                            <div class="text-danger small mt-1">
                                {{ $message }}
                            </div>
                        @enderror

                    </div>


                    <div class="col-lg-3 col-md-6 form-group mb-3">

                        <label
                            class="form-label-title"
                            for="loan_type_max_qualification_period"
                        >
                            Maximum Membership
                        </label>

                        <div class="input-group">

                            <input
                                type="number"
                                min="0"
                                class="form-control @error('loan_type_max_qualification_period') is-invalid @enderror"
                                id="loan_type_max_qualification_period"
                                name="loan_type_max_qualification_period"
                                value="{{ old(
                                    'loan_type_max_qualification_period',
                                    $loanType->loan_type_max_qualification_period
                                ) }}"
                                placeholder="No limit"
                            >

                            <div class="input-group-append">
                                <span class="input-group-text">
                                    Months
                                </span>
                            </div>

                        </div>

                        @error('loan_type_max_qualification_period')
                            <div class="text-danger small mt-1">
                                {{ $message }}
                            </div>
                        @enderror

                        <small class="form-text text-muted">
                            Leave blank where no maximum applies.
                        </small>

                    </div>

                </div>


                <div class="subsection-title mt-3">
                    Application Automation
                </div>


                <div class="row">

                    <div class="col-lg-4 col-md-6 form-group mb-3">

                        <label
                            class="form-label-title"
                            for="loan_type_instant_qualification"
                        >
                            Instant Qualification
                            <span class="required-star">*</span>
                        </label>

                        <select
                            class="form-control @error('loan_type_instant_qualification') is-invalid @enderror"
                            id="loan_type_instant_qualification"
                            name="loan_type_instant_qualification"
                            required
                        >
                            <option
                                value="0"
                                {{ (string) old(
                                    'loan_type_instant_qualification',
                                    $loanType->loan_type_instant_qualification ?? 0
                                ) === '0' ? 'selected' : '' }}
                            >
                                No
                            </option>

                            <option
                                value="1"
                                {{ (string) old(
                                    'loan_type_instant_qualification',
                                    $loanType->loan_type_instant_qualification ?? 0
                                ) === '1' ? 'selected' : '' }}
                            >
                                Yes
                            </option>
                        </select>

                        @error('loan_type_instant_qualification')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                        <small class="form-text text-muted">
                            Controls automatic qualification against the applicable product rules.
                        </small>

                    </div>


                    <div class="col-lg-4 col-md-6 form-group mb-3">

                        <label
                            class="form-label-title"
                            for="loan_type_auto_approval"
                        >
                            Automatic Approval
                            <span class="required-star">*</span>
                        </label>

                        <select
                            class="form-control @error('loan_type_auto_approval') is-invalid @enderror"
                            id="loan_type_auto_approval"
                            name="loan_type_auto_approval"
                            required
                        >
                            <option
                                value="0"
                                {{ (string) old(
                                    'loan_type_auto_approval',
                                    $loanType->loan_type_auto_approval ?? 0
                                ) === '0' ? 'selected' : '' }}
                            >
                                No
                            </option>

                            <option
                                value="1"
                                {{ (string) old(
                                    'loan_type_auto_approval',
                                    $loanType->loan_type_auto_approval ?? 0
                                ) === '1' ? 'selected' : '' }}
                            >
                                Yes
                            </option>
                        </select>

                        @error('loan_type_auto_approval')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                        <small class="form-text text-muted">
                            Allows eligible applications to proceed through automatic approval.
                        </small>

                    </div>


                    <div class="col-lg-4 col-md-6 form-group mb-3">

                        <label
                            class="form-label-title"
                            for="loan_type_instant_disbursement"
                        >
                            Instant Disbursement
                            <span class="required-star">*</span>
                        </label>

                        <select
                            class="form-control @error('loan_type_instant_disbursement') is-invalid @enderror"
                            id="loan_type_instant_disbursement"
                            name="loan_type_instant_disbursement"
                            required
                        >
                            <option
                                value="0"
                                {{ (string) old(
                                    'loan_type_instant_disbursement',
                                    $loanType->loan_type_instant_disbursement ?? 0
                                ) === '0' ? 'selected' : '' }}
                            >
                                No
                            </option>

                            <option
                                value="1"
                                {{ (string) old(
                                    'loan_type_instant_disbursement',
                                    $loanType->loan_type_instant_disbursement ?? 0
                                ) === '1' ? 'selected' : '' }}
                            >
                                Yes
                            </option>
                        </select>

                        @error('loan_type_instant_disbursement')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                        <small class="form-text text-muted">
                            Starts automatic payout only after the loan has been successfully approved.
                        </small>

                    </div>

                </div>


                <div class="alert alert-info mb-0 mt-2">

                    <strong>Note:</strong>

                    qualification, approval and disbursement are separate controls.
                    Enabling one does not automatically enable the others.

                </div>

            </div>
        </div>



        {{-- ===============================================================
        | 5. RISK, INSURANCE & CHARGES
        ================================================================ --}}
        <div class="card loan-form-card mb-4">

            <div class="card-header">

                <h3 class="section-title">
                    <span class="section-number">5</span>
                    Risk, Insurance &amp; Charges
                </h3>

                <p class="section-description">
                    Configure CRB checks, insurance and commission charges independently.
                </p>

            </div>


            <div class="card-body">

                {{-- CRB --}}
                <div class="subsection-title">
                    CRB Configuration
                </div>

                <div class="row">

                    <div class="col-lg-3 col-md-6 form-group mb-3">

                        <label
                            class="form-label-title"
                            for="loan_type_crb_required"
                        >
                            CRB Check Required
                            <span class="required-star">*</span>
                        </label>

                        <select
                            class="form-control @error('loan_type_crb_required') is-invalid @enderror"
                            id="loan_type_crb_required"
                            name="loan_type_crb_required"
                            required
                        >
                            <option
                                value="N"
                                {{ old(
                                    'loan_type_crb_required',
                                    $loanType->loan_type_crb_required ?? 'N'
                                ) === 'N' ? 'selected' : '' }}
                            >
                                No
                            </option>

                            <option
                                value="Y"
                                {{ old(
                                    'loan_type_crb_required',
                                    $loanType->loan_type_crb_required ?? 'N'
                                ) === 'Y' ? 'selected' : '' }}
                            >
                                Yes
                            </option>
                        </select>

                        @error('loan_type_crb_required')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                    </div>


                    <div class="col-lg-3 col-md-6 form-group mb-3 crb-field">

                        <label
                            class="form-label-title"
                            for="loan_type_crb_charge"
                        >
                            CRB Charge
                        </label>

                        <div class="input-group">

                            <div class="input-group-prepend">
                                <span class="input-group-text">
                                    KES
                                </span>
                            </div>

                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                class="form-control @error('loan_type_crb_charge') is-invalid @enderror"
                                id="loan_type_crb_charge"
                                name="loan_type_crb_charge"
                                value="{{ old(
                                    'loan_type_crb_charge',
                                    $loanType->loan_type_crb_charge ?? 0
                                ) }}"
                            >

                        </div>

                        @error('loan_type_crb_charge')
                            <div class="text-danger small mt-1">
                                {{ $message }}
                            </div>
                        @enderror

                    </div>


                    <div class="col-lg-6 col-md-12 form-group mb-3 crb-field">

                        <label
                            class="form-label-title"
                            for="loan_type_crb_effect"
                        >
                            CRB Charge Treatment
                        </label>

                        <select
                            class="form-control @error('loan_type_crb_effect') is-invalid @enderror"
                            id="loan_type_crb_effect"
                            name="loan_type_crb_effect"
                        >
                            <option value="">
                                Select treatment
                            </option>

                            <option
                                value="ADD_TO_LOAN"
                                {{ old(
                                    'loan_type_crb_effect',
                                    $loanType->loan_type_crb_effect ?? ''
                                ) === 'ADD_TO_LOAN' ? 'selected' : '' }}
                            >
                                Add to Loan Balance
                            </option>

                            <option
                                value="DEDUCT_FROM_DISBURSEMENT"
                                {{ old(
                                    'loan_type_crb_effect',
                                    $loanType->loan_type_crb_effect ?? ''
                                ) === 'DEDUCT_FROM_DISBURSEMENT' ? 'selected' : '' }}
                            >
                                Deduct from Amount Disbursed
                            </option>
                        </select>

                        @error('loan_type_crb_effect')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                    </div>

                </div>


                {{-- Insurance --}}
                <div class="subsection-title mt-4">
                    Insurance Configuration
                </div>

                <div class="row">

                    <div class="col-lg-3 col-md-6 form-group mb-3">

                        <label
                            class="form-label-title"
                            for="loan_type_insurable"
                        >
                            Insurance Required
                            <span class="required-star">*</span>
                        </label>

                        <select
                            class="form-control @error('loan_type_insurable') is-invalid @enderror"
                            id="loan_type_insurable"
                            name="loan_type_insurable"
                            required
                        >
                            <option
                                value="N"
                                {{ old(
                                    'loan_type_insurable',
                                    $loanType->loan_type_insurable ?? 'Y'
                                ) === 'N' ? 'selected' : '' }}
                            >
                                No
                            </option>

                            <option
                                value="Y"
                                {{ old(
                                    'loan_type_insurable',
                                    $loanType->loan_type_insurable ?? 'Y'
                                ) === 'Y' ? 'selected' : '' }}
                            >
                                Yes
                            </option>
                        </select>

                        @error('loan_type_insurable')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                    </div>


                    <div class="col-lg-9 col-md-6 form-group mb-3 insurance-field">

                        <label
                            class="form-label-title"
                            for="loan_type_insurance_effect"
                        >
                            Insurance Treatment
                        </label>

                        <select
                            class="form-control @error('loan_type_insurance_effect') is-invalid @enderror"
                            id="loan_type_insurance_effect"
                            name="loan_type_insurance_effect"
                        >
                            <option value="">
                                Select treatment
                            </option>

                            <option
                                value="ADD_TO_LOAN"
                                {{ old(
                                    'loan_type_insurance_effect',
                                    $loanType->loan_type_insurance_effect ?? ''
                                ) === 'ADD_TO_LOAN' ? 'selected' : '' }}
                            >
                                Add to Loan Balance
                            </option>

                            <option
                                value="DEDUCT_FROM_DISBURSEMENT"
                                {{ old(
                                    'loan_type_insurance_effect',
                                    $loanType->loan_type_insurance_effect ?? ''
                                ) === 'DEDUCT_FROM_DISBURSEMENT' ? 'selected' : '' }}
                            >
                                Deduct from Amount Disbursed
                            </option>

                        </select>

                        @error('loan_type_insurance_effect')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                    </div>

                </div>


                {{-- Commission --}}
                <div class="subsection-title mt-4">
                    Commission Configuration
                </div>

                <div class="row">

                    <div class="col-lg-3 col-md-6 form-group mb-3">

                        <label
                            class="form-label-title"
                            for="loan_type_commission_required"
                        >
                            Commission Required
                            <span class="required-star">*</span>
                        </label>

                        <select
                            class="form-control @error('loan_type_commission_required') is-invalid @enderror"
                            id="loan_type_commission_required"
                            name="loan_type_commission_required"
                            required
                        >
                            <option
                                value="N"
                                {{ old(
                                    'loan_type_commission_required',
                                    $loanType->loan_type_commission_required ?? 'N'
                                ) === 'N' ? 'selected' : '' }}
                            >
                                No
                            </option>

                            <option
                                value="Y"
                                {{ old(
                                    'loan_type_commission_required',
                                    $loanType->loan_type_commission_required ?? 'N'
                                ) === 'Y' ? 'selected' : '' }}
                            >
                                Yes
                            </option>
                        </select>

                        @error('loan_type_commission_required')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                    </div>


                    <div class="col-lg-3 col-md-6 form-group mb-3 commission-field">

                        <label
                            class="form-label-title"
                            for="loan_type_commission_type"
                        >
                            Commission Type
                        </label>

                        <select
                            class="form-control @error('loan_type_commission_type') is-invalid @enderror"
                            id="loan_type_commission_type"
                            name="loan_type_commission_type"
                        >
                            <option value="">
                                Select type
                            </option>

                            <option
                                value="FIXED"
                                {{ old(
                                    'loan_type_commission_type',
                                    $loanType->loan_type_commission_type ?? ''
                                ) === 'FIXED' ? 'selected' : '' }}
                            >
                                Fixed Amount
                            </option>

                            <option
                                value="PERCENT"
                                {{ old(
                                    'loan_type_commission_type',
                                    $loanType->loan_type_commission_type ?? ''
                                ) === 'PERCENT' ? 'selected' : '' }}
                            >
                                Percentage
                            </option>
                        </select>

                        @error('loan_type_commission_type')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                    </div>


                    <div class="col-lg-3 col-md-6 form-group mb-3 commission-field">

                        <label
                            class="form-label-title"
                            for="loan_type_commission_value"
                        >
                            Commission Value
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            class="form-control @error('loan_type_commission_value') is-invalid @enderror"
                            id="loan_type_commission_value"
                            name="loan_type_commission_value"
                            value="{{ old(
                                'loan_type_commission_value',
                                $loanType->loan_type_commission_value ?? 0
                            ) }}"
                        >

                        @error('loan_type_commission_value')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                    </div>


                    <div class="col-lg-3 col-md-6 form-group mb-3 commission-field">

                        <label
                            class="form-label-title"
                            for="loan_type_commission_effect"
                        >
                            Commission Treatment
                        </label>

                        <select
                            class="form-control @error('loan_type_commission_effect') is-invalid @enderror"
                            id="loan_type_commission_effect"
                            name="loan_type_commission_effect"
                        >
                            <option value="">
                                Select treatment
                            </option>

                            <option
                                value="ADD_TO_LOAN"
                                {{ old(
                                    'loan_type_commission_effect',
                                    $loanType->loan_type_commission_effect ?? ''
                                ) === 'ADD_TO_LOAN' ? 'selected' : '' }}
                            >
                                Add to Loan Balance
                            </option>

                            <option
                                value="DEDUCT_FROM_DISBURSEMENT"
                                {{ old(
                                    'loan_type_commission_effect',
                                    $loanType->loan_type_commission_effect ?? ''
                                ) === 'DEDUCT_FROM_DISBURSEMENT' ? 'selected' : '' }}
                            >
                                Deduct from Amount Disbursed
                            </option>
                        </select>

                        @error('loan_type_commission_effect')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                    </div>

                </div>

            </div>
        </div>



        {{-- ===============================================================
        | 6. ACCOUNTING
        ================================================================ --}}
        <div class="card loan-form-card mb-4">

            <div class="card-header">

                <h3 class="section-title">
                    <span class="section-number">6</span>
                    Accounting Setup
                </h3>

                <p class="section-description">
                    Accounts used when loan principal, interest and commission transactions are posted to the SACCO ledger.
                </p>

            </div>


            <div class="card-body">

                <div class="row">

                    <div class="col-lg-4 col-md-6 form-group mb-3">

                        <label
                            class="form-label-title"
                            for="loan_type_acount"
                        >
                            Loan Receivable Account
                            <span class="required-star">*</span>
                        </label>

                        <select
                            class="form-control @error('loan_type_acount') is-invalid @enderror"
                            id="loan_type_acount"
                            name="loan_type_acount"
                            required
                        >
                            <option value="">
                                Select asset account
                            </option>

                            @foreach($assetAccounts as $account)

                                <option
                                    value="{{ $account->sub_account_id }}"
                                    {{ (string) old(
                                        'loan_type_acount',
                                        $loanType->loan_type_acount
                                    ) === (string) $account->sub_account_id
                                        ? 'selected'
                                        : '' }}
                                >
                                    {{ $account->sub_account_name }}
                                    ({{ $account->main_account_code }}/{{ $account->sub_account_code }})
                                </option>

                            @endforeach
                        </select>

                        @error('loan_type_acount')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                        <small class="form-text text-muted account-help">
                            Asset account holding the outstanding loan receivable.
                        </small>

                    </div>


                    <div class="col-lg-4 col-md-6 form-group mb-3">

                        <label
                            class="form-label-title"
                            for="loan_type_int_account"
                        >
                            Interest Income Account
                            <span class="required-star">*</span>
                        </label>

                        <select
                            class="form-control @error('loan_type_int_account') is-invalid @enderror"
                            id="loan_type_int_account"
                            name="loan_type_int_account"
                            required
                        >
                            <option value="">
                                Select income account
                            </option>

                            @foreach($incomeAccounts as $account)

                                <option
                                    value="{{ $account->sub_account_id }}"
                                    {{ (string) old(
                                        'loan_type_int_account',
                                        $loanType->loan_type_int_account
                                    ) === (string) $account->sub_account_id
                                        ? 'selected'
                                        : '' }}
                                >
                                    {{ $account->sub_account_name }}
                                    ({{ $account->main_account_code }}/{{ $account->sub_account_code }})
                                </option>

                            @endforeach
                        </select>

                        @error('loan_type_int_account')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                        <small class="form-text text-muted account-help">
                            Income account credited for normal and applicable default interest.
                        </small>

                    </div>


                    <div class="col-lg-4 col-md-12 form-group mb-3">

                        <label
                            class="form-label-title"
                            for="loan_type_comm_account"
                        >
                            Commission Account
                            <span class="required-star">*</span>
                        </label>

                        <select
                            class="form-control @error('loan_type_comm_account') is-invalid @enderror"
                            id="loan_type_comm_account"
                            name="loan_type_comm_account"
                            required
                        >
                            <option value="">
                                Select income or liability account
                            </option>

                            @foreach($incomeLiabilityAccounts as $account)

                                <option
                                    value="{{ $account->sub_account_id }}"
                                    {{ (string) old(
                                        'loan_type_comm_account',
                                        $loanType->loan_type_comm_account
                                    ) === (string) $account->sub_account_id
                                        ? 'selected'
                                        : '' }}
                                >
                                    {{ $account->sub_account_name }}
                                    ({{ $account->main_account_code }}/{{ $account->sub_account_code }})
                                </option>

                            @endforeach
                        </select>

                        @error('loan_type_comm_account')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                        <small class="form-text text-muted account-help">
                            Account used when commission is charged for this product.
                        </small>

                    </div>

                </div>

            </div>
        </div>



        {{-- ===============================================================
        | ACTIONS
        ================================================================ --}}
        <div class="form-actions">

            <button
                type="submit"
                class="btn btn-primary"
                id="submitLoanTypeButton"
            >
                Update Loan Type
            </button>

            <a
                href="{{ route('loans.types') }}"
                class="btn btn-light"
            >
                Cancel
            </a>

        </div>

    </form>

</div>



<script>
document.addEventListener('DOMContentLoaded', function () {

    const crbRequired =
        document.getElementById('loan_type_crb_required');

    const crbCharge =
        document.getElementById('loan_type_crb_charge');

    const crbEffect =
        document.getElementById('loan_type_crb_effect');


    const insuranceRequired =
        document.getElementById('loan_type_insurable');

    const insuranceEffect =
        document.getElementById('loan_type_insurance_effect');


    const commissionRequired =
        document.getElementById('loan_type_commission_required');

    const commissionType =
        document.getElementById('loan_type_commission_type');

    const commissionValue =
        document.getElementById('loan_type_commission_value');

    const commissionEffect =
        document.getElementById('loan_type_commission_effect');


    const autoDefaultInterest =
        document.getElementById(
            'loan_type_auto_interest_on_period_change'
        );

    const normalInterestRate =
        document.getElementById('loan_type_interest');

    const normalRatePreview =
        document.getElementById('normalRatePreview');


    const form =
        document.getElementById('loanTypeForm');

    const submitButton =
        document.getElementById('submitLoanTypeButton');


    function setGroupState(selector, enabled) {

        document.querySelectorAll(selector).forEach(function (container) {

            container.classList.toggle(
                'field-group-muted',
                !enabled
            );

            container
                .querySelectorAll('input, select, textarea')
                .forEach(function (field) {
                    field.disabled = !enabled;
                });

        });

    }


    function updateCrbFields() {

        const enabled =
            crbRequired
            && crbRequired.value === 'Y';

        setGroupState(
            '.crb-field',
            enabled
        );

        if (crbEffect) {

            crbEffect.required =
                enabled
                && crbCharge
                && parseFloat(
                    crbCharge.value || '0'
                ) > 0;

        }

    }


    function updateInsuranceFields() {

        const enabled =
            insuranceRequired
            && insuranceRequired.value === 'Y';

        setGroupState(
            '.insurance-field',
            enabled
        );

        if (insuranceEffect) {
            insuranceEffect.required = enabled;
        }

    }


    function updateCommissionFields() {

        const enabled =
            commissionRequired
            && commissionRequired.value === 'Y';

        setGroupState(
            '.commission-field',
            enabled
        );

        if (commissionType) {
            commissionType.required = enabled;
        }

        if (commissionValue) {
            commissionValue.required = enabled;
        }

        if (commissionEffect) {
            commissionEffect.required = enabled;
        }

    }


    /*
    |--------------------------------------------------------------------------
    | Default-interest settings
    |--------------------------------------------------------------------------
    |
    | Do NOT disable these inputs.
    |
    | The grace period and optional rate remain stored even when automation
    | is switched off, so they are immediately available if the product is
    | enabled again.
    |--------------------------------------------------------------------------
    */
    function updateDefaultInterestPresentation() {

        const enabled =
            autoDefaultInterest
            && autoDefaultInterest.value === '1';

        document
            .querySelectorAll('.dfi-dependent')
            .forEach(function (container) {

                container.classList.toggle(
                    'field-group-muted',
                    !enabled
                );

            });

    }


    function updateNormalRatePreview() {

        if (
            !normalInterestRate
            || !normalRatePreview
        ) {
            return;
        }

        const rate =
            parseFloat(
                normalInterestRate.value || '0'
            );

        normalRatePreview.textContent =
            rate.toFixed(2) + '%';

    }


    if (crbRequired) {
        crbRequired.addEventListener(
            'change',
            updateCrbFields
        );
    }

    if (crbCharge) {
        crbCharge.addEventListener(
            'input',
            updateCrbFields
        );
    }

    if (insuranceRequired) {
        insuranceRequired.addEventListener(
            'change',
            updateInsuranceFields
        );
    }

    if (commissionRequired) {
        commissionRequired.addEventListener(
            'change',
            updateCommissionFields
        );
    }

    if (autoDefaultInterest) {
        autoDefaultInterest.addEventListener(
            'change',
            updateDefaultInterestPresentation
        );
    }

    if (normalInterestRate) {
        normalInterestRate.addEventListener(
            'input',
            updateNormalRatePreview
        );
    }


    if (form) {

        form.addEventListener(
            'submit',
            function () {

                if (submitButton) {

                    submitButton.disabled = true;

                    submitButton.textContent =
                        'Updating...';

                }

            }
        );

    }


    updateCrbFields();
    updateInsuranceFields();
    updateCommissionFields();

    updateDefaultInterestPresentation();
    updateNormalRatePreview();

});
</script>

@endsection