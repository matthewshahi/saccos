@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Add New Loan Type</h1>
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

@if ($errors->any())
    <div class="row">
        <div class="col-md-12">
            <div class="alert alert-danger">
                <strong>Please correct the following:</strong>
                <ul class="mb-0 mt-2">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif

<form action="{{ route('loans.types.store') }}" method="POST">
    @csrf

    <div class="row">

        {{-- 1. BASIC DETAILS --}}
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">1. Basic Loan Type Details</div>

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label for="loan_type_name">Loan Type Name <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                class="form-control"
                                id="loan_type_name"
                                name="loan_type_name"
                                value="{{ old('loan_type_name') }}"
                                placeholder="e.g. Emergency Loan"
                                required>
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_code">Loan Type Code <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                class="form-control"
                                id="loan_type_code"
                                name="loan_type_code"
                                value="{{ old('loan_type_code') }}"
                                placeholder="e.g. EL01"
                                required>
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_active">Loan Type Status <span class="text-danger">*</span></label>
                            <select class="form-control" id="loan_type_active" name="loan_type_active" required>
                                <option value="1" {{ old('loan_type_active', '1') == '1' ? 'selected' : '' }}>Active</option>
                                <option value="0" {{ old('loan_type_active') == '0' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_interest">Interest (%) <span class="text-danger">*</span></label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                class="form-control"
                                id="loan_type_interest"
                                name="loan_type_interest"
                                value="{{ old('loan_type_interest') }}"
                                placeholder="e.g. 12"
                                required>
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_interest_type">Interest Type <span class="text-danger">*</span></label>
                            <select class="form-control" id="loan_type_interest_type" name="loan_type_interest_type" required>
                                <option value="">Select interest type</option>
                                <option value="REDUCING BALANCE" {{ old('loan_type_interest_type') == 'REDUCING BALANCE' ? 'selected' : '' }}>Reducing Balance</option>
                                <option value="FIXED INTEREST" {{ old('loan_type_interest_type') == 'FIXED INTEREST' ? 'selected' : '' }}>Fixed Interest</option>
                            </select>
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_duration">Maximum Duration (Months) <span class="text-danger">*</span></label>
                            <input
                                type="number"
                                min="1"
                                class="form-control"
                                id="loan_type_duration"
                                name="loan_type_duration"
                                value="{{ old('loan_type_duration') }}"
                                placeholder="e.g. 12"
                                required>
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_max_amount">Maximum Amount <span class="text-danger">*</span></label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                class="form-control"
                                id="loan_type_max_amount"
                                name="loan_type_max_amount"
                                value="{{ old('loan_type_max_amount') }}"
                                placeholder="e.g. 500000"
                                required>
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_share_factor">Share Factor <span class="text-danger">*</span></label>
                            <input
                                type="number"
                                step="1"
                                min="0"
                                class="form-control"
                                id="loan_type_share_factor"
                                name="loan_type_share_factor"
                                value="{{ old('loan_type_share_factor', 3) }}"
                                placeholder="e.g. 3"
                                required>
                            <small class="text-muted">Multiplier used against member shares/capital when determining eligibility.</small>
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_guaranteable_percent">Guarantee Percent (%) <span class="text-danger">*</span></label>
                            <input
                                type="number"
                                min="0"
                                max="100"
                                class="form-control"
                                id="loan_type_guaranteable_percent"
                                name="loan_type_guaranteable_percent"
                                value="{{ old('loan_type_guaranteable_percent', 100) }}"
                                placeholder="e.g. 100"
                                required>
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_insurable">Insurable <span class="text-danger">*</span></label>
                            <select class="form-control" id="loan_type_insurable" name="loan_type_insurable" required>
                                <option value="Y" {{ old('loan_type_insurable', 'Y') == 'Y' ? 'selected' : '' }}>Yes</option>
                                <option value="N" {{ old('loan_type_insurable') == 'N' ? 'selected' : '' }}>No</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 2. QUALIFICATION RULES --}}
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">2. Qualification Rules</div>

                    <div class="row">
                        <div class="col-md-4 form-group mb-3">
                            <label for="loan_type_qualification_period">Minimum Months in SACCO <span class="text-danger">*</span></label>
                            <input
                                type="number"
                                min="0"
                                class="form-control"
                                id="loan_type_qualification_period"
                                name="loan_type_qualification_period"
                                value="{{ old('loan_type_qualification_period', 0) }}"
                                placeholder="e.g. 6"
                                required>
                        </div>

                        <div class="col-md-4 form-group mb-3">
                            <label for="loan_type_max_qualification_period">Maximum Months in SACCO</label>
                            <input
                                type="number"
                                min="0"
                                class="form-control"
                                id="loan_type_max_qualification_period"
                                name="loan_type_max_qualification_period"
                                value="{{ old('loan_type_max_qualification_period') }}"
                                placeholder="Leave blank if no upper limit">
                            <small class="text-muted">Optional. Use this where a loan should only be available within a certain membership age.</small>
                        </div>

                        <div class="col-md-4 form-group mb-3 d-flex align-items-end">
                            <label class="switch switch-primary me-3 mb-0">
                                <span>Instant Qualification</span>
                                <input
                                    type="checkbox"
                                    id="loan_type_instant_qualification"
                                    name="loan_type_instant_qualification"
                                    value="1"
                                    {{ old('loan_type_instant_qualification') ? 'checked' : '' }}>
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div class="col-md-12">
                            <small class="text-muted">
                                Instant qualification allows the loan to bypass the normal minimum-qualification restriction where applicable in your business rules.
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 3. CRB SETTINGS --}}
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">3. CRB Settings</div>

                    <div class="row">
                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_crb_required">CRB Check Required <span class="text-danger">*</span></label>
                            <select class="form-control" id="loan_type_crb_required" name="loan_type_crb_required" required>
                                <option value="N" {{ old('loan_type_crb_required', 'N') == 'N' ? 'selected' : '' }}>No</option>
                                <option value="Y" {{ old('loan_type_crb_required') == 'Y' ? 'selected' : '' }}>Yes</option>
                            </select>
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_crb_charge">CRB Charge</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                class="form-control"
                                id="loan_type_crb_charge"
                                name="loan_type_crb_charge"
                                value="{{ old('loan_type_crb_charge', 0) }}"
                                placeholder="e.g. 100">
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label for="loan_type_crb_effect">CRB Charge Treatment</label>
                            <select class="form-control" id="loan_type_crb_effect" name="loan_type_crb_effect">
                                <option value="">Select treatment</option>
                                <option value="ADD_TO_LOAN" {{ old('loan_type_crb_effect') == 'ADD_TO_LOAN' ? 'selected' : '' }}>Add to Loan</option>
                                <option value="DEDUCT_FROM_DISBURSEMENT" {{ old('loan_type_crb_effect') == 'DEDUCT_FROM_DISBURSEMENT' ? 'selected' : '' }}>Deduct from Amount Disbursed</option>
                            </select>
                            <small class="text-muted">Only applies if CRB check is required and a CRB charge is set.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 4. COMMISSION SETTINGS --}}
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">4. Commission Settings</div>

                    <div class="row">
                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_commission_required">Commission Required <span class="text-danger">*</span></label>
                            <select class="form-control" id="loan_type_commission_required" name="loan_type_commission_required" required>
                                <option value="N" {{ old('loan_type_commission_required', 'N') == 'N' ? 'selected' : '' }}>No</option>
                                <option value="Y" {{ old('loan_type_commission_required') == 'Y' ? 'selected' : '' }}>Yes</option>
                            </select>
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_commission_type">Commission Type</label>
                            <select class="form-control" id="loan_type_commission_type" name="loan_type_commission_type">
                                <option value="">Select type</option>
                                <option value="FIXED" {{ old('loan_type_commission_type') == 'FIXED' ? 'selected' : '' }}>Fixed Amount</option>
                                <option value="PERCENT" {{ old('loan_type_commission_type') == 'PERCENT' ? 'selected' : '' }}>Percentage</option>
                            </select>
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_commission_value">Commission Value</label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                class="form-control"
                                id="loan_type_commission_value"
                                name="loan_type_commission_value"
                                value="{{ old('loan_type_commission_value', 0) }}"
                                placeholder="e.g. 500 or 2.5">
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="loan_type_commission_effect">Commission Treatment</label>
                            <select class="form-control" id="loan_type_commission_effect" name="loan_type_commission_effect">
                                <option value="">Select treatment</option>
                                <option value="ADD_TO_LOAN" {{ old('loan_type_commission_effect') == 'ADD_TO_LOAN' ? 'selected' : '' }}>Add to Loan</option>
                                <option value="DEDUCT_FROM_DISBURSEMENT" {{ old('loan_type_commission_effect') == 'DEDUCT_FROM_DISBURSEMENT' ? 'selected' : '' }}>Deduct from Amount Disbursed</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- 5. ACCOUNTING --}}
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">5. Accounting Setup</div>

                    <div class="row">
                        <div class="col-md-4 form-group mb-3">
                            <label for="loan_type_acount">Loan Principal Account <span class="text-danger">*</span></label>
                            <select class="form-control" id="loan_type_acount" name="loan_type_acount" required>
                                <option value="">Select asset account</option>
                                @foreach($assetAccounts as $acc)
                                    <option value="{{ $acc->sub_account_id }}" {{ old('loan_type_acount') == $acc->sub_account_id ? 'selected' : '' }}>
                                        {{ $acc->sub_account_name }} ({{ $acc->main_account_code }}/{{ $acc->sub_account_code }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Only asset accounts are listed here.</small>
                        </div>

                        <div class="col-md-4 form-group mb-3">
                            <label for="loan_type_int_account">Interest Account <span class="text-danger">*</span></label>
                            <select class="form-control" id="loan_type_int_account" name="loan_type_int_account" required>
                                <option value="">Select income account</option>
                                @foreach($incomeAccounts as $acc)
                                    <option value="{{ $acc->sub_account_id }}" {{ old('loan_type_int_account') == $acc->sub_account_id ? 'selected' : '' }}>
                                        {{ $acc->sub_account_name }} ({{ $acc->main_account_code }}/{{ $acc->sub_account_code }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Only income accounts are listed here.</small>
                        </div>

                        <div class="col-md-4 form-group mb-3">
                            <label for="loan_type_comm_account">Commission Account <span class="text-danger">*</span></label>
                            <select class="form-control" id="loan_type_comm_account" name="loan_type_comm_account" required>
                                <option value="">Select income / liability account</option>
                                @foreach($incomeLiabilityAccounts as $acc)
                                    <option value="{{ $acc->sub_account_id }}" {{ old('loan_type_comm_account') == $acc->sub_account_id ? 'selected' : '' }}>
                                        {{ $acc->sub_account_name }} ({{ $acc->main_account_code }}/{{ $acc->sub_account_code }})
                                    </option>
                                @endforeach
                            </select>
                            <small class="text-muted">Only income and liability accounts are listed here.</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- ACTIONS --}}
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <button type="submit" class="btn btn-primary">Save Loan Type</button>
                    <a href="{{ route('loans.types') }}" class="btn btn-light">Cancel</a>
                </div>
            </div>
        </div>

    </div>
</form>
@endsection