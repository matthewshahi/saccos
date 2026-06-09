@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Special Saving Transactions</h1>
        <ul>
            <li><a href="{{ route('special_savings.dashboard') }}">Special Savings</a></li>
            <li>Transactions</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @include('special_savings.partials.alerts')
    @include('special_savings.partials.nav')

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="card-title mb-0">Transactions</div>
                        <a href="{{ route('special_savings.deposits.create') }}" class="btn btn-success btn-sm">Post Deposit</a>
                    </div>

                    <form method="GET" class="row mb-3">
                        <div class="col-md-3 form-group mb-2">
                            <input type="text" name="q" value="{{ $q ?? '' }}" class="form-control" placeholder="Search transaction">
                        </div>

                        <div class="col-md-3 form-group mb-2">
                            <select name="type" class="form-control">
                                <option value="">All Types</option>
                                @foreach(['DEPOSIT','WITHDRAWAL','INTEREST_ACCRUAL','INTEREST_VESTING','INTEREST_FORFEITURE','TRANSFER_IN','TRANSFER_OUT','REVERSAL','ADJUSTMENT'] as $item)
                                    <option value="{{ $item }}" {{ ($type ?? '') == $item ? 'selected' : '' }}>{{ $item }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2 form-group mb-2">
                            <input type="date" name="from" value="{{ $from ?? '' }}" class="form-control">
                        </div>

                        <div class="col-md-2 form-group mb-2">
                            <input type="date" name="to" value="{{ $to ?? '' }}" class="form-control">
                        </div>

                        <div class="col-md-2 form-group mb-2">
                            <button class="btn btn-secondary btn-block">Filter</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Member</th>
                                    <th>Account</th>
                                    <th>Product</th>
                                    <th>Type</th>
                                    <th>Doc No</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-end">Balance</th>
                                    <th style="width:150px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($records as $row)
                                    <tr>
                                        <td>{{ $row->special_saving_transaction_date }}</td>
                                        <td>{{ $row->member_name }} <small class="text-muted d-block">{{ $row->member_sacco_id }}</small></td>
                                        <td>{{ $row->special_saving_account_number }}</td>
                                        <td>{{ $row->special_saving_product_name }}</td>
                                        <td>{{ $row->special_saving_transaction_type }}</td>
                                        <td>{{ $row->special_saving_transaction_doc_no }}</td>
                                        <td class="text-end">{{ number_format($row->special_saving_transaction_amount, 2) }}</td>
                                        <td class="text-end">{{ number_format($row->special_saving_transaction_total_balance_after, 2) }}</td>
                                        <td>
                                            <a href="{{ route('special_savings.transactions.show', $row->special_saving_transaction_id) }}" class="btn btn-sm btn-info">View</a>
                                            <a href="{{ route('special_savings.transactions.receipt', $row->special_saving_transaction_id) }}" class="btn btn-sm btn-outline-secondary">Receipt</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">No transactions found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $records->links() }}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection