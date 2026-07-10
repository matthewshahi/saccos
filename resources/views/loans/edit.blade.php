@extends('layouts.app')

@section('content')

<style>
    .loan-type-page {
        --lt-primary: #4f46e5;
        --lt-primary-soft: rgba(79, 70, 229, 0.08);
        --lt-border: #e5e7eb;
        --lt-muted: #6b7280;
        --lt-dark: #111827;
        --lt-success: #059669;
        --lt-warning: #d97706;
        --lt-danger: #dc2626;
    }

    .loan-type-page .page-heading {
        margin-bottom: 0.25rem;
        color: var(--lt-dark);
        font-size: 1.55rem;
        font-weight: 700;
    }

    .loan-type-page .page-subtitle {
        margin-bottom: 0;
        color: var(--lt-muted);
    }

    .loan-type-page .form-section-card {
        overflow: hidden;
        border: 1px solid var(--lt-border);
        border-radius: 12px;
        box-shadow: 0 4px 18px rgba(17, 24, 39, 0.04);
    }

    .loan-type-page .form-section-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 1rem;
        padding: 1rem 1.25rem;
        background: #fafafa;
        border-bottom: 1px solid var(--lt-border);
    }

    .loan-type-page .form-section-number {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 34px;
        height: 34px;
        padding: 0 10px;
        border-radius: 10px;
        color: var(--lt-primary);
        background: var(--lt-primary-soft);
        font-weight: 700;
    }

    .loan-type-page .form-section-title {
        margin-bottom: 0.2rem;
        color: var(--lt-dark);
        font-size: 1rem;
        font-weight: 700;
    }

    .loan-type-page .form-section-description {
        margin-bottom: 0;
        color: var(--lt-muted);
        font-size: 0.86rem;
    }

    .loan-type-page .form-section-body {
        padding: 1.25rem;
    }

    .loan-type-page .field-group-title {
        margin-bottom: 1rem;
        color: var(--lt-muted);
        font-size: 0.78rem;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .loan-type-page .form-group label {
        margin-bottom: 0.4rem;
        color: #374151;
        font-weight: 600;
    }

    .loan-type-page .form-control {
        min-height: 42px;
        border-color: #d1d5db;
        border-radius: 8px;
    }

    .loan-type-page .form-control:focus {
        border-color: var(--lt-primary);
        box-shadow: 0 0 0 0.15rem rgba(79, 70, 229, 0.15);
    }

    .loan-type-page .help-text {
        display: block;
        margin-top: 0.4rem;
        color: var(--lt-muted);
        font-size: 0.8rem;
        line-height: 1.45;
    }

    .loan-type-page .setting-panel {
        height: 100%;
        padding: 1rem;
        border: 1px solid var(--lt-border);
        border-radius: 10px;
        background: #ffffff;
        transition: 0.2s ease;
    }

    .loan-type-page .setting-panel.active-setting {
        border-color: rgba(79, 70, 229, 0.35);
        background: var(--lt-primary-soft);
    }

    .loan-type-page .setting-panel-title {
        margin-bottom: 0.3rem;
        color: var(--lt-dark);
        font-weight: 700;
    }

    .loan-type-page .setting-panel-text {
        margin-bottom: 0;
        color: var(--lt-muted);
        font-size: 0.82rem;
        line-height: 1.45;
    }

    .loan-type-page .modern-switch-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }

    .loan-type-page .modern-switch {
        position: relative;
        width: 48px;
        min-width: 48px;
        height: 26px;
        margin: 0;
    }

    .loan-type-page .modern-switch input[type="checkbox"] {
        width: 0;
        height: 0;
        opacity: 0;
    }

    .loan-type-page .modern-switch-slider {
        position: absolute;
        inset: 0;
        border-radius: 999px;
        background: #cbd5e1;
        cursor: pointer;
        transition: 0.2s ease;
    }

    .loan-type-page .modern-switch-slider::before {
        position: absolute;
        top: 3px;
        left: 3px;
        width: 20px;
        height: 20px;
        border-radius: 50%;
        background: #ffffff;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.18);
        content: "";
        transition: 0.2s ease;
    }

    .loan-type-page .modern-switch input:checked + .modern-switch-slider {
        background: var(--lt-primary);
    }

    .loan-type-page .modern-switch input:checked + .modern-switch-slider::before {
        transform: translateX(22px);
    }

    .loan-type-page .configuration-note {
        margin-top: 0.9rem;
        padding: 0.8rem 0.9rem;
        border-left: 4px solid var(--lt-primary);
        border-radius: 8px;
        color: #3730a3;
        background: #eef2ff;
        font-size: 0.82rem;
        line-height: 1.5;
    }

    .loan-type-page .dependent-fields-disabled {
        opacity: 0.55;
    }

    .loan-type-page .sticky-actions {
        position: sticky;
        bottom: 0;
        z-index: 20;
        border: 1px solid var(--lt-border);
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.96);
        box-shadow: 0 -4px 16px rgba(17, 24, 39, 0.06);
        backdrop-filter: blur(8px);
    }

    .loan-type-page .required-marker {
        color: var(--lt-danger);
    }

    .loan-type-page .status-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.35rem 0.7rem;
        border-radius: 999px;
        font-size: 0.78rem;
        font-weight: 600;
    }

    .loan-type-page .status-badge.active {
        color: #065f46;
        background: #d1fae5;
    }

    .loan-type-page .status-badge.inactive {
        color: #991b1b;
        background: #fee2e2;
    }

    @media (max-width: 767.98px) {
        .loan-type-page .modern-switch-row {
            align-items: flex-start;
        }

        .loan-type-page .sticky-actions .btn {
            margin-bottom: 0.5rem;
        }
    }
</style>

<div class="loan-type-page">

    {{-- ================================================================
    | PAGE HEADING
    ================================================================= --}}
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-heading">
                Edit Loan Type
            </h1>

            <p class="page-subtitle">
                Configure eligibility, pricing, risk controls, automation
                and accounting.
            </p>
        </div>

        <div class="header-part-right">
            <ul>
                @if (Auth::check())
                    <li>
                        {{ Auth::user()->member_name }}
                    </li>
                @endif

                @if (isset($currentPeriod) && $currentPeriod)
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

    {{-- ================================================================
    | MESSAGES
    ================================================================= --}}
    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <strong>
                The loan type could not be updated.
            </strong>

            <div class="mt-1">
                Please correct the following:
            </div>

            <ul class="mb-0 mt-2">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        action="{{ route('loans.types.update', $loanType->loan_type_id) }}"
        method="POST"
        id="loanTypeForm"
    >
        @csrf
        @method('PUT')

        {{-- ================================================================
        | 1. PRODUCT IDENTITY
        ================================================================= --}}
        <div class="card form-section-card mb-4">

            <div class="form-section-header">
                <div class="d-flex align-items-start">

                    <div class="form-section-number mr-3">
                        1
                    </div>

                    <div>
                        <div class="form-section-title">
                            Product identity and availability
                        </div>

                        <p class="form-section-description">
                            Define the loan product name, code and whether
                            members can currently use it.
                        </p>
                    </div>

                </div>

                @if (
                    (int) old(
                        'loan_type_active',
                        $loanType->loan_type_active ?? 1
                    ) === 1
                )
                    <span
                        class="status-badge active"
                        id="loanStatusBadge"
                    >
                        Active
                    </span>
                @else
                    <span
                        class="status-badge inactive"
                        id="loanStatusBadge"
                    >
                        Inactive
                    </span>
                @endif
            </div>

            <div class="form-section-body">
                <div class="row">

                    <div class="col-lg-6 form-group mb-3">
                        <label for="loan_type_name">
                            Loan type name
                            <span class="required-marker">*</span>
                        </label>

                        <input
                            type="text"
                            class="form-control @error('loan_type_name') is-invalid @enderror"
                            id="loan_type_name"
                            name="loan_type_name"
                            value="{{ old(
                                'loan_type_name',
                                $loanType->loan_type_name
                            ) }}"
                            placeholder="e.g. Emergency Loan"
                            maxlength="250"
                            required
                        >

                        @error('loan_type_name')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                        <small class="help-text">
                            The member-facing name of this loan product.
                        </small>
                    </div>

                    <div class="col-lg-3 form-group mb-3">
                        <label for="loan_type_code">
                            Loan type code
                            <span class="required-marker">*</span>
                        </label>

                        <input
                            type="text"
                            class="form-control @error('loan_type_code') is-invalid @enderror"
                            id="loan_type_code"
                            name="loan_type_code"
                            value="{{ old(
                                'loan_type_code',
                                $loanType->loan_type_code
                            ) }}"
                            placeholder="e.g. EML"
                            maxlength="100"
                            required
                        >

                        @error('loan_type_code')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                        <small class="help-text">
                            Short internal code used in loan references and reports.
                        </small>
                    </div>

                    <div class="col-lg-3 form-group mb-3">
                        <label for="loan_type_active">
                            Product status
                            <span class="required-marker">*</span>
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

                        <small class="help-text">
                            Inactive loan types should not accept new applications.
                        </small>
                    </div>

                </div>
            </div>
        </div>

        {{-- ================================================================
        | 2. PRICING AND REPAYMENT
        ================================================================= --}}
        <div class="card form-section-card mb-4">

            <div class="form-section-header">
                <div class="d-flex align-items-start">

                    <div class="form-section-number mr-3">
                        2
                    </div>

                    <div>
                        <div class="form-section-title">
                            Pricing, limits and repayment
                        </div>

                        <p class="form-section-description">
                            Configure interest, monthly interest treatment,
                            maximum principal, duration and security requirements.
                        </p>
                    </div>

                </div>
            </div>

            <div class="form-section-body">

                <div class="field-group-title">
                    Interest and repayment
                </div>

                <div class="row">

                    <div class="col-lg-3 col-md-6 form-group mb-3">
                        <label for="loan_type_interest">
                            Interest rate (%)
                            <span class="required-marker">*</span>
                        </label>

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
                            placeholder="e.g. 12"
                            required
                        >

                        @error('loan_type_interest')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="col-lg-3 col-md-6 form-group mb-3">
                        <label for="loan_type_interest_type">
                            Interest method
                            <span class="required-marker">*</span>
                        </label>

                        <select
                            class="form-control @error('loan_type_interest_type') is-invalid @enderror"
                            id="loan_type_interest_type"
                            name="loan_type_interest_type"
                            required
                        >
                            <option value="">
                                Select interest method
                            </option_interest',
                                $loanType->loan_type_interest
                            ) }}"
                            placeholder="e.g. 12"
                            required
                        >

                        @error('loan_type_interest')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @>

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
                        <label for="loan_type_duration">
                            Maximum duration
                            <span class="required-marker">*</span>
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

                            @error('loan_type_duration')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 form-group mb-3">
                        <label for="loan_type_max_amount">
                            Maximum principal
                            <span class="required-marker">*</span>
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

                            @error('loan_type_max_amount')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>

                </div>

                <hr>

                {{-- ========================================================
                | MONTHLY INTEREST PROCESSING
                ======================================================== --}}
                <div class="field-group-title">
                    Monthly interest processing
                </div>

                <div class="row">
                    <div class="col-lg-12 mb-3">

                        <div
                            class="setting-panel"
                            id="autoInterestPeriodChangePanel"
                        >
                            <div class="modern-switch-row">

                                <div class="pr-3">
                                    <div class="setting-panel-title">
                                        Auto-apply interest when the accounting period changes
                                    </div>

                                    <p class="setting-panel-text">
                                        When enabled, this loan type may be
                                        included in monthly interest processing
                                        when a different YYYYMM accounting period
                                        is activated.
                                    </p>
                                </div>

                                <div>
                                    <input
                                        type="hidden"
                                        name="loan_type_auto_interest_on_period_change"
                                        value="0"
                                    >

                                    <label
                                        class="modern-switch"
                                        for="loan_type_auto_interest_on_period_change"
                                    >
                                        <input
                                            type="checkbox"
                                            id="loan_type_auto_interest_on_period_change"
                                            name="loan_type_auto_interest_on_period_change"
                                            value="1"
                                            {{ (int) old(
                                                'loan_type_auto_interest_on_period_change',
                                                $loanType->loan_type_auto_interest_on_period_change ?? 0
                                            ) === 1 ? 'checked' : '' }}
                                        >

                                        <span class="modern-switch-slider"></span>
                                    </label>
                                </div>

                            </div>

                            <div class="configuration-note">
                                <strong>Configuration only.</strong>
                                This screen does not calculate, post or deduct
                                interest. The setting is disabled unless it is
                                explicitly switched on.
                            </div>
                        </div>

                        @error('loan_type_auto_interest_on_period_change')
                            <div class="text-danger mt-2">
                                {{ $message }}
                            </div>
                        @enderror

                    </div>
                </div>

                <hr>

                <div class="field-group-title">
                    Security and lending limits
                </div>

                <div class="row">

                    <div class="col-lg-4 form-group mb-3">
                        <label for="loan_type_share_factor">
                            Share factor
                            <span class="required-marker">*</span>
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            class="form-control @error('loan_type_share_factor') is-invalid @enderror"
                            id="loan_type_share_factor"
                            name="loan_type_share_factor"
                            value="{{ old(
                                'loan_type_share_factor',
                                $loanType->loan_type_share_factor ?? 3
                            ) }}"
                            placeholder="e.g. 3"
                            required
                        >

                        @error('loan_type_share_factor')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                        <small class="help-text">
                            Maximum lending multiplier applied against qualifying
                            member shares and capital.
                        </small>
                    </div>

                    <div class="col-lg-4 form-group mb-3">
                        <label for="loan_type_guaranteable_percent">
                            Amount requiring guarantee (%)
                            <span class="required-marker">*</span>
                        </label>

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
                            placeholder="e.g. 100"
                            required
                        >

                        @error('loan_type_guaranteable_percent')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                        <small class="help-text">
                            Use zero where guarantors are not required.
                        </small>
                    </div>

                </div>
            </div>
        </div>

        {{-- ================================================================
        | 3. QUALIFICATION AND AUTOMATION
        ================================================================= --}}
        <div class="card form-section-card mb-4">

            <div class="form-section-header">
                <div class="d-flex align-items-start">

                    <div class="form-section-number mr-3">
                        3
                    </div>

                    <div>
                        <div class="form-section-title">
                            Qualification and automation
                        </div>

                        <p class="form-section-description">
                            Define membership eligibility and configure
                            qualification and payout independently.
                        </p>
                    </div>

                </div>
            </div>

            <div class="form-section-body">

                <div class="field-group-title">
                    Membership eligibility
                </div>

                <div class="row">

                    <div class="col-lg-4 form-group mb-3">
                        <label for="loan_type_qualification_period">
                            Minimum membership period
                            <span class="required-marker">*</span>
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
                                placeholder="e.g. 6"
                                required
                            >

                            <div class="input-group-append">
                                <span class="input-group-text">
                                    Months
                                </span>
                            </div>

                            @error('loan_type_qualification_period')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <small class="help-text">
                            Minimum number of months a member must have
                            belonged to the SACCO.
                        </small>
                    </div>

                    <div class="col-lg-4 form-group mb-3">
                        <label for="loan_type_max_qualification_period">
                            Maximum membership period
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
                                placeholder="No upper limit"
                            >

                            <div class="input-group-append">
                                <span class="input-group-text">
                                    Months
                                </span>
                            </div>

                            @error('loan_type_max_qualification_period')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        <small class="help-text">
                            Optional. Leave blank when there is no maximum
                            membership period.
                        </small>
                    </div>

                </div>

                <hr>

                <div class="field-group-title">
                    Automated loan processing
                </div>

                <div class="row">

                    <div class="col-lg-6 mb-3">
                        <div
                            class="setting-panel"
                            id="instantQualificationPanel"
                        >
                            <div class="modern-switch-row">

                                <div class="pr-3">
                                    <div class="setting-panel-title">
                                        Instant qualification
                                    </div>

                                    <p class="setting-panel-text">
                                        Allow the eligibility engine to assess
                                        the member immediately without waiting
                                        for manual qualification review.
                                    </p>
                                </div>

                                <div>
                                    <input
                                        type="hidden"
                                        name="loan_type_instant_qualification"
                                        value="0"
                                    >

                                    <label
                                        class="modern-switch"
                                        for="loan_type_instant_qualification"
                                    >
                                        <input
                                            type="checkbox"
                                            id="loan_type_instant_qualification"
                                            name="loan_type_instant_qualification"
                                            value="1"
                                            {{ (int) old(
                                                'loan_type_instant_qualification',
                                                $loanType->loan_type_instant_qualification ?? 0
                                            ) === 1 ? 'checked' : '' }}
                                        >

                                        <span class="modern-switch-slider"></span>
                                    </label>
                                </div>

                            </div>
                        </div>

                        @error('loan_type_instant_qualification')
                            <div class="text-danger mt-2">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="col-lg-6 mb-3">
                        <div
                            class="setting-panel"
                            id="instantDisbursementPanel"
                        >
                            <div class="modern-switch-row">

                                <div class="pr-3">
                                    <div class="setting-panel-title">
                                        Instant disbursement
                                    </div>

                                    <p class="setting-panel-text">
                                        After final approval and all mandatory
                                        controls pass, allow the system to initiate
                                        payout through an approved bank or
                                        mobile-money channel.
                                    </p>
                                </div>

                                <div>
                                    <input
                                        type="hidden"
                                        name="loan_type_instant_disbursement"
                                        value="0"
                                    >

                                    <label
                                        class="modern-switch"
                                        for="loan_type_instant_disbursement"
                                    >
                                        <input
                                            type="checkbox"
                                            id="loan_type_instant_disbursement"
                                            name="loan_type_instant_disbursement"
                                            value="1"
                                            {{ (int) old(
                                                'loan_type_instant_disbursement',
                                                $loanType->loan_type_instant_disbursement ?? 0
                                            ) === 1 ? 'checked' : '' }}
                                        >

                                        <span class="modern-switch-slider"></span>
                                    </label>
                                </div>

                            </div>
                        </div>

                        @error('loan_type_instant_disbursement')
                            <div class="text-danger mt-2">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                </div>

                <div class="configuration-note">
                    Instant qualification and instant disbursement apply at
                    different stages and may be configured independently.
                    Instant disbursement must still follow final approval,
                    risk, compliance and account-validation controls.
                </div>

            </div>
        </div>

        {{-- ================================================================
        | 4. RISK, INSURANCE AND CHARGES
        ================================================================= --}}
        <div class="card form-section-card mb-4">

            <div class="form-section-header">
                <div class="d-flex align-items-start">

                    <div class="form-section-number mr-3">
                        4
                    </div>

                    <div>
                        <div class="form-section-title">
                            Risk checks, insurance and charges
                        </div>

                        <p class="form-section-description">
                            Configure CRB requirements, insurance treatment
                            and loan-processing charges.
                        </p>
                    </div>

                </div>
            </div>

            <div class="form-section-body">

                {{-- CRB --}}
                <div class="field-group-title">
                    Credit reference bureau
                </div>

                <div class="row">

                    <div class="col-lg-3 form-group mb-3">
                        <label for="loan_type_crb_required">
                            CRB check required
                            <span class="required-marker">*</span>
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

                    <div class="col-lg-3 form-group mb-3 crb-dependent-field">
                        <label for="loan_type_crb_charge">
                            CRB charge
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
                                placeholder="e.g. 100"
                            >

                            @error('loan_type_crb_charge')
                                <div class="invalid-feedback">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    </div>

                    <div class="col-lg-6 form-group mb-3 crb-dependent-field">
                        <label for="loan_type_crb_effect">
                            CRB charge treatment
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
                                Add to loan balance
                            </option>

                            <option
                                value="DEDUCT_FROM_DISBURSEMENT"
                                {{ old(
                                    'loan_type_crb_effect',
                                    $loanType->loan_type_crb_effect ?? ''
                                ) === 'DEDUCT_FROM_DISBURSEMENT' ? 'selected' : '' }}
                            >
                                Deduct from amount disbursed
                            </option>
                        </select>

                        @error('loan_type_crb_effect')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                </div>

                <hr>

                {{-- INSURANCE --}}
                <div class="field-group-title">
                    Insurance
                </div>

                <div class="row">

                    <div class="col-lg-3 form-group mb-3">
                        <label for="loan_type_insurable">
                            Insurance required
                            <span class="required-marker">*</span>
                        </label>

                        <select
                            class="form-control @error('loan_type_insurable') is-invalid @enderror"
                            id="loan_type_insurable"
                            name="loan_type_insurable"
                            required
                        >
                            <option
                                value="Y"
                                {{ old(
                                    'loan_type_insurable',
                                    $loanType->loan_type_insurable
                                ) === 'Y' ? 'selected' : '' }}
                            >
                                Yes
                            </option>

                            <option
                                value="N"
                                {{ old(
                                    'loan_type_insurable',
                                    $loanType->loan_type_insurable
                                ) === 'N' ? 'selected' : '' }}
                            >
                                No
                            </option>
                        </select>

                        @error('loan_type_insurable')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="col-lg-6 form-group mb-3 insurance-dependent-field">
                        <label for="loan_type_insurance_effect">
                            Insurance treatment
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
                                Add insurance to loan balance
                            </option>

                            <option
                                value="DEDUCT_FROM_DISBURSEMENT"
                                {{ old(
                                    'loan_type_insurance_effect',
                                    $loanType->loan_type_insurance_effect ?? ''
                                ) === 'DEDUCT_FROM_DISBURSEMENT' ? 'selected' : '' }}
                            >
                                Deduct insurance from amount disbursed
                            </option>
                        </select>

                        @error('loan_type_insurance_effect')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                        <small class="help-text">
                            Determines whether insurance increases the member's
                            loan balance or reduces the net cash received.
                        </small>
                    </div>

                </div>

                <hr>

                {{-- COMMISSION --}}
                <div class="field-group-title">
                    Commission and processing charges
                </div>

                <div class="row">

                    <div class="col-lg-3 form-group mb-3">
                        <label for="loan_type_commission_required">
                            Commission required
                            <span class="required-marker">*</span>
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

                    <div class="col-lg-3 form-group mb-3 commission-dependent-field">
                        <label for="loan_type_commission_type">
                            Commission type
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
                                Fixed amount
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

                    <div class="col-lg-3 form-group mb-3 commission-dependent-field">
                        <label for="loan_type_commission_value">
                            Commission value
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
                            placeholder="e.g. 500 or 2.5"
                        >

                        @error('loan_type_commission_value')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror
                    </div>

                    <div class="col-lg-3 form-group mb-3 commission-dependent-field">
                        <label for="loan_type_commission_effect">
                            Commission treatment
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
                                Add to loan balance
                            </option>

                            <option
                                value="DEDUCT_FROM_DISBURSEMENT"
                                {{ old(
                                    'loan_type_commission_effect',
                                    $loanType->loan_type_commission_effect ?? ''
                                ) === 'DEDUCT_FROM_DISBURSEMENT' ? 'selected' : '' }}
                            >
                                Deduct from amount disbursed
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

        {{-- ================================================================
        | 5. ACCOUNTING
        ================================================================= --}}
        <div class="card form-section-card mb-4">

            <div class="form-section-header">
                <div class="d-flex align-items-start">

                    <div class="form-section-number mr-3">
                        5
                    </div>

                    <div>
                        <div class="form-section-title">
                            Accounting setup
                        </div>

                        <p class="form-section-description">
                            Map principal, interest and commission entries
                            to the correct general-ledger accounts.
                        </p>
                    </div>

                </div>
            </div>

            <div class="form-section-body">

                <div class="alert alert-light border mb-4">
                    These accounts determine how approved loans, interest and
                    charges are posted into the SACCO ledger. Confirm them
                    carefully before saving.
                </div>

                <div class="row">

                    <div class="col-lg-4 form-group mb-3">
                        <label for="loan_type_acount">
                            Loan principal account
                            <span class="required-marker">*</span>
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

                            @foreach ($assetAccounts as $acc)
                                <option
                                    value="{{ $acc->sub_account_id }}"
                                    {{ (string) old(
                                        'loan_type_acount',
                                        $loanType->loan_type_acount
                                    ) === (string) $acc->sub_account_id
                                        ? 'selected'
                                        : '' }}
                                >
                                    {{ $acc->main_account_code }}/{{ $acc->sub_account_code }}
                                    — {{ $acc->sub_account_name }}
                                </option>
                            @endforeach
                        </select>

                        @error('loan_type_acount')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                        <small class="help-text">
                            Asset account used to recognise outstanding
                            member loan principal.
                        </small>
                    </div>

                    <div class="col-lg-4 form-group mb-3">
                        <label for="loan_type_int_account">
                            Interest income account
                            <span class="required-marker">*</span>
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

                            @foreach ($incomeAccounts as $acc)
                                <option
                                    value="{{ $acc->sub_account_id }}"
                                    {{ (string) old(
                                        'loan_type_int_account',
                                        $loanType->loan_type_int_account
                                    ) === (string) $acc->sub_account_id
                                        ? 'selected'
                                        : '' }}
                                >
                                    {{ $acc->main_account_code }}/{{ $acc->sub_account_code }}
                                    — {{ $acc->sub_account_name }}
                                </option>
                            @endforeach
                        </select>

                        @error('loan_type_int_account')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                        <small class="help-text">
                            Income account used for loan interest earned.
                        </small>
                    </div>

                    <div class="col-lg-4 form-group mb-3">
                        <label for="loan_type_comm_account">
                            Commission account
                            <span class="required-marker">*</span>
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

                            @foreach ($incomeLiabilityAccounts as $acc)
                                <option
                                    value="{{ $acc->sub_account_id }}"
                                    {{ (string) old(
                                        'loan_type_comm_account',
                                        $loanType->loan_type_comm_account
                                    ) === (string) $acc->sub_account_id
                                        ? 'selected'
                                        : '' }}
                                >
                                    {{ $acc->main_account_code }}/{{ $acc->sub_account_code }}
                                    — {{ $acc->sub_account_name }}
                                </option>
                            @endforeach
                        </select>

                        @error('loan_type_comm_account')
                            <div class="invalid-feedback">
                                {{ $message }}
                            </div>
                        @enderror

                        <small class="help-text">
                            Account used for loan commission and related
                            processing charges.
                        </small>
                    </div>

                </div>
            </div>
        </div>

        {{-- ================================================================
        | ACTIONS
        ================================================================= --}}
        <div class="sticky-actions mb-4">

            <div class="p-3 d-flex flex-column flex-md-row justify-content-between align-items-md-center">

                <div class="mb-3 mb-md-0">
                    <strong>
                        Ready to save?
                    </strong>

                    <div class="text-muted small">
                        Review automation, charges and accounting mappings
                        before updating.
                    </div>
                </div>

                <div>
                    <a
                        href="{{ route('loans.types') }}"
                        class="btn btn-light mr-2"
                    >
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="btn btn-primary"
                        id="submitLoanTypeButton"
                    >
                        Update Loan Type
                    </button>
                </div>

            </div>
        </div>

    </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('loanTypeForm');

    const loanTypeActive = document.getElementById(
        'loan_type_active'
    );

    const loanStatusBadge = document.getElementById(
        'loanStatusBadge'
    );

    const autoInterestOnPeriodChange = document.getElementById(
        'loan_type_auto_interest_on_period_change'
    );

    const autoInterestPeriodChangePanel = document.getElementById(
        'autoInterestPeriodChangePanel'
    );

    const instantQualification = document.getElementById(
        'loan_type_instant_qualification'
    );

    const instantDisbursement = document.getElementById(
        'loan_type_instant_disbursement'
    );

    const instantQualificationPanel = document.getElementById(
        'instantQualificationPanel'
    );

    const instantDisbursementPanel = document.getElementById(
        'instantDisbursementPanel'
    );

    const crbRequired = document.getElementById(
        'loan_type_crb_required'
    );

    const insurable = document.getElementById(
        'loan_type_insurable'
    );

    const commissionRequired = document.getElementById(
        'loan_type_commission_required'
    );

    const submitButton = document.getElementById(
        'submitLoanTypeButton'
    );

    /*
    |--------------------------------------------------------------------------
    | Enable or Disable Dependent Fields
    |--------------------------------------------------------------------------
    */
    function setFieldsEnabled(selector, enabled) {
        document.querySelectorAll(selector).forEach(function (wrapper) {
            wrapper.classList.toggle(
                'dependent-fields-disabled',
                !enabled
            );

            wrapper
                .querySelectorAll('input, select, textarea')
                .forEach(function (field) {
                    field.disabled = !enabled;
                });
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Product Status Badge
    |--------------------------------------------------------------------------
    */
    function updateLoanStatusBadge() {
        if (!loanTypeActive || !loanStatusBadge) {
            return;
        }

        const active = loanTypeActive.value === '1';

        loanStatusBadge.textContent = active
            ? 'Active'
            : 'Inactive';

        loanStatusBadge.classList.toggle(
            'active',
            active
        );

        loanStatusBadge.classList.toggle(
            'inactive',
            !active
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Setting Panel Highlights
    |--------------------------------------------------------------------------
    | All three settings operate independently.
    |--------------------------------------------------------------------------
    */
    function updateSettingPanels() {
        if (
            autoInterestOnPeriodChange &&
            autoInterestPeriodChangePanel
        ) {
            autoInterestPeriodChangePanel.classList.toggle(
                'active-setting',
                autoInterestOnPeriodChange.checked
            );
        }

        if (
            instantQualification &&
            instantQualificationPanel
        ) {
            instantQualificationPanel.classList.toggle(
                'active-setting',
                instantQualification.checked
            );
        }

        if (
            instantDisbursement &&
            instantDisbursementPanel
        ) {
            instantDisbursementPanel.classList.toggle(
                'active-setting',
                instantDisbursement.checked
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | CRB Fields
    |--------------------------------------------------------------------------
    */
    function updateCrbFields() {
        if (!crbRequired) {
            return;
        }

        setFieldsEnabled(
            '.crb-dependent-field',
            crbRequired.value === 'Y'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Insurance Fields
    |--------------------------------------------------------------------------
    */
    function updateInsuranceFields() {
        if (!insurable) {
            return;
        }

        setFieldsEnabled(
            '.insurance-dependent-field',
            insurable.value === 'Y'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Commission Fields
    |--------------------------------------------------------------------------
    */
    function updateCommissionFields() {
        if (!commissionRequired) {
            return;
        }

        setFieldsEnabled(
            '.commission-dependent-field',
            commissionRequired.value === 'Y'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Event Listeners
    |--------------------------------------------------------------------------
    */
    if (loanTypeActive) {
        loanTypeActive.addEventListener(
            'change',
            updateLoanStatusBadge
        );
    }

    if (autoInterestOnPeriodChange) {
        autoInterestOnPeriodChange.addEventListener(
            'change',
            updateSettingPanels
        );
    }

    if (instantQualification) {
        instantQualification.addEventListener(
            'change',
            updateSettingPanels
        );
    }

    if (instantDisbursement) {
        instantDisbursement.addEventListener(
            'change',
            updateSettingPanels
        );
    }

    if (crbRequired) {
        crbRequired.addEventListener(
            'change',
            updateCrbFields
        );
    }

    if (insurable) {
        insurable.addEventListener(
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

    /*
    |--------------------------------------------------------------------------
    | Form Submission
    |--------------------------------------------------------------------------
    */
    if (form) {
        form.addEventListener('submit', function () {
            /*
             * Re-enable conditional fields before submission so their current
             * values reach the controller for normalisation.
             */
            form
                .querySelectorAll(':disabled')
                .forEach(function (field) {
                    field.disabled = false;
                });

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Updating...';
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Initial State
    |--------------------------------------------------------------------------
    */
    updateLoanStatusBadge();
    updateSettingPanels();
    updateCrbFields();
    updateInsuranceFields();
    updateCommissionFields();
});
</script>

@endsection