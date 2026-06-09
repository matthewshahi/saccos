@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Account Statement</h1>
        <ul>
            <li><a href="{{ route('special_savings.accounts.show', $record->special_saving_account_id) }}">Account</a></li>
            <li>Statement</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @include('special_savings.partials.alerts')

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">
                        {{ $record->member_name }} - {{ $record->special_saving_account_number }}
                    </div>

                    <form method="GET" class="row mb-3">
                        <div class="col-md-3 form-group mb-2">
                            <label>From</label>
                            <input type="date" name="from" value="{{ $from }}" class="form-control">
                        </div>
                        <div class="col-md-3 form-group mb-2">
                            <label>To</label>
                            <input type="date" name="to" value="{{ $to }}" class="form-control">
                        </div>
                        <div class="col-md-3 form-group mb-2 d-flex align-items-end">
                            <button class="btn btn-secondary">Filter</button>
                            <a href="{{ route('special_savings.accounts.statement.pdf', $record->special_saving_account_id) }}?from={{ $from }}&to={{ $to }}" class="btn btn-outline-primary ms-2">Print</a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Doc No</th>
                                    <th>Description</th>
                                    <th class="text-end">Principal</th>
                                    <th class="text-end">Interest</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-end">Balance</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($transactions as $row)
                                    <tr>
                                        <td>{{ $row->special_saving_transaction_date }}</td>
                                        <td>{{ $row->special_saving_transaction_type }}</td>
                                        <td>{{ $row->special_saving_transaction_doc_no }}</td>
                                        <td>{{ $row->special_saving_transaction_description }}</td>
                                        <td class="text-end">{{ number_format($row->special_saving_transaction_principal_amount, 2) }}</td>
                                        <td class="text-end">{{ number_format($row->special_saving_transaction_interest_amount, 2) }}</td>
                                        <td class="text-end">{{ number_format($row->special_saving_transaction_amount, 2) }}</td>
                                        <td class="text-end">{{ number_format($row->special_saving_transaction_total_balance_after, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">No statement entries found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <a href="{{ route('special_savings.accounts.show', $record->special_saving_account_id) }}" class="btn btn-outline-secondary">Back to Account</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection