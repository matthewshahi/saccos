@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Account Details</h1>
        <ul>
            <li><a href="{{ route('special_savings.accounts.index') }}">Accounts</a></li>
            <li>{{ $record->special_saving_account_number }}</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @include('special_savings.partials.alerts')
    @include('special_savings.partials.nav')

    <div class="row">
        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">Account</div>

                    <table class="table table-bordered table-sm">
                        <tr><th>Account No</th><td>{{ $record->special_saving_account_number }}</td></tr>
                        <tr><th>Member</th><td>{{ $record->member_name }}</td></tr>
                        <tr><th>Sacco No</th><td>{{ $record->member_sacco_id }}</td></tr>
                        <tr><th>Product</th><td>{{ $record->special_saving_product_name }}</td></tr>
                        <tr><th>Opened</th><td>{{ $record->special_saving_account_opening_date }}</td></tr>
                        <tr><th>Next Free Withdrawal</th><td>{{ $record->special_saving_account_next_free_withdrawal_date }}</td></tr>
                        <tr><th>Status</th><td>{{ $record->special_saving_account_status }}</td></tr>
                    </table>

                    <a href="{{ route('special_savings.accounts.edit', $record->special_saving_account_id) }}" class="btn btn-primary btn-sm">Edit</a>
                    <a href="{{ route('special_savings.accounts.statement', $record->special_saving_account_id) }}" class="btn btn-outline-secondary btn-sm">Statement</a>
                </div>
            </div>
        </div>

        <div class="col-md-8">
            <div class="row">
                <div class="col-md-3">
                    <div class="card mb-4"><div class="card-body">
                        <p class="text-muted mb-1">Principal</p>
                        <h4>{{ number_format($record->special_saving_account_principal_balance, 2) }}</h4>
                    </div></div>
                </div>

                <div class="col-md-3">
                    <div class="card mb-4"><div class="card-body">
                        <p class="text-muted mb-1">Accrued Interest</p>
                        <h4>{{ number_format($record->special_saving_account_accrued_interest_balance, 2) }}</h4>
                    </div></div>
                </div>

                <div class="col-md-3">
                    <div class="card mb-4"><div class="card-body">
                        <p class="text-muted mb-1">Available Interest</p>
                        <h4>{{ number_format($record->special_saving_account_available_interest_balance, 2) }}</h4>
                    </div></div>
                </div>

                <div class="col-md-3">
                    <div class="card mb-4"><div class="card-body">
                        <p class="text-muted mb-1">Total</p>
                        <h4>{{ number_format($record->special_saving_account_total_balance, 2) }}</h4>
                    </div></div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">Recent Transactions</div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Doc No</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-end">Balance After</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($transactions as $txn)
                                    <tr>
                                        <td>{{ $txn->special_saving_transaction_date }}</td>
                                        <td>{{ $txn->special_saving_transaction_type }}</td>
                                        <td>{{ $txn->special_saving_transaction_doc_no }}</td>
                                        <td class="text-end">{{ number_format($txn->special_saving_transaction_amount, 2) }}</td>
                                        <td class="text-end">{{ number_format($txn->special_saving_transaction_total_balance_after, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No transactions found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <a href="{{ route('special_savings.deposits.create') }}" class="btn btn-success btn-sm">Post Deposit</a>
                    <a href="{{ route('special_savings.withdrawals.create') }}" class="btn btn-warning btn-sm">Request Withdrawal</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection