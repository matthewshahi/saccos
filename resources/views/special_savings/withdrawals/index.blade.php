@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Special Saving Withdrawals</h1>
        <ul>
            <li><a href="{{ route('special_savings.dashboard') }}">Special Savings</a></li>
            <li>Withdrawals</li>
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
                        <div class="card-title mb-0">Withdrawal Requests</div>
                        <a href="{{ route('special_savings.withdrawals.create') }}" class="btn btn-primary btn-sm">New Withdrawal</a>
                    </div>

                    <form method="GET" class="row mb-3">
                        <div class="col-md-4 form-group mb-2">
                            <input type="text" name="q" value="{{ $q ?? '' }}" class="form-control" placeholder="Search withdrawal">
                        </div>

                        <div class="col-md-3 form-group mb-2">
                            <select name="status" class="form-control">
                                <option value="">All Statuses</option>
                                @foreach(['Pending', 'Approved', 'Paid', 'Rejected', 'Cancelled', 'Reversed'] as $item)
                                    <option value="{{ $item }}" {{ ($status ?? '') == $item ? 'selected' : '' }}>{{ $item }}</option>
                                @endforeach
                            </select>
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
                                    <th class="text-end">Principal</th>
                                    <th class="text-end">Interest</th>
                                    <th>Early</th>
                                    <th>Status</th>
                                    <th style="width:130px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($records as $row)
                                    <tr>
                                        <td>{{ $row->special_saving_withdrawal_request_date }}</td>
                                        <td>{{ $row->member_name }} <small class="text-muted d-block">{{ $row->member_sacco_id }}</small></td>
                                        <td>{{ $row->special_saving_account_number }}</td>
                                        <td>{{ $row->special_saving_product_name }}</td>
                                        <td class="text-end">{{ number_format($row->special_saving_withdrawal_principal_amount, 2) }}</td>
                                        <td class="text-end">{{ number_format($row->special_saving_withdrawal_interest_amount, 2) }}</td>
                                        <td>{{ $row->special_saving_withdrawal_is_early }}</td>
                                        <td>{{ $row->special_saving_withdrawal_status }}</td>
                                        <td>
                                            <a href="{{ route('special_savings.withdrawals.show', $row->special_saving_withdrawal_id) }}" class="btn btn-sm btn-info">View</a>
                                            <a href="{{ route('special_savings.withdrawals.voucher', $row->special_saving_withdrawal_id) }}" class="btn btn-sm btn-outline-secondary">Voucher</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">No withdrawal requests found.</td>
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