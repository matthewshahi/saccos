@extends('layouts.app')

@section('title', 'Edit Loan Deduction Type')

@section('content')
<div class="breadcrumb">
    <h1>Edit Loan Deduction Type</h1>
    <ul>
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('loans.deduction-types') }}">Loan Deduction Types</a></li>
        <li>Edit</li>
    </ul>
</div>

<div class="separator-breadcrumb border-top"></div>

<div class="row mb-3">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
                <div>
                    <strong>Loan Deduction Types</strong><br>
                    <small class="text-muted">Update deduction type details and manage status.</small>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('loans.deduction-types') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="i-File-Horizontal-Text"></i> Back to List
                    </a>
                    <a href="{{ route('loans.deduction-types.add') }}" class="btn btn-primary btn-sm">
                        <i class="i-Add"></i> Add New
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        @include('partials.alerts')

        <form action="{{ route('loans.deduction-types.update', $record->deduction_type_id) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="card-title mb-0">Deduction Type Details</h4>
                </div>

                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label for="deduction_type_name">Deduction Type Name <span class="text-danger">*</span></label>
                            <input
                                type="text"
                                name="deduction_type_name"
                                id="deduction_type_name"
                                class="form-control @error('deduction_type_name') is-invalid @enderror"
                                value="{{ old('deduction_type_name', $record->deduction_type_name) }}"
                                required
                            >
                            @error('deduction_type_name')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label for="deduction_type_code">Deduction Type Code</label>
                            <input
                                type="text"
                                name="deduction_type_code"
                                id="deduction_type_code"
                                class="form-control @error('deduction_type_code') is-invalid @enderror"
                                value="{{ old('deduction_type_code', $record->deduction_type_code) }}"
                            >
                            @error('deduction_type_code')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4 form-group mb-3">
                            <label for="deduction_type_value_type">Value Type <span class="text-danger">*</span></label>
                            <select
                                name="deduction_type_value_type"
                                id="deduction_type_value_type"
                                class="form-control @error('deduction_type_value_type') is-invalid @enderror"
                                required
                            >
                                <option value="FIXED" {{ old('deduction_type_value_type', $record->deduction_type_value_type) === 'FIXED' ? 'selected' : '' }}>
                                    Fixed Amount
                                </option>
                                <option value="PERCENT" {{ old('deduction_type_value_type', $record->deduction_type_value_type) === 'PERCENT' ? 'selected' : '' }}>
                                    Percent
                                </option>
                            </select>
                            @error('deduction_type_value_type')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4 form-group mb-3">
                            <label for="deduction_type_default_value">Default Value <span class="text-danger">*</span></label>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                name="deduction_type_default_value"
                                id="deduction_type_default_value"
                                class="form-control @error('deduction_type_default_value') is-invalid @enderror"
                                value="{{ old('deduction_type_default_value', $record->deduction_type_default_value) }}"
                                required
                            >
                            @error('deduction_type_default_value')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-4 form-group mb-3">
                            <label for="deduction_type_effect">Effect <span class="text-danger">*</span></label>
                            <select
                                name="deduction_type_effect"
                                id="deduction_type_effect"
                                class="form-control @error('deduction_type_effect') is-invalid @enderror"
                                required
                            >
                                <option value="ADD_TO_LOAN" {{ old('deduction_type_effect', $record->deduction_type_effect) === 'ADD_TO_LOAN' ? 'selected' : '' }}>
                                    Add to Loan
                                </option>
                                <option value="DEDUCT_FROM_DISBURSEMENT" {{ old('deduction_type_effect', $record->deduction_type_effect) === 'DEDUCT_FROM_DISBURSEMENT' ? 'selected' : '' }}>
                                    Deduct from Disbursement
                                </option>
                            </select>
                            @error('deduction_type_effect')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-12 form-group mb-3">
                            <label for="deduction_type_account">Posting Account</label>
                            <select
                                name="deduction_type_account"
                                id="deduction_type_account"
                                class="form-control @error('deduction_type_account') is-invalid @enderror"
                            >
                                <option value="">-- Select Posting Account --</option>
                                @foreach($incomeLiabilityAccounts as $acc)
                                    <option value="{{ $acc->sub_account_id }}"
                                        {{ (string) old('deduction_type_account', $record->deduction_type_account) === (string) $acc->sub_account_id ? 'selected' : '' }}>
                                        {{ $acc->sub_account_name }}
                                        @if(!empty($acc->sub_account_code))
                                            - {{ $acc->sub_account_code }}
                                        @endif
                                        @if(!empty($acc->main_account_name))
                                            ({{ $acc->main_account_name }}{{ !empty($acc->main_account_type) ? ' - '.$acc->main_account_type : '' }})
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                            @error('deduction_type_account')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                            <small class="text-muted">Only income and liability accounts are shown here.</small>
                        </div>

                        <div class="col-md-12 form-group mb-3">
                            <label for="deduction_type_description">Description</label>
                            <textarea
                                name="deduction_type_description"
                                id="deduction_type_description"
                                rows="4"
                                class="form-control @error('deduction_type_description') is-invalid @enderror"
                            >{{ old('deduction_type_description', $record->deduction_type_description) }}</textarea>
                            @error('deduction_type_description')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="col-md-12 form-group mb-3">
                            <div class="form-check">
                                <input
                                    type="checkbox"
                                    name="deduction_type_active"
                                    id="deduction_type_active"
                                    value="1"
                                    class="form-check-input"
                                    {{ old('deduction_type_active', $record->deduction_type_active) ? 'checked' : '' }}
                                >
                                <label class="form-check-label" for="deduction_type_active">
                                    Active
                                </label>
                            </div>
                            <small class="text-muted">Only active deduction types should normally be used during loan processing.</small>
                        </div>
                    </div>
                </div>

                <div class="card-footer d-flex justify-content-between">
                    <a href="{{ route('loans.deduction-types') }}" class="btn btn-secondary">
                        Back to List
                    </a>

                    <button type="submit" class="btn btn-primary">
                        Update Deduction Type
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection