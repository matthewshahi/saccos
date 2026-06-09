@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Special Savings</h1>
        <ul>
            <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
            <li>Special Savings</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @include('special_savings.partials.alerts')
    @include('special_savings.partials.nav')

    <div class="row">
        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-body">
                    <p class="text-muted mb-1">Products</p>
                    <h3 class="mb-0">{{ number_format($summary['products'] ?? 0) }}</h3>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-body">
                    <p class="text-muted mb-1">Active Accounts</p>
                    <h3 class="mb-0">{{ number_format($summary['active_accounts'] ?? 0) }}</h3>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-body">
                    <p class="text-muted mb-1">Principal Balance</p>
                    <h3 class="mb-0">{{ number_format($summary['principal_balance'] ?? 0, 2) }}</h3>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card mb-4">
                <div class="card-body">
                    <p class="text-muted mb-1">Total Balance</p>
                    <h3 class="mb-0">{{ number_format($summary['total_balance'] ?? 0, 2) }}</h3>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <p class="text-muted mb-1">Accrued Interest</p>
                    <h4 class="mb-0">{{ number_format($summary['accrued_interest'] ?? 0, 2) }}</h4>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <p class="text-muted mb-1">Available Interest</p>
                    <h4 class="mb-0">{{ number_format($summary['available_interest'] ?? 0, 2) }}</h4>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <p class="text-muted mb-1">FEDHA</p>
                    <h4 class="mb-0">12.5% p.a.</h4>
                </div>
            </div>
        </div>

        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">Latest Transactions</div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Member</th>
                                    <th>Product</th>
                                    <th>Type</th>
                                    <th>Doc No</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-end">Balance After</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($latest_transactions as $row)
                                    <tr>
                                        <td>{{ $row->special_saving_transaction_date }}</td>
                                        <td>{{ $row->member_name }} <small class="text-muted">{{ $row->member_sacco_id }}</small></td>
                                        <td>{{ $row->special_saving_product_name }}</td>
                                        <td>{{ $row->special_saving_transaction_type }}</td>
                                        <td>{{ $row->special_saving_transaction_doc_no }}</td>
                                        <td class="text-end">{{ number_format($row->special_saving_transaction_amount, 2) }}</td>
                                        <td class="text-end">{{ number_format($row->special_saving_transaction_total_balance_after, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">No transactions found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection