@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Vesting Preview</h1>
        <ul>
            <li><a href="{{ route('special_savings.vesting.index') }}">Vesting</a></li>
            <li>Preview</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @php
        $total = collect($items)->sum('special_saving_account_accrued_interest_balance');
    @endphp

    <div class="row">
        <div class="col-md-3">
            <div class="card mb-4"><div class="card-body">
                <p class="text-muted mb-1">Accounts Due</p>
                <h4>{{ number_format(count($items)) }}</h4>
            </div></div>
        </div>

        <div class="col-md-3">
            <div class="card mb-4"><div class="card-body">
                <p class="text-muted mb-1">Total to Vest</p>
                <h4>{{ number_format($total, 2) }}</h4>
            </div></div>
        </div>

        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">Accounts Due for Vesting</div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Product</th>
                                    <th>Account</th>
                                    <th>Next Free Withdrawal</th>
                                    <th class="text-end">Accrued Interest</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($items as $row)
                                    <tr>
                                        <td>{{ $row->member_name }} <small class="text-muted d-block">{{ $row->member_sacco_id }}</small></td>
                                        <td>{{ $row->special_saving_product_name }}</td>
                                        <td>{{ $row->special_saving_account_number }}</td>
                                        <td>{{ $row->special_saving_account_next_free_withdrawal_date }}</td>
                                        <td class="text-end">{{ number_format($row->special_saving_account_accrued_interest_balance, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No accounts are due for vesting.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <form method="POST" action="{{ route('special_savings.vesting.process') }}" class="d-inline">
                        @csrf
                        <input type="hidden" name="process_date" value="{{ $data['process_date'] }}">
                        <input type="hidden" name="product_id" value="{{ $data['product_id'] ?? '' }}">
                        <button class="btn btn-primary" onclick="return confirm('Process this vesting batch?')">Process Vesting</button>
                    </form>

                    <a href="{{ route('special_savings.vesting.index') }}" class="btn btn-outline-secondary">Back</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection