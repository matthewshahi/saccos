@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Post Special Saving Deposit</h1>
        <ul>
            <li><a href="{{ route('special_savings.accounts.index') }}">Accounts</a></li>
            <li>Deposit</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @include('special_savings.partials.alerts')

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">Deposit Details</div>

                    <form method="POST" action="{{ route('special_savings.deposits.store') }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label>Special Saving Account ID</label>
                                <input type="number" name="special_saving_transaction_account_id" value="{{ old('special_saving_transaction_account_id') }}" class="form-control" required>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>Amount</label>
                                <input type="number" step="0.01" name="special_saving_transaction_amount" value="{{ old('special_saving_transaction_amount') }}" class="form-control" required>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>Transaction Date</label>
                                <input type="date" name="special_saving_transaction_date" value="{{ old('special_saving_transaction_date', date('Y-m-d')) }}" class="form-control" required>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>Reference</label>
                                <input type="text" name="special_saving_transaction_reference" value="{{ old('special_saving_transaction_reference') }}" class="form-control">
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>Source Ledger</label>
                                <input type="number" name="special_saving_transaction_sub_account_id" value="{{ old('special_saving_transaction_sub_account_id') }}" class="form-control">
                            </div>

                            <div class="col-md-12 form-group mb-3">
                                <label>Description</label>
                                <textarea name="special_saving_transaction_description" class="form-control" rows="2">{{ old('special_saving_transaction_description') }}</textarea>
                            </div>

                            <div class="col-md-12">
                                <button class="btn btn-primary">Post Deposit</button>
                                <a href="{{ route('special_savings.accounts.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection