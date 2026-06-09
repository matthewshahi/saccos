@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Interest Run Items</h1>
        <ul>
            <li><a href="{{ route('special_savings.interest.show', $record->special_saving_interest_run_id) }}">Interest Run</a></li>
            <li>Items</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @include('special_savings.partials.alerts')

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">Run Items - {{ $record->special_saving_interest_run_period }}</div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Member</th>
                                    <th>Account</th>
                                    <th class="text-end">Opening</th>
                                    <th class="text-end">Closing</th>
                                    <th class="text-end">Minimum</th>
                                    <th class="text-end">Qualifying</th>
                                    <th class="text-end">Rate</th>
                                    <th class="text-end">Interest</th>
                                    <th>Qualified</th>
                                    <th>Status</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($items as $row)
                                    <tr>
                                        <td>{{ $row->member_name }} <small class="text-muted d-block">{{ $row->member_sacco_id }}</small></td>
                                        <td>{{ $row->special_saving_account_number }}</td>
                                        <td class="text-end">{{ number_format($row->special_saving_interest_item_opening_balance, 2) }}</td>
                                        <td class="text-end">{{ number_format($row->special_saving_interest_item_closing_balance, 2) }}</td>
                                        <td class="text-end">{{ number_format($row->special_saving_interest_item_minimum_balance, 2) }}</td>
                                        <td class="text-end">{{ number_format($row->special_saving_interest_item_qualifying_balance, 2) }}</td>
                                        <td class="text-end">{{ number_format($row->special_saving_interest_item_monthly_rate, 6) }}%</td>
                                        <td class="text-end">{{ number_format($row->special_saving_interest_item_interest_amount, 2) }}</td>
                                        <td>{{ $row->special_saving_interest_item_qualified }}</td>
                                        <td>{{ $row->special_saving_interest_item_status }}</td>
                                        <td>{{ $row->special_saving_interest_item_skip_reason }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="text-center text-muted">No items found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    {{ $items->links() }}

                    <a href="{{ route('special_savings.interest.show', $record->special_saving_interest_run_id) }}" class="btn btn-outline-secondary">Back</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection