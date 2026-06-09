@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Rate Tiers</h1>
        <ul>
            <li><a href="{{ route('special_savings.products.index') }}">Products</a></li>
            <li>{{ $product->special_saving_product_name }}</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @include('special_savings.partials.alerts')

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="card-title mb-0">Amount-Based Rates</div>
                        <a href="{{ route('special_savings.rate_tiers.create', $product->special_saving_product_id) }}" class="btn btn-primary btn-sm">Add Tier</a>
                    </div>

                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Minimum Amount</th>
                                <th>Maximum Amount</th>
                                <th>Annual Rate</th>
                                <th>Monthly Rate</th>
                                <th>Status</th>
                                <th style="width:160px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($records as $row)
                                <tr>
                                    <td>{{ number_format($row->special_saving_rate_tier_min_amount, 2) }}</td>
                                    <td>{{ $row->special_saving_rate_tier_max_amount ? number_format($row->special_saving_rate_tier_max_amount, 2) : 'Above' }}</td>
                                    <td>{{ number_format($row->special_saving_rate_tier_annual_rate, 6) }}%</td>
                                    <td>{{ number_format($row->special_saving_rate_tier_monthly_rate, 6) }}%</td>
                                    <td>{{ $row->special_saving_rate_tier_status }}</td>
                                    <td>
                                        <a href="{{ route('special_savings.rate_tiers.edit', $row->special_saving_rate_tier_id) }}" class="btn btn-sm btn-primary">Edit</a>
                                        <form method="POST" action="{{ route('special_savings.rate_tiers.delete', $row->special_saving_rate_tier_id) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-danger" onclick="return confirm('Delete this tier?')">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="text-center text-muted">No rate tiers found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>

                    {{ $records->links() }}

                    <a href="{{ route('special_savings.products.show', $product->special_saving_product_id) }}" class="btn btn-outline-secondary">Back to Product</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection