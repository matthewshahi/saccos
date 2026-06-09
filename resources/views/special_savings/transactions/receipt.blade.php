@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Special Saving Receipt</h1>
        <ul>
            <li><a href="{{ route('special_savings.transactions.index') }}">Transactions</a></li>
            <li>Receipt</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-body">
                    <h4 class="mb-3">Receipt: {{ $record->special_saving_transaction_doc_no }}</h4>

                    <table class="table table-bordered table-sm">
                        <tr><th>Date</th><td>{{ $record->special_saving_transaction_date }}</td></tr>
                        <tr><th>Member</th><td>{{ $record->member_name }} - {{ $record->member_sacco_id }}</td></tr>
                        <tr><th>Account</th><td>{{ $record->special_saving_account_number }}</td></tr>
                        <tr><th>Product</th><td>{{ $record->special_saving_product_name }}</td></tr>
                        <tr><th>Type</th><td>{{ $record->special_saving_transaction_type }}</td></tr>
                        <tr><th>Amount</th><td>{{ number_format($record->special_saving_transaction_amount, 2) }}</td></tr>
                        <tr><th>Reference</th><td>{{ $record->special_saving_transaction_reference }}</td></tr>
                        <tr><th>Balance After</th><td>{{ number_format($record->special_saving_transaction_total_balance_after, 2) }}</td></tr>
                    </table>

                    <button onclick="window.print()" class="btn btn-primary">Print</button>
                    <a href="{{ route('special_savings.transactions.index') }}" class="btn btn-outline-secondary">Back</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection