@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Withdrawal Details</h1>
        <ul>
            <li><a href="{{ route('special_savings.withdrawals.index') }}">Withdrawals</a></li>
            <li>#{{ $record->special_saving_withdrawal_id }}</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @include('special_savings.partials.alerts')

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-body">
                    <table class="table table-bordered table-sm">
                        <tr><th>Member</th><td>{{ $record->member_name }} - {{ $record->member_sacco_id }}</td></tr>
                        <tr><th>Account</th><td>{{ $record->special_saving_account_number }}</td></tr>
                        <tr><th>Product</th><td>{{ $record->special_saving_product_name }}</td></tr>
                        <tr><th>Request Date</th><td>{{ $record->special_saving_withdrawal_request_date }}</td></tr>
                        <tr><th>Principal</th><td>{{ number_format($record->special_saving_withdrawal_principal_amount, 2) }}</td></tr>
                        <tr><th>Interest</th><td>{{ number_format($record->special_saving_withdrawal_interest_amount, 2) }}</td></tr>
                        <tr><th>Total</th><td>{{ number_format($record->special_saving_withdrawal_total_amount, 2) }}</td></tr>
                        <tr><th>Early Withdrawal</th><td>{{ $record->special_saving_withdrawal_is_early }}</td></tr>
                        <tr><th>Forfeited Interest</th><td>{{ number_format($record->special_saving_withdrawal_forfeited_interest, 2) }}</td></tr>
                        <tr><th>Status</th><td>{{ $record->special_saving_withdrawal_status }}</td></tr>
                    </table>

                    @if($record->special_saving_withdrawal_status == 'Pending')
                        <form method="POST" action="{{ route('special_savings.withdrawals.approve', $record->special_saving_withdrawal_id) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-success">Approve</button>
                        </form>

                        <form method="POST" action="{{ route('special_savings.withdrawals.reject', $record->special_saving_withdrawal_id) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-danger">Reject</button>
                        </form>
                    @endif

                    @if($record->special_saving_withdrawal_status == 'Approved')
                        <form method="POST" action="{{ route('special_savings.withdrawals.pay', $record->special_saving_withdrawal_id) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-primary">Pay</button>
                        </form>
                    @endif

                    @if(in_array($record->special_saving_withdrawal_status, ['Pending', 'Approved']))
                        <form method="POST" action="{{ route('special_savings.withdrawals.cancel', $record->special_saving_withdrawal_id) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-outline-danger">Cancel</button>
                        </form>
                    @endif

                    <a href="{{ route('special_savings.withdrawals.voucher', $record->special_saving_withdrawal_id) }}" class="btn btn-outline-secondary">Voucher</a>
                    <a href="{{ route('special_savings.withdrawals.index') }}" class="btn btn-outline-secondary">Back</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection