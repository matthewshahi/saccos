@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Product Details</h1>
        <ul>
            <li><a href="{{ route('special_savings.products.index') }}">Products</a></li>
            <li>{{ $record->special_saving_product_name }}</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @include('special_savings.partials.alerts')
    @include('special_savings.partials.nav')

    <div class="row">
        <div class="col-md-8">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">{{ $record->special_saving_product_name }}</div>

                    <table class="table table-bordered table-sm">
                        <tr><th>Code</th><td>{{ $record->special_saving_product_code }}</td></tr>
                        <tr><th>Category</th><td>{{ $record->special_saving_category_name }}</td></tr>
                        <tr><th>Annual Rate</th><td>{{ number_format($record->special_saving_product_annual_interest_rate, 6) }}%</td></tr>
                        <tr><th>Monthly Rate</th><td>{{ number_format($record->special_saving_product_monthly_interest_rate, 6) }}%</td></tr>
                        <tr><th>Interest Method</th><td>{{ $record->special_saving_product_interest_method }}</td></tr>
                        <tr><th>Posting Frequency</th><td>{{ $record->special_saving_product_interest_posting_frequency }}</td></tr>
                        <tr><th>Withdrawal Cycle</th><td>{{ $record->special_saving_product_withdrawal_cycle_months }} months</td></tr>
                        <tr><th>Status</th><td>{{ $record->special_saving_product_status }}</td></tr>
                    </table>

                    <a href="{{ route('special_savings.products.edit', $record->special_saving_product_id) }}" class="btn btn-primary">Edit Product</a>
                    <a href="{{ route('special_savings.rate_tiers.index', $record->special_saving_product_id) }}" class="btn btn-outline-secondary">Rate Tiers</a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">Rate Tiers</div>

                    <table class="table table-sm table-bordered">
                        <thead>
                            <tr>
                                <th>Min</th>
                                <th>Max</th>
                                <th>Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($rate_tiers as $tier)
                                <tr>
                                    <td>{{ number_format($tier->special_saving_rate_tier_min_amount, 2) }}</td>
                                    <td>{{ $tier->special_saving_rate_tier_max_amount ? number_format($tier->special_saving_rate_tier_max_amount, 2) : 'Above' }}</td>
                                    <td>{{ number_format($tier->special_saving_rate_tier_annual_rate, 4) }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-center text-muted">No rate tiers.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection