@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Interest Preview</h1>
        <ul>
            <li><a href="{{ route('special_savings.interest.index') }}">Interest Runs</a></li>
            <li>Preview</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @include('special_savings.partials.alerts')

    @php
        $qualified = collect($items)->where('special_saving_interest_item_qualified', 'Y');
        $skipped = collect($items)->where('special_saving_interest_item_qualified', 'N');
    @endphp

    <div class="row">
        <div class="col-md-3">
            <div class="card mb-4"><div class="card-body">
                <p class="text-muted mb-1">Total Accounts</p>
                <h4>{{ number_format(count($items)) }}</h4>
            </div></div>
        </div>

        <div class="col-md-3">
            <div class="card mb-4"><div class="card-body">
                <p class="text-muted mb-1">Qualified</p>
                <h4>{{ number_format($qualified->count()) }}</h4>
            </div></div>
        </div>

        <div class="col-md-3">
            <div class="card mb-4"><div class="card-body">
                <p class="text-muted mb-1">Skipped</p>
                <h4>{{ number_format($skipped->count()) }}</h4>
            </div></div>
        </div>

        <div class="col-md-3">
            <div class="card mb-4"><div class="card-body">
                <p class="text-muted mb-1">Total Interest</p>
                <h4>{{ number_format($qualified->sum('special_saving_interest_item_interest_amount'), 2) }}</h4>
            </div></div>
        </div>

        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">{{ $product->special_saving_product_name }} Interest Preview</div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Account ID</th>
                                    <th>Member ID</th>
                                    <th>Period</th>
                                    <th class="text-end">Opening</th>
                                    <th class="text-end">Closing</th>
                                    <th class="text-end">Minimum</th>
                                    <th class="text-end">Qualifying</th>
                                    <th class="text-end">Rate</th>
                                    <th class="text-end">Interest</th>
                                    <th>Qualified</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($items as $row)
                                    <tr>
                                        <td>{{ $row['special_saving_interest_item_account_id'] }}</td>
                                        <td>{{ $row['special_saving_interest_item_member_id'] }}</td>
                                        <td>{{ $row['special_saving_interest_item_period'] }}</td>
                                        <td class="text-end">{{ number_format($row['special_saving_interest_item_opening_balance'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['special_saving_interest_item_closing_balance'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['special_saving_interest_item_minimum_balance'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['special_saving_interest_item_qualifying_balance'], 2) }}</td>
                                        <td class="text-end">{{ number_format($row['special_saving_interest_item_monthly_rate'], 6) }}%</td>
                                        <td class="text-end">{{ number_format($row['special_saving_interest_item_interest_amount'], 2) }}</td>
                                        <td>{{ $row['special_saving_interest_item_qualified'] }}</td>
                                        <td>{{ $row['special_saving_interest_item_skip_reason'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="text-center text-muted">No accounts found for this product.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <form method="POST" action="{{ route('special_savings.interest.process') }}" class="d-inline">
                        @csrf
                        <input type="hidden" name="product_id" value="{{ $product->special_saving_product_id }}">
                        <input type="hidden" name="period" value="{{ $items[0]['special_saving_interest_item_period'] ?? date('Ym') }}">
                        <button class="btn btn-primary" onclick="return confirm('Create this interest run?')">Create Interest Run</button>
                    </form>

                    <a href="{{ route('special_savings.interest.create') }}" class="btn btn-outline-secondary">Back</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection