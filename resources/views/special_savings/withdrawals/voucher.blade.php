@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Withdrawal Voucher</h1>
        <ul>
            <li><a href="{{ route('special_savings.withdrawals.show', $record->special_saving_withdrawal_id) }}">Withdrawal</a></li>
            <li>Voucher</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-body">
                    <h4>Special Saving Withdrawal Voucher</h4>
                    <hr>

                    <table class="table table-bordered table-sm">
                        <tr><th>Member</th><td>{{ $record->member_name }} - {{ $record->member_sacco_id }}</td></tr>
                        <tr><th>Account</th><td>{{ $record->special_saving_account_number }}</td></tr>
                        <tr><th>Product</th><td>{{ $record->special_saving_product_name }}</td></tr>
                        <tr><th>Date</th><td>{{ $record->special_saving_withdrawal_request_date }}</td></tr>
                        <tr><th>Principal</th><td>{{ number_format($record->special_saving_withdrawal_principal_amount, 2) }}</td></tr>
                        <tr><th>Interest</th><td>{{ number_format($record->special_saving_withdrawal_interest_amount, 2) }}</td></tr>
                        <tr><th>Total</th><td>{{ number_format($record->special_saving_withdrawal_total_amount, 2) }}</td></tr>
                        <tr><th>Status</th><td>{{ $record->special_saving_withdrawal_status }}</td></tr>
                    </table>

                    <button onclick="window.print()" class="btn btn-primary">Print</button>
                    <a href="{{ route('special_savings.withdrawals.show', $record->special_saving_withdrawal_id) }}" class="btn btn-outline-secondary">Back</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection