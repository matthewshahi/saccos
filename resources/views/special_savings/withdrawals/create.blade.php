@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Request Withdrawal</h1>
        <ul>
            <li><a href="{{ route('special_savings.withdrawals.index') }}">Withdrawals</a></li>
            <li>Create</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @include('special_savings.partials.alerts')

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">Withdrawal Details</div>

                    <form method="POST" action="{{ route('special_savings.withdrawals.store') }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label>Special Saving Account ID</label>
                                <input type="number" name="special_saving_withdrawal_account_id" value="{{ old('special_saving_withdrawal_account_id') }}" class="form-control" required>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>Request Date</label>
                                <input type="date" name="special_saving_withdrawal_request_date" value="{{ old('special_saving_withdrawal_request_date', date('Y-m-d')) }}" class="form-control" required>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>Principal Amount</label>
                                <input type="number" step="0.01" name="special_saving_withdrawal_principal_amount" value="{{ old('special_saving_withdrawal_principal_amount', 0) }}" class="form-control">
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>Interest Amount</label>
                                <input type="number" step="0.01" name="special_saving_withdrawal_interest_amount" value="{{ old('special_saving_withdrawal_interest_amount', 0) }}" class="form-control">
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>Payment Mode</label>
                                <select name="special_saving_withdrawal_payment_mode" class="form-control">
                                    <option value="">Select mode</option>
                                    @foreach(['CASH', 'BANK', 'MPESA', 'TRANSFER'] as $mode)
                                        <option value="{{ $mode }}" {{ old('special_saving_withdrawal_payment_mode') == $mode ? 'selected' : '' }}>{{ $mode }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>Payment Reference</label>
                                <input type="text" name="special_saving_withdrawal_payment_reference" value="{{ old('special_saving_withdrawal_payment_reference') }}" class="form-control">
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>Payment Ledger</label>
                                <select name="special_saving_withdrawal_payment_sub_account_id" class="form-control">
                                    <option value="">Select ledger</option>
                                    @foreach($sub_accounts as $acc)
                                        <option value="{{ $acc->sub_account_id }}" {{ old('special_saving_withdrawal_payment_sub_account_id') == $acc->sub_account_id ? 'selected' : '' }}>
                                            {{ $acc->sub_account_name }} - {{ $acc->sub_account_code }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-12 form-group mb-3">
                                <label>Notes</label>
                                <textarea name="special_saving_withdrawal_notes" class="form-control" rows="2">{{ old('special_saving_withdrawal_notes') }}</textarea>
                            </div>

                            <div class="col-md-12">
                                <button class="btn btn-primary">Submit Request</button>
                                <a href="{{ route('special_savings.withdrawals.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection