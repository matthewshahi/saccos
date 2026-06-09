@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Edit Category</h1>
        <ul>
            <li><a href="{{ route('special_savings.categories.index') }}">Categories</a></li>
            <li>Edit</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @include('special_savings.partials.alerts')

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">Category Details</div>

                    <form method="POST" action="{{ route('special_savings.categories.update', $record->special_saving_category_id) }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label>Category Name</label>
                                <input type="text" name="special_saving_category_name" value="{{ old('special_saving_category_name', $record->special_saving_category_name) }}" class="form-control" required>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>Category Code</label>
                                <input type="text" name="special_saving_category_code" value="{{ old('special_saving_category_code', $record->special_saving_category_code) }}" class="form-control" required>
                            </div>

                            <div class="col-md-12 form-group mb-3">
                                <label>Description</label>
                                <textarea name="special_saving_category_description" class="form-control" rows="2">{{ old('special_saving_category_description', $record->special_saving_category_description) }}</textarea>
                            </div>

                            @php
                                $ledgerFields = [
                                    'special_saving_liability_sub_account_id' => 'Savings Liability Ledger',
                                    'special_saving_interest_expense_sub_account_id' => 'Interest Expense Ledger',
                                    'special_saving_interest_payable_sub_account_id' => 'Interest Payable Ledger',
                                    'special_saving_penalty_income_sub_account_id' => 'Penalty Income Ledger',
                                    'special_saving_charge_income_sub_account_id' => 'Charge Income Ledger',
                                    'special_saving_default_cash_sub_account_id' => 'Default Cash/Bank Ledger',
                                ];
                            @endphp

                            @foreach($ledgerFields as $field => $label)
                                <div class="col-md-6 form-group mb-3">
                                    <label>{{ $label }}</label>
                                    <select name="{{ $field }}" class="form-control" required>
                                        <option value="">Select ledger</option>
                                        @foreach($sub_accounts as $acc)
                                            <option value="{{ $acc->sub_account_id }}" {{ old($field, $record->$field) == $acc->sub_account_id ? 'selected' : '' }}>
                                                {{ $acc->sub_account_name }} - {{ $acc->sub_account_code }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            @endforeach

                            <div class="col-md-6 form-group mb-3">
                                <label>Status</label>
                                <select name="special_saving_category_status" class="form-control">
                                    <option value="Active" {{ old('special_saving_category_status', $record->special_saving_category_status) == 'Active' ? 'selected' : '' }}>Active</option>
                                    <option value="Inactive" {{ old('special_saving_category_status', $record->special_saving_category_status) == 'Inactive' ? 'selected' : '' }}>Inactive</option>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <button class="btn btn-primary">Update Category</button>
                                <a href="{{ route('special_savings.categories.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection