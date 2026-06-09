@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>New Transaction</h1>
        <ul>
            <li><a href="{{ route('special_savings.transactions.index') }}">Transactions</a></li>
            <li>Create</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @include('special_savings.partials.alerts')

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">Transaction Details</div>

                    <form method="POST" action="{{ route('special_savings.transactions.store') }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label>Account ID</label>
                                <input type="number" name="special_saving_transaction_account_id" class="form-control" value="{{ old('special_saving_transaction_account_id') }}" required>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>Transaction Type</label>
                                <select name="special_saving_transaction_type" class="form-control" required>
                                    <option value="DEPOSIT" {{ old('special_saving_transaction_type') == 'DEPOSIT' ? 'selected' : '' }}>DEPOSIT</option>
                                </select>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>Amount</label>
                                <input type="number" step="0.01" name="special_saving_transaction_amount" class="form-control" value="{{ old('special_saving_transaction_amount') }}" required>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>Date</label>
                                <input type="date" name="special_saving_transaction_date" class="form-control" value="{{ old('special_saving_transaction_date', date('Y-m-d')) }}" required>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>Reference</label>
                                <input type="text" name="special_saving_transaction_reference" class="form-control" value="{{ old('special_saving_transaction_reference') }}">
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>Sub Account ID</label>
                                <input type="number" name="special_saving_transaction_sub_account_id" class="form-control" value="{{ old('special_saving_transaction_sub_account_id') }}">
                            </div>

                            <div class="col-md-12 form-group mb-3">
                                <label>Description</label>
                                <textarea name="special_saving_transaction_description" class="form-control" rows="2">{{ old('special_saving_transaction_description') }}</textarea>
                            </div>

                            <div class="col-md-12">
                                <button class="btn btn-primary">Post Transaction</button>
                                <a href="{{ route('special_savings.transactions.index') }}" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection