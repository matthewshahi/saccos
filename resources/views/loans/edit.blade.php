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
        font-size: 1.55rem;
        font-weight: 700;
        color: var(--lt-dark);
        margin-bottom: 0.25rem;
    }

    .loan-type-page .page-subtitle {
        color: var(--lt-muted);
        margin-bottom: 0;
    }

    .loan-type-page .form-section-card {
        border: 1px solid var(--lt-border);
        border-radius: 12px;
        box-shadow: 0 4px 18px rgba(17, 24, 39, 0.04);
        overflow: hidden;
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
        background: var(--lt-primary-soft);
        color: var(--lt-primary);
        font-weight: 700;
    }

    .loan-type-page .form-section-title {
        font-size: 1rem;
        font-weight: 700;
        color: var(--lt-dark);
        margin-bottom: 0.2rem;
    }

    .loan-type-page .form-section-description {
        color: var(--lt-muted);
        font-size: 0.86rem;
        margin-bottom: 0;
    }

    .loan-type-page .form-section-body {
        padding: 1.25rem;
    }

    .loan-type-page .field-group-title {
        font-size: 0.78rem;
        font-weight: 700;
        color: var(--lt-muted);
        letter-spacing: 0.04em;
        text-transform: uppercase;
        margin-bottom: 1rem;
    }

    .loan-type-page .form-group label {
        font-weight: 600;
        color: #374151;
        margin-bottom: 0.4rem;
    }

    .loan-type-page .form-control {
        min-height: 42px;
        border-radius: 8px;
        border-color: #d1d5db;
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
        border: 1px solid var(--lt-border);
        border-radius: 10px;
        padding: 1rem;
        background: #fff;
    }

    .loan-type-page .setting-panel.active-setting {
        border-color: rgba(79, 70, 229, 0.35);
        background: var(--lt-primary-soft);
    }

    .loan-type-page .setting-panel-title {
        font-weight: 700;
        color: var(--lt-dark);
        margin-bottom: 0.3rem;
    }

    .loan-type-page .setting-panel-text {
        color: var(--lt-muted);
        font-size: 0.82rem;
        line-height: 1.45;
        margin-bottom: 0;
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

    .loan-type-page .modern-switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .loan-type-page .modern-switch-slider {
        position: absolute;
        inset: 0;
        cursor: pointer;
        background: #cbd5e1;
        border-radius: 999px;
        transition: 0.2s ease;
    }

    .loan-type-page .modern-switch-slider::before {
        content: "";
        position: absolute;
        width: 20px;
        height: 20px;
        left: 3px;
        top: 3px;
        background: #fff;
        border-radius: 50%;
        box-shadow: 0 1px 4px rgba(0, 0, 0, 0.18);
        transition: 0.2s ease;
    }

    .loan-type-page .modern-switch input:checked + .modern-switch-slider {
        background: var(--lt-primary);
    }

    .loan-type-page .modern-switch input:checked + .modern-switch-slider::before {
        transform: translateX(22px);
    }

    .loan-type-page .automation-warning {
        display: none;
        border-left: 4px solid var(--lt-warning);
        background: #fffbeb;
        color: #92400e;
        padding: 0.85rem 1rem;
        border-radius: 8px;
        margin-top: 1rem;
        font-size: 0.85rem;
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
        backdrop-filter: blur(8px);
        box-shadow: 0 -4px 16px rgba(17, 24, 39, 0.06);
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
</style>

<div class="loan-type-page">

    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <div>
            <h1 class="page-heading">Edit Loan Type</h1>
            <p class="page-subtitle">
                Configure eligibility, pricing, risk controls, automation and accounting.
            </p>
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
                        data-fullscreen="">
                    </i>
                </li>
            </ul>
        </div>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <div class="d-flex align-items-start">
                <div>
                    <strong>The loan type could not be updated.</strong>
                    <div class="mt-1">Please correct the following:</div>

                    <ul class="mb-0 mt-2">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <form
        action="{{ route('loans.types.update', $loanType->loan_type_id) }}"
        method="POST"
        id="loanTypeForm">

        @csrf
        @method('PUT')

        {{-- ================================================================
        | 1. PRODUCT IDENTITY
        ================================================================= --}}
        <div class="card form-section-card mb-4">
            <div class="form-section-header">
                <div class="d-flex align-items-start">
                    <div class="form-section-number mr-3">1</div>

                    <div>
                        <div class="form-section-title">
                            Product identity and availability
                        </div>

                        <p class="form-section-description">
                            Define the loan product name, code and whether members can currently use it.
                        </p>
                    </div>
                </div>

                @if((int) old('loan_type_active', $loanType->loan_type_active ?? 1) === 1)
                    <span class="status-badge active" id="loanStatusBadge">
                        Active
                    </span>
                @else
                    <span class="status-badge inactive" id="loanStatusBadge">
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
                            class="form-control"
                            id="loan_type_name"
                            name="loan_type_name"
                            value="{{ old('loan_type_name', $loanType->loan_type_name) }}"
                            placeholder="e.g. Emergency Loan"
                            maxlength="250"
                            required>

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
                            class="form-control"
                            id="loan_type_code"
                            name="loan_type_code"
                            value="{{ old('loan_type_code', $loanType->loan_type_code) }}"
                            placeholder="e.g. EML"
                            maxlength="100"
                            required>

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
                            class="form-control"
                            id="loan_type_active"
                            name="loan_type_active"
                            required>

                            <option
                                value="1"
                                {{ (string) old('loan_type_active', $loanType->loan_type_active ?? 1) === '1' ? 'selected' : '' }}>
                                Active
                            </option>

                            <option
                                value="0"
                                {{ (string) old('loan_type_active', $loanType->loan_type_active ?? 1) === '0' ? 'selected' : '' }}>
                                Inactive
                            </option>
                        </select>

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
                    <div class="form-section-number mr-3">2</div>

                    <div>
                        <div class="form-section-title">
                            Pricing, limits and repayment
                        </div>

                        <p class="form-section-description">
                            Configure interest, maximum amount, repayment duration and security requirements.
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
                            class="form-control"
                            id="loan_type_interest"
                            name="loan_type_interest"
                            value="{{ old('loan_type_interest', $loanType->loan_type_interest) }}"
                            placeholder="e.g. 12"
                            required>
                    </div>

                    <div class="col-lg-3 col-md-6 form-group mb-3">
                        <label for="loan_type_interest_type">
                            Interest method
                            <span class="required-marker">*</span>
                        </label>

                        <select
                            class="form-control"
                            id="loan_type_interest_type"
                            name="loan_type_interest_type"
                            required>

                            <option value="">Select interest method</option>

                            <option
                                value="REDUCING BALANCE"
                                {{ old('loan_type_interest_type', $loanType->loan_type_interest_type) === 'REDUCING BALANCE' ? 'selected' : '' }}>
                                Reducing Balance
                            </option>

                            <option
                                value="FIXED INTEREST"
                                {{ old('loan_type_interest_type', $loanType->loan_type_interest_type) === 'FIXED INTEREST' ? 'selected' : '' }}>
                                Fixed Interest
                            </option>
                        </select>
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
                                class="form-control"
                                id="loan_type_duration"
                                name="loan_type_duration"
                                value="{{ old('loan_type_duration', $loanType->loan_type_duration) }}"
                                placeholder="e.g. 12"
                                required>

                            <div class="input-group-append">
                                <span class="input-group-text">Months</span>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-3 col-md-6 form-group mb-3">
                        <label for="loan_type_max_amount">
                            Maximum principal
                            <span class="required-marker">*</span>
                        </label>

                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">KES</span>
                            </div>

                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                class="form-control"
                                id="loan_type_max_amount"
                                name="loan_type_max_amount"
                                value="{{ old('loan_type_max_amount', $loanType->loan_type_max_amount) }}"
                                placeholder="e.g. 50000"
                                required>
                        </div>
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
                            class="form-control"
                            id="loan_type_share_factor"
                            name="loan_type_share_factor"
                            value="{{ old('loan_type_share_factor', $loanType->loan_type_share_factor ?? 3) }}"
                            placeholder="e.g. 3"
                            required>

                        <small class="help-text">
                            Maximum lending multiplier applied against qualifying member shares and capital.
                        </small>
                    </div>

                    <div class="col-lg-4 form-group mb-3">
                        <label for="loan_type_guaranteable_percent">
                            Amount requiring guarantee (%)
                            <span class="required-marker">*</span>
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            max="100"
                            class="form-control"
                            id="loan_type_guaranteable_percent"
                            name="loan_type_guaranteable_percent"
                            value="{{ old('loan_type_guaranteable_percent', $loanType->loan_type_guaranteable_percent) }}"
                            placeholder="e.g. 100"
                            required>

                        <small class="help-text">
                            Use 0 where guarantors are not required.
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
                    <div class="form-section-number mr-3">3</div>

                    <div>
                        <div class="form-section-title">
                            Qualification and automation
                        </div>

                        <p class="form-section-description">
                            Define membership eligibility and whether qualification or payout may happen automatically.
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
                                class="form-control"
                                id="loan_type_qualification_period"
                                name="loan_type_qualification_period"
                                value="{{ old('loan_type_qualification_period', $loanType->loan_type_qualification_period) }}"
                                placeholder="e.g. 6"
                                required>

                            <div class="input-group-append">
                                <span class="input-group-text">Months</span>
                            </div>
                        </div>

                        <small class="help-text">
                            Minimum number of months a member must have belonged to the SACCO.
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
                                class="form-control"
                                id="loan_type_max_qualification_period"
                                name="loan_type_max_qualification_period"
                                value="{{ old('loan_type_max_qualification_period', $loanType->loan_type_max_qualification_period) }}"
                                placeholder="No upper limit">

                            <div class="input-group-append">
                                <span class="input-group-text">Months</span>
                            </div>
                        </div>

                        <small class="help-text">
                            Optional. Leave blank when there is no maximum membership period.
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
                            id="instantQualificationPanel">

                            <div class="modern-switch-row">
                                <div class="pr-3">
                                    <div class="setting-panel-title">
                                        Instant qualification
                                    </div>

                                    <p class="setting-panel-text">
                                        Allow the eligibility engine to evaluate and qualify the member immediately,
                                        without waiting for manual qualification review.
                                    </p>
                                </div>

                                <label
                                    class="modern-switch"
                                    for="loan_type_instant_qualification">

                                    <input
                                        type="hidden"
                                        name="loan_type_instant_qualification"
                                        value="0">

                                    <input
                                        type="checkbox"
                                        id="loan_type_instant_qualification"
                                        name="loan_type_instant_qualification"
                                        value="1"
                                        {{ (int) old(
                                            'loan_type_instant_qualification',
                                            $loanType->loan_type_instant_qualification ?? 0
                                        ) === 1 ? 'checked' : '' }}>

                                    <span class="modern-switch-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6 mb-3">
                        <div
                            class="setting-panel"
                            id="instantDisbursementPanel">

                            <div class="modern-switch-row">
                                <div class="pr-3">
                                    <div class="setting-panel-title">
                                        Instant disbursement
                                    </div>

                                    <p class="setting-panel-text">
                                        After successful automated qualification and all mandatory checks,
                                        allow the system to initiate immediate payment through an approved
                                        bank or mobile-money channel.
                                    </p>
                                </div>

                                <label
                                    class="modern-switch"
                                    for="loan_type_instant_disbursement">

                                    <input
                                        type="hidden"
                                        name="loan_type_instant_disbursement"
                                        value="0">

                                    <input
                                        type="checkbox"
                                        id="loan_type_instant_disbursement"
                                        name="loan_type_instant_disbursement"
                                        value="1"
                                        {{ (int) old(
                                            'loan_type_instant_disbursement',
                                            $loanType->loan_type_instant_disbursement ?? 0
                                        ) === 1 ? 'checked' : '' }}>

                                    <span class="modern-switch-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div
                    class="automation-warning"
                    id="instantDisbursementWarning">

                    <strong>Important:</strong>
                    Instant disbursement depends on instant qualification. Enabling instant
                    disbursement will also enable instant qualification. Server-side validation
                    should enforce the same rule.
                </div>
            </div>
        </div>

        {{-- ================================================================
        | 4. RISK, INSURANCE AND CHARGES
        ================================================================= --}}
        <div class="card form-section-card mb-4">
            <div class="form-section-header">
                <div class="d-flex align-items-start">
                    <div class="form-section-number mr-3">4</div>

                    <div>
                        <div class="form-section-title">
                            Risk checks, insurance and charges
                        </div>

                        <p class="form-section-description">
                            Configure CRB requirements, insurance treatment and loan-processing charges.
                        </p>
                    </div>
                </div>
            </div>

            <div class="form-section-body">

                {{-- CRB --}}
                <div class="field-group-title">
                    Credit reference bureau
                </div>

                <div class="row" id="crbSettingsGroup">
                    <div class="col-lg-3 form-group mb-3">
                        <label for="loan_type_crb_required">
                            CRB check required
                            <span class="required-marker">*</span>
                        </label>

                        <select
                            class="form-control"
                            id="loan_type_crb_required"
                            name="loan_type_crb_required"
                            required>

                            <option
                                value="N"
                                {{ old('loan_type_crb_required', $loanType->loan_type_crb_required ?? 'N') === 'N' ? 'selected' : '' }}>
                                No
                            </option>

                            <option
                                value="Y"
                                {{ old('loan_type_crb_required', $loanType->loan_type_crb_required ?? 'N') === 'Y' ? 'selected' : '' }}>
                                Yes
                            </option>
                        </select>
                    </div>

                    <div
                        class="col-lg-3 form-group mb-3 crb-dependent-field">

                        <label for="loan_type_crb_charge">
                            CRB charge
                        </label>

                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text">KES</span>
                            </div>

                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                class="form-control"
                                id="loan_type_crb_charge"
                                name="loan_type_crb_charge"
                                value="{{ old('loan_type_crb_charge', $loanType->loan_type_crb_charge ?? 0) }}"
                                placeholder="e.g. 100">
                        </div>
                    </div>

                    <div
                        class="col-lg-6 form-group mb-3 crb-dependent-field">

                        <label for="loan_type_crb_effect">
                            CRB charge treatment
                        </label>

                        <select
                            class="form-control"
                            id="loan_type_crb_effect"
                            name="loan_type_crb_effect">

                            <option value="">Select treatment</option>

                            <option
                                value="ADD_TO_LOAN"
                                {{ old('loan_type_crb_effect', $loanType->loan_type_crb_effect ?? '') === 'ADD_TO_LOAN' ? 'selected' : '' }}>
                                Add to loan balance
                            </option>

                            <option
                                value="DEDUCT_FROM_DISBURSEMENT"
                                {{ old('loan_type_crb_effect', $loanType->loan_type_crb_effect ?? '') === 'DEDUCT_FROM_DISBURSEMENT' ? 'selected' : '' }}>
                                Deduct from amount disbursed
                            </option>
                        </select>
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
                            class="form-control"
                            id="loan_type_insurable"
                            name="loan_type_insurable"
                            required>

                            <option
                                value="Y"
                                {{ old('loan_type_insurable', $loanType->loan_type_insurable) === 'Y' ? 'selected' : '' }}>
                                Yes
                            </option>

                            <option
                                value="N"
                                {{ old('loan_type_insurable', $loanType->loan_type_insurable) === 'N' ? 'selected' : '' }}>
                                No
                            </option>
                        </select>
                    </div>

                    <div
                        class="col-lg-6 form-group mb-3 insurance-dependent-field">

                        <label for="loan_type_insurance_effect">
                            Insurance treatment
                        </label>

                        <select
                            class="form-control"
                            id="loan_type_insurance_effect"
                            name="loan_type_insurance_effect">

                            <option value="">Select treatment</option>

                            <option
                                value="ADD_TO_LOAN"
                                {{ old(
                                    'loan_type_insurance_effect',
                                    $loanType->loan_type_insurance_effect ?? ''
                                ) === 'ADD_TO_LOAN' ? 'selected' : '' }}>
                                Add insurance to loan balance
                            </option>

                            <option
                                value="DEDUCT_FROM_DISBURSEMENT"
                                {{ old(
                                    'loan_type_insurance_effect',
                                    $loanType->loan_type_insurance_effect ?? ''
                                ) === 'DEDUCT_FROM_DISBURSEMENT' ? 'selected' : '' }}>
                                Deduct insurance from amount disbursed
                            </option>
                        </select>

                        <small class="help-text">
                            Determines whether insurance increases the member's loan balance
                            or reduces the net cash received.
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
                            class="form-control"
                            id="loan_type_commission_required"
                            name="loan_type_commission_required"
                            required>

                            <option
                                value="N"
                                {{ old(
                                    'loan_type_commission_required',
                                    $loanType->loan_type_commission_required ?? 'N'
                                ) === 'N' ? 'selected' : '' }}>
                                No
                            </option>

                            <option
                                value="Y"
                                {{ old(
                                    'loan_type_commission_required',
                                    $loanType->loan_type_commission_required ?? 'N'
                                ) === 'Y' ? 'selected' : '' }}>
                                Yes
                            </option>
                        </select>
                    </div>

                    <div
                        class="col-lg-3 form-group mb-3 commission-dependent-field">

                        <label for="loan_type_commission_type">
                            Commission type
                        </label>

                        <select
                            class="form-control"
                            id="loan_type_commission_type"
                            name="loan_type_commission_type">

                            <option value="">Select type</option>

                            <option
                                value="FIXED"
                                {{ old(
                                    'loan_type_commission_type',
                                    $loanType->loan_type_commission_type ?? ''
                                ) === 'FIXED' ? 'selected' : '' }}>
                                Fixed amount
                            </option>

                            <option
                                value="PERCENT"
                                {{ old(
                                    'loan_type_commission_type',
                                    $loanType->loan_type_commission_type ?? ''
                                ) === 'PERCENT' ? 'selected' : '' }}>
                                Percentage
                            </option>
                        </select>
                    </div>

                    <div
                        class="col-lg-3 form-group mb-3 commission-dependent-field">

                        <label for="loan_type_commission_value">
                            Commission value
                        </label>

                        <input
                            type="number"
                            step="0.01"
                            min="0"
                            class="form-control"
                            id="loan_type_commission_value"
                            name="loan_type_commission_value"
                            value="{{ old(
                                'loan_type_commission_value',
                                $loanType->loan_type_commission_value ?? 0
                            ) }}"
                            placeholder="e.g. 500 or 2.5">
                    </div>

                    <div
                        class="col-lg-3 form-group mb-3 commission-dependent-field">

                        <label for="loan_type_commission_effect">
                            Commission treatment
                        </label>

                        <select
                            class="form-control"
                            id="loan_type_commission_effect"
                            name="loan_type_commission_effect">

                            <option value="">Select treatment</option>

                            <option
                                value="ADD_TO_LOAN"
                                {{ old(
                                    'loan_type_commission_effect',
                                    $loanType->loan_type_commission_effect ?? ''
                                ) === 'ADD_TO_LOAN' ? 'selected' : '' }}>
                                Add to loan balance
                            </option>

                            <option
                                value="DEDUCT_FROM_DISBURSEMENT"
                                {{ old(
                                    'loan_type_commission_effect',
                                    $loanType->loan_type_commission_effect ?? ''
                                ) === 'DEDUCT_FROM_DISBURSEMENT' ? 'selected' : '' }}>
                                Deduct from amount disbursed
                            </option>
                        </select>
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
                    <div class="form-section-number mr-3">5</div>

                    <div>
                        <div class="form-section-title">
                            Accounting setup
                        </div>

                        <p class="form-section-description">
                            Map principal, interest and commission entries to the correct general-ledger accounts.
                        </p>
                    </div>
                </div>
            </div>

            <div class="form-section-body">
                <div class="alert alert-light border mb-4">
                    These accounts determine how approved loans, interest and charges are
                    posted into the SACCO ledger. Confirm them carefully before saving.
                </div>

                <div class="row">
                    <div class="col-lg-4 form-group mb-3">
                        <label for="loan_type_acount">
                            Loan principal account
                            <span class="required-marker">*</span>
                        </label>

                        <select
                            class="form-control"
                            id="loan_type_acount"
                            name="loan_type_acount"
                            required>

                            <option value="">Select asset account</option>

                            @foreach($assetAccounts as $acc)
                                <option
                                    value="{{ $acc->sub_account_id }}"
                                    {{ (string) old(
                                        'loan_type_acount',
                                        $loanType->loan_type_acount
                                    ) === (string) $acc->sub_account_id ? 'selected' : '' }}>

                                    {{ $acc->main_account_code }}/{{ $acc->sub_account_code }}
                                    — {{ $acc->sub_account_name }}
                                </option>
                            @endforeach
                        </select>

                        <small class="help-text">
                            Asset account used to recognise outstanding member loan principal.
                        </small>
                    </div>

                    <div class="col-lg-4 form-group mb-3">
                        <label for="loan_type_int_account">
                            Interest income account
                            <span class="required-marker">*</span>
                        </label>

                        <select
                            class="form-control"
                            id="loan_type_int_account"
                            name="loan_type_int_account"
                            required>

                            <option value="">Select income account</option>

                            @foreach($incomeAccounts as $acc)
                                <option
                                    value="{{ $acc->sub_account_id }}"
                                    {{ (string) old(
                                        'loan_type_int_account',
                                        $loanType->loan_type_int_account
                                    ) === (string) $acc->sub_account_id ? 'selected' : '' }}>

                                    {{ $acc->main_account_code }}/{{ $acc->sub_account_code }}
                                    — {{ $acc->sub_account_name }}
                                </option>
                            @endforeach
                        </select>

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
                            class="form-control"
                            id="loan_type_comm_account"
                            name="loan_type_comm_account"
                            required>

                            <option value="">
                                Select income or liability account
                            </option>

                            @foreach($incomeLiabilityAccounts as $acc)
                                <option
                                    value="{{ $acc->sub_account_id }}"
                                    {{ (string) old(
                                        'loan_type_comm_account',
                                        $loanType->loan_type_comm_account
                                    ) === (string) $acc->sub_account_id ? 'selected' : '' }}>

                                    {{ $acc->main_account_code }}/{{ $acc->sub_account_code }}
                                    — {{ $acc->sub_account_name }}
                                </option>
                            @endforeach
                        </select>

                        <small class="help-text">
                            Account used for loan commission and related processing charges.
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
                    <strong>Ready to save?</strong>

                    <div class="text-muted small">
                        Review automation, charges and accounting mappings before updating.
                    </div>
                </div>

                <div>
                    <a
                        href="{{ route('loans.types') }}"
                        class="btn btn-light mr-2">
                        Cancel
                    </a>

                    <button
                        type="submit"
                        class="btn btn-primary"
                        id="submitLoanTypeButton">

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

    const loanTypeActive = document.getElementById('loan_type_active');
    const loanStatusBadge = document.getElementById('loanStatusBadge');

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

    const instantDisbursementWarning = document.getElementById(
        'instantDisbursementWarning'
    );

    const crbRequired = document.getElementById('loan_type_crb_required');
    const insurable = document.getElementById('loan_type_insurable');

    const commissionRequired = document.getElementById(
        'loan_type_commission_required'
    );

    const submitButton = document.getElementById('submitLoanTypeButton');

    function setFieldsEnabled(selector, enabled) {
        document.querySelectorAll(selector).forEach(function (wrapper) {
            wrapper.classList.toggle(
                'dependent-fields-disabled',
                !enabled
            );

            wrapper.querySelectorAll('input, select, textarea')
                .forEach(function (field) {
                    field.disabled = !enabled;
                });
        });
    }

    function updateLoanStatusBadge() {
        if (!loanTypeActive || !loanStatusBadge) {
            return;
        }

        const isActive = loanTypeActive.value === '1';

        loanStatusBadge.textContent = isActive
            ? 'Active'
            : 'Inactive';

        loanStatusBadge.classList.toggle('active', isActive);
        loanStatusBadge.classList.toggle('inactive', !isActive);
    }

    function updateAutomationPanels() {
        if (
            !instantQualification ||
            !instantDisbursement
        ) {
            return;
        }

        instantQualificationPanel.classList.toggle(
            'active-setting',
            instantQualification.checked
        );

        instantDisbursementPanel.classList.toggle(
            'active-setting',
            instantDisbursement.checked
        );

        instantDisbursementWarning.style.display =
            instantDisbursement.checked
                ? 'block'
                : 'none';
    }

    function updateCrbFields() {
        if (!crbRequired) {
            return;
        }

        setFieldsEnabled(
            '.crb-dependent-field',
            crbRequired.value === 'Y'
        );
    }

    function updateInsuranceFields() {
        if (!insurable) {
            return;
        }

        setFieldsEnabled(
            '.insurance-dependent-field',
            insurable.value === 'Y'
        );
    }

    function updateCommissionFields() {
        if (!commissionRequired) {
            return;
        }

        setFieldsEnabled(
            '.commission-dependent-field',
            commissionRequired.value === 'Y'
        );
    }

    if (loanTypeActive) {
        loanTypeActive.addEventListener(
            'change',
            updateLoanStatusBadge
        );
    }

    if (instantQualification) {
        instantQualification.addEventListener('change', function () {
            /*
             * Instant disbursement cannot remain enabled without
             * instant qualification.
             */
            if (
                !instantQualification.checked &&
                instantDisbursement.checked
            ) {
                instantDisbursement.checked = false;
            }

            updateAutomationPanels();
        });
    }

    if (instantDisbursement) {
        instantDisbursement.addEventListener('change', function () {
            /*
             * Enabling instant disbursement automatically enables
             * instant qualification.
             */
            if (instantDisbursement.checked) {
                instantQualification.checked = true;
            }

            updateAutomationPanels();
        });
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

    if (form) {
        form.addEventListener('submit', function () {
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Updating...';
            }
        });
    }

    updateLoanStatusBadge();
    updateAutomationPanels();
    updateCrbFields();
    updateInsuranceFields();
    updateCommissionFields();
});
</script>

@endsection