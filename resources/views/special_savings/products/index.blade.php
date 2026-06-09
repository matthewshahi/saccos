@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Special Saving Products</h1>
        <ul>
            <li><a href="{{ route('special_savings.dashboard') }}">Special Savings</a></li>
            <li>Products</li>
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
                        <div class="card-title mb-0">Products</div>
                        <a href="{{ route('special_savings.products.create') }}" class="btn btn-primary btn-sm">Add Product</a>
                    </div>

                    <form method="GET" class="row mb-3">
                        <div class="col-md-4 form-group mb-2">
                            <input type="text" name="q" value="{{ $q ?? '' }}" class="form-control" placeholder="Search product">
                        </div>
                        <div class="col-md-2 form-group mb-2">
                            <button class="btn btn-secondary btn-block">Search</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>Code</th>
                                    <th>Category</th>
                                    <th>Rate</th>
                                    <th>Method</th>
                                    <th>Cycle</th>
                                    <th>Status</th>
                                    <th style="width:260px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($records as $row)
                                    <tr>
                                        <td>{{ $row->special_saving_product_name }}</td>
                                        <td>{{ $row->special_saving_product_code }}</td>
                                        <td>{{ $row->special_saving_category_name }}</td>
                                        <td>{{ number_format($row->special_saving_product_annual_interest_rate, 4) }}% p.a.</td>
                                        <td>{{ $row->special_saving_product_interest_method }}</td>
                                        <td>{{ $row->special_saving_product_withdrawal_cycle_months }} months</td>
                                        <td>{{ $row->special_saving_product_status }}</td>
                                        <td>
                                            <a href="{{ route('special_savings.products.show', $row->special_saving_product_id) }}" class="btn btn-sm btn-info">View</a>
                                            <a href="{{ route('special_savings.products.edit', $row->special_saving_product_id) }}" class="btn btn-sm btn-primary">Edit</a>
                                            <a href="{{ route('special_savings.rate_tiers.index', $row->special_saving_product_id) }}" class="btn btn-sm btn-outline-secondary">Rates</a>
                                            <form action="{{ route('special_savings.products.toggle', $row->special_saving_product_id) }}" method="POST" class="d-inline">
                                                @csrf
                                                <button class="btn btn-sm btn-outline-dark">Toggle</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="8" class="text-center text-muted">No products found.</td>
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