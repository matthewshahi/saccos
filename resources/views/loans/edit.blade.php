@extends('layouts.app')

@section('content')

<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Edit Loan Type</h1>

    <div class="header-part-right">
        <ul>
            @if (Auth::check())
                <li>{{ Auth::user()->member_name }}</li>
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

@if (session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger">
        {{ session('error') }}
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>The loan type could not be updated.</strong>

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

    <div class="row">

        {{-- ================================================================
        | 1. PRODUCT DETAILS
        ================================================================= --}}
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">
                        1. Product Details
                    </div>

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label for="loan_type_name">
                                Loan Type Name
                                <span class="text-danger">*</span>
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
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_code">
                                Loan Type Code
                                <span class="text-danger">*</span>
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
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_active">
                                Status
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                class="form-control @error('loan_type_active') is-invalid @enderror"
                                id="loan_type_active"
                                name="loan_type_active"
                                required
                            >
                                <option
                                    value="1"
                                    {{ (string) old('loan_type_active', $loanType->loan_type_active ?? 1) === '1' ? 'selected' : '' }}
                                >
                                    Active
                                </option>

                                <option
                                    value="0"
                                    {{ (string) old('loan_type_active', $loanType->loan_type_active ?? 1) === '0' ? 'selected' : '' }}
                                >
                                    Inactive
                                </option>
                            </select>

                            @error('loan_type_active')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            <small class="form-text text-muted">
                                Inactive products should not accept new applications.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ================================================================
        | 2. PRICING AND REPAYMENT
        ================================================================= --}}
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">
                        2. Pricing and Repayment
                    </div>

                    <div class="row">
                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_interest">
                                Interest Rate (%)
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                class="form-control @error('loan_type_interest') is-invalid @enderror"
                                id="loan_type_interest"
                                name="loan_type_interest"
                                value="{{ old('loan_type_interest', $loanType->loan_type_interest) }}"
                                placeholder="e.g. 12"
                                required
                            >

                            @error('loan_type_interest')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_interest_type">
                                Interest Type
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                class="form-control @error('loan_type_interest_type') is-invalid @enderror"
                                id="loan_type_interest_type"
                                name="loan_type_interest_type"
                                required
                            >
                                <option value="">Select interest type</option>

                                <option
                                    value="REDUCING BALANCE"
                                    {{ old('loan_type_interest_type', $loanType->loan_type_interest_type) === 'REDUCING BALANCE' ? 'selected' : '' }}
                                >
                                    Reducing Balance
                                </option>

                                <option
                                    value="FIXED INTEREST"
                                    {{ old('loan_type_interest_type', $loanType->loan_type_interest_type) === 'FIXED INTEREST' ? 'selected' : '' }}
                                >
                                    Fixed Interest
                                </option>
                            </select>

                            @error('loan_type_interest_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_duration">
                                Maximum Duration
                                <span class="text-danger">*</span>
                            </label>

                            <div class="input-group">
                                <input
                                    type="number"
                                    min="1"
                                    class="form-control @error('loan_type_duration') is-invalid @enderror"
                                    id="loan_type_duration"
                                    name="loan_type_duration"
                                    value="{{ old('loan_type_duration', $loanType->loan_type_duration) }}"
                                    placeholder="e.g. 12"
                                    required
                                >

                                <div class="input-group-append">
                                    <span class="input-group-text">Months</span>
                                </div>

                                @error('loan_type_duration')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_max_amount">
                                Maximum Amount
                                <span class="text-danger">*</span>
                            </label>

                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">KES</span>
                                </div>

                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="form-control @error('loan_type_max_amount') is-invalid @enderror"
                                    id="loan_type_max_amount"
                                    name="loan_type_max_amount"
                                    value="{{ old('loan_type_max_amount', $loanType->loan_type_max_amount) }}"
                                    placeholder="e.g. 50000"
                                    required
                                >

                                @error('loan_type_max_amount')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label for="loan_type_auto_interest_on_period_change">
                                Apply Interest Automatically on Period Change
                                <span class="text-danger">*</span>
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
                                    No
                                </option>

                                <option
                                    value="1"
                                    {{ (string) old(
                                        'loan_type_auto_interest_on_period_change',
                                        $loanType->loan_type_auto_interest_on_period_change ?? 0
                                    ) === '1' ? 'selected' : '' }}
                                >
                                    Yes
                                </option>
                            </select>

                            @error('loan_type_auto_interest_on_period_change')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            <small class="form-text text-muted">
                                Enables this product for the configured monthly interest process.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ================================================================
        | 3. QUALIFICATION AND AUTOMATION
        ================================================================= --}}
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">
                        3. Qualification and Automation
                    </div>

                    <div class="row">
                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_share_factor">
                                Share Factor
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="number"
                                step="1"
                                min="0"
                                class="form-control @error('loan_type_share_factor') is-invalid @enderror"
                                id="loan_type_share_factor"
                                name="loan_type_share_factor"
                                value="{{ old('loan_type_share_factor', $loanType->loan_type_share_factor ?? 3) }}"
                                placeholder="e.g. 3"
                                required
                            >

                            @error('loan_type_share_factor')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            <small class="form-text text-muted">
                                Lending multiplier applied against qualifying shares and capital.
                            </small>
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_guaranteable_percent">
                                Guarantee Required (%)
                                <span class="text-danger">*</span>
                            </label>

                            <input
                                type="number"
                                step="1"
                                min="0"
                                max="100"
                                class="form-control @error('loan_type_guaranteable_percent') is-invalid @enderror"
                                id="loan_type_guaranteable_percent"
                                name="loan_type_guaranteable_percent"
                                value="{{ old('loan_type_guaranteable_percent', $loanType->loan_type_guaranteable_percent) }}"
                                placeholder="e.g. 100"
                                required
                            >

                            @error('loan_type_guaranteable_percent')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            <small class="form-text text-muted">
                                Use zero when guarantors are not required.
                            </small>
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_qualification_period">
                                Minimum Membership
                                <span class="text-danger">*</span>
                            </label>

                            <div class="input-group">
                                <input
                                    type="number"
                                    min="0"
                                    class="form-control @error('loan_type_qualification_period') is-invalid @enderror"
                                    id="loan_type_qualification_period"
                                    name="loan_type_qualification_period"
                                    value="{{ old('loan_type_qualification_period', $loanType->loan_type_qualification_period) }}"
                                    placeholder="e.g. 6"
                                    required
                                >

                                <div class="input-group-append">
                                    <span class="input-group-text">Months</span>
                                </div>

                                @error('loan_type_qualification_period')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_max_qualification_period">
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
                                    placeholder="Leave blank"
                                >

                                <div class="input-group-append">
                                    <span class="input-group-text">Months</span>
                                </div>

                                @error('loan_type_max_qualification_period')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <small class="form-text text-muted">
                                Leave blank where there is no maximum.
                            </small>
                        </div>

                        <div class="col-md-4 form-group mb-3">
                            <label for="loan_type_instant_qualification">
                                Instant Qualification
                                <span class="text-danger">*</span>
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
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            <small class="form-text text-muted">
                                Bypasses only the deposits/shares factor rule. Other conditions still apply.
                            </small>
                        </div>

                        <div class="col-md-4 form-group mb-3">
                            <label for="loan_type_auto_approval">
                                Automatic Approval
                                <span class="text-danger">*</span>
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
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            <small class="form-text text-muted">
                                Eligible non-top-up applications submitted within 30 days may be approved automatically.
                            </small>
                        </div>

                        <div class="col-md-4 form-group mb-3">
                            <label for="loan_type_instant_disbursement">
                                Instant Disbursement
                                <span class="text-danger">*</span>
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
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            <small class="form-text text-muted">
                                Starts payout only after the loan has been approved and created.
                            </small>
                        </div>

                        <div class="col-md-12">
                            <div class="alert alert-info mb-0">
                                <strong>Important:</strong>
                                Instant qualification, automatic approval and instant disbursement are independent settings.
                                Top-up applications always require manual approval.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ================================================================
        | 4. RISK, INSURANCE AND CHARGES
        ================================================================= --}}
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">
                        4. Risk, Insurance and Charges
                    </div>

                    <div class="row">
                        <div class="col-md-12">
                            <h5 class="mb-3">CRB Configuration</h5>
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_crb_required">
                                CRB Check Required
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                class="form-control @error('loan_type_crb_required') is-invalid @enderror"
                                id="loan_type_crb_required"
                                name="loan_type_crb_required"
                                required
                            >
                                <option
                                    value="N"
                                    {{ old('loan_type_crb_required', $loanType->loan_type_crb_required ?? 'N') === 'N' ? 'selected' : '' }}
                                >
                                    No
                                </option>

                                <option
                                    value="Y"
                                    {{ old('loan_type_crb_required', $loanType->loan_type_crb_required ?? 'N') === 'Y' ? 'selected' : '' }}
                                >
                                    Yes
                                </option>
                            </select>

                            @error('loan_type_crb_required')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3 form-group mb-3 crb-field">
                            <label for="loan_type_crb_charge">
                                CRB Charge
                            </label>

                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <span class="input-group-text">KES</span>
                                </div>

                                <input
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    class="form-control @error('loan_type_crb_charge') is-invalid @enderror"
                                    id="loan_type_crb_charge"
                                    name="loan_type_crb_charge"
                                    value="{{ old('loan_type_crb_charge', $loanType->loan_type_crb_charge ?? 0) }}"
                                    placeholder="e.g. 100"
                                >

                                @error('loan_type_crb_charge')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <div class="col-md-6 form-group mb-3 crb-field">
                            <label for="loan_type_crb_effect">
                                CRB Charge Treatment
                            </label>

                            <select
                                class="form-control @error('loan_type_crb_effect') is-invalid @enderror"
                                id="loan_type_crb_effect"
                                name="loan_type_crb_effect"
                            >
                                <option value="">Select treatment</option>

                                <option
                                    value="ADD_TO_LOAN"
                                    {{ old('loan_type_crb_effect', $loanType->loan_type_crb_effect ?? '') === 'ADD_TO_LOAN' ? 'selected' : '' }}
                                >
                                    Add to Loan Balance
                                </option>

                                <option
                                    value="DEDUCT_FROM_DISBURSEMENT"
                                    {{ old('loan_type_crb_effect', $loanType->loan_type_crb_effect ?? '') === 'DEDUCT_FROM_DISBURSEMENT' ? 'selected' : '' }}
                                >
                                    Deduct from Amount Disbursed
                                </option>
                            </select>

                            @error('loan_type_crb_effect')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-12">
                            <hr>
                            <h5 class="mb-3">Insurance Configuration</h5>
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_insurable">
                                Insurance Required
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                class="form-control @error('loan_type_insurable') is-invalid @enderror"
                                id="loan_type_insurable"
                                name="loan_type_insurable"
                                required
                            >
                                <option
                                    value="N"
                                    {{ old('loan_type_insurable', $loanType->loan_type_insurable ?? 'Y') === 'N' ? 'selected' : '' }}
                                >
                                    No
                                </option>

                                <option
                                    value="Y"
                                    {{ old('loan_type_insurable', $loanType->loan_type_insurable ?? 'Y') === 'Y' ? 'selected' : '' }}
                                >
                                    Yes
                                </option>
                            </select>

                            @error('loan_type_insurable')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-9 form-group mb-3 insurance-field">
                            <label for="loan_type_insurance_effect">
                                Insurance Treatment
                            </label>

                            <select
                                class="form-control @error('loan_type_insurance_effect') is-invalid @enderror"
                                id="loan_type_insurance_effect"
                                name="loan_type_insurance_effect"
                            >
                                <option value="">Select treatment</option>

                                <option
                                    value="ADD_TO_LOAN"
                                    {{ old('loan_type_insurance_effect', $loanType->loan_type_insurance_effect ?? '') === 'ADD_TO_LOAN' ? 'selected' : '' }}
                                >
                                    Add to Loan Balance
                                </option>

                                <option
                                    value="DEDUCT_FROM_DISBURSEMENT"
                                    {{ old('loan_type_insurance_effect', $loanType->loan_type_insurance_effect ?? '') === 'DEDUCT_FROM_DISBURSEMENT' ? 'selected' : '' }}
                                >
                                    Deduct from Amount Disbursed
                                </option>
                            </select>

                            @error('loan_type_insurance_effect')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-12">
                            <hr>
                            <h5 class="mb-3">Commission Configuration</h5>
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_commission_required">
                                Commission Required
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                class="form-control @error('loan_type_commission_required') is-invalid @enderror"
                                id="loan_type_commission_required"
                                name="loan_type_commission_required"
                                required
                            >
                                <option
                                    value="N"
                                    {{ old('loan_type_commission_required', $loanType->loan_type_commission_required ?? 'N') === 'N' ? 'selected' : '' }}
                                >
                                    No
                                </option>

                                <option
                                    value="Y"
                                    {{ old('loan_type_commission_required', $loanType->loan_type_commission_required ?? 'N') === 'Y' ? 'selected' : '' }}
                                >
                                    Yes
                                </option>
                            </select>

                            @error('loan_type_commission_required')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3 form-group mb-3 commission-field">
                            <label for="loan_type_commission_type">
                                Commission Type
                            </label>

                            <select
                                class="form-control @error('loan_type_commission_type') is-invalid @enderror"
                                id="loan_type_commission_type"
                                name="loan_type_commission_type"
                            >
                                <option value="">Select commission type</option>

                                <option
                                    value="FIXED"
                                    {{ old('loan_type_commission_type', $loanType->loan_type_commission_type ?? '') === 'FIXED' ? 'selected' : '' }}
                                >
                                    Fixed Amount
                                </option>

                                <option
                                    value="PERCENT"
                                    {{ old('loan_type_commission_type', $loanType->loan_type_commission_type ?? '') === 'PERCENT' ? 'selected' : '' }}
                                >
                                    Percentage
                                </option>
                            </select>

                            @error('loan_type_commission_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3 form-group mb-3 commission-field">
                            <label for="loan_type_commission_value">
                                Commission Value
                            </label>

                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                class="form-control @error('loan_type_commission_value') is-invalid @enderror"
                                id="loan_type_commission_value"
                                name="loan_type_commission_value"
                                value="{{ old('loan_type_commission_value', $loanType->loan_type_commission_value ?? 0) }}"
                                placeholder="e.g. 500 or 2.5"
                            >

                            @error('loan_type_commission_value')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-3 form-group mb-3 commission-field">
                            <label for="loan_type_commission_effect">
                                Commission Treatment
                            </label>

                            <select
                                class="form-control @error('loan_type_commission_effect') is-invalid @enderror"
                                id="loan_type_commission_effect"
                                name="loan_type_commission_effect"
                            >
                                <option value="">Select treatment</option>

                                <option
                                    value="ADD_TO_LOAN"
                                    {{ old('loan_type_commission_effect', $loanType->loan_type_commission_effect ?? '') === 'ADD_TO_LOAN' ? 'selected' : '' }}
                                >
                                    Add to Loan Balance
                                </option>

                                <option
                                    value="DEDUCT_FROM_DISBURSEMENT"
                                    {{ old('loan_type_commission_effect', $loanType->loan_type_commission_effect ?? '') === 'DEDUCT_FROM_DISBURSEMENT' ? 'selected' : '' }}
                                >
                                    Deduct from Amount Disbursed
                                </option>
                            </select>

                            @error('loan_type_commission_effect')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ================================================================
        | 5. ACCOUNTING SETUP
        ================================================================= --}}
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">
                        5. Accounting Setup
                    </div>

                    <div class="row">
                        <div class="col-md-4 form-group mb-3">
                            <label for="loan_type_acount">
                                Loan Principal Account
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                class="form-control @error('loan_type_acount') is-invalid @enderror"
                                id="loan_type_acount"
                                name="loan_type_acount"
                                required
                            >
                                <option value="">Select asset account</option>

                                @foreach ($assetAccounts as $account)
                                    <option
                                        value="{{ $account->sub_account_id }}"
                                        {{ (string) old(
                                            'loan_type_acount',
                                            $loanType->loan_type_acount
                                        ) === (string) $account->sub_account_id ? 'selected' : '' }}
                                    >
                                        {{ $account->sub_account_name }}
                                        ({{ $account->main_account_code }}/{{ $account->sub_account_code }})
                                    </option>
                                @endforeach
                            </select>

                            @error('loan_type_acount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            <small class="form-text text-muted">
                                Only asset accounts are listed.
                            </small>
                        </div>

                        <div class="col-md-4 form-group mb-3">
                            <label for="loan_type_int_account">
                                Interest Account
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                class="form-control @error('loan_type_int_account') is-invalid @enderror"
                                id="loan_type_int_account"
                                name="loan_type_int_account"
                                required
                            >
                                <option value="">Select income account</option>

                                @foreach ($incomeAccounts as $account)
                                    <option
                                        value="{{ $account->sub_account_id }}"
                                        {{ (string) old(
                                            'loan_type_int_account',
                                            $loanType->loan_type_int_account
                                        ) === (string) $account->sub_account_id ? 'selected' : '' }}
                                    >
                                        {{ $account->sub_account_name }}
                                        ({{ $account->main_account_code }}/{{ $account->sub_account_code }})
                                    </option>
                                @endforeach
                            </select>

                            @error('loan_type_int_account')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            <small class="form-text text-muted">
                                Only income accounts are listed.
                            </small>
                        </div>

                        <div class="col-md-4 form-group mb-3">
                            <label for="loan_type_comm_account">
                                Commission Account
                                <span class="text-danger">*</span>
                            </label>

                            <select
                                class="form-control @error('loan_type_comm_account') is-invalid @enderror"
                                id="loan_type_comm_account"
                                name="loan_type_comm_account"
                                required
                            >
                                <option value="">Select income or liability account</option>

                                @foreach ($incomeLiabilityAccounts as $account)
                                    <option
                                        value="{{ $account->sub_account_id }}"
                                        {{ (string) old(
                                            'loan_type_comm_account',
                                            $loanType->loan_type_comm_account
                                        ) === (string) $account->sub_account_id ? 'selected' : '' }}
                                    >
                                        {{ $account->sub_account_name }}
                                        ({{ $account->main_account_code }}/{{ $account->sub_account_code }})
                                    </option>
                                @endforeach
                            </select>

                            @error('loan_type_comm_account')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror

                            <small class="form-text text-muted">
                                Income and liability accounts are listed.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ================================================================
        | ACTIONS
        ================================================================= --}}
        <div class="col-md-12 mb-4">
            <button
                type="submit"
                class="btn btn-primary"
                id="submitLoanTypeButton"
            >
                Update Loan Type
            </button>

            <a
                href="{{ route('loans.types') }}"
                class="btn btn-light ml-2"
            >
                Cancel
            </a>
        </div>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const crbRequired = document.getElementById('loan_type_crb_required');
    const crbCharge = document.getElementById('loan_type_crb_charge');
    const crbEffect = document.getElementById('loan_type_crb_effect');

    const insuranceRequired = document.getElementById('loan_type_insurable');
    const insuranceEffect = document.getElementById('loan_type_insurance_effect');

    const commissionRequired = document.getElementById('loan_type_commission_required');
    const commissionType = document.getElementById('loan_type_commission_type');
    const commissionValue = document.getElementById('loan_type_commission_value');
    const commissionEffect = document.getElementById('loan_type_commission_effect');

    const form = document.getElementById('loanTypeForm');
    const submitButton = document.getElementById('submitLoanTypeButton');

    function setGroupState(selector, enabled) {
        document.querySelectorAll(selector).forEach(function (container) {
            container.classList.toggle('text-muted', !enabled);

            container.querySelectorAll('input, select, textarea').forEach(function (field) {
                field.disabled = !enabled;
            });
        });
    }

    function updateCrbFields() {
        const enabled = crbRequired && crbRequired.value === 'Y';

        setGroupState('.crb-field', enabled);

        if (crbEffect) {
            crbEffect.required =
                enabled
                && crbCharge
                && parseFloat(crbCharge.value || '0') > 0;
        }
    }

    function updateInsuranceFields() {
        const enabled =
            insuranceRequired
            && insuranceRequired.value === 'Y';

        setGroupState('.insurance-field', enabled);

        if (insuranceEffect) {
            insuranceEffect.required = enabled;
        }
    }

    function updateCommissionFields() {
        const enabled =
            commissionRequired
            && commissionRequired.value === 'Y';

        setGroupState('.commission-field', enabled);

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

    if (crbRequired) {
        crbRequired.addEventListener('change', updateCrbFields);
    }

    if (crbCharge) {
        crbCharge.addEventListener('input', updateCrbFields);
    }

    if (insuranceRequired) {
        insuranceRequired.addEventListener('change', updateInsuranceFields);
    }

    if (commissionRequired) {
        commissionRequired.addEventListener('change', updateCommissionFields);
    }

    if (form) {
        form.addEventListener('submit', function () {
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = 'Updating...';
            }
        });
    }

    updateCrbFields();
    updateInsuranceFields();
    updateCommissionFields();
});
</script>

@endsection
