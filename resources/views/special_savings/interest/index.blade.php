@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Special Saving Interest</h1>
        <ul>
            <li><a href="{{ route('special_savings.dashboard') }}">Special Savings</a></li>
            <li>Interest Runs</li>
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
                        <div class="card-title mb-0">Interest Runs</div>
                        <a href="{{ route('special_savings.interest.create') }}" class="btn btn-primary btn-sm">New Interest Run</a>
                    </div>

                    <form method="GET" class="row mb-3">
                        <div class="col-md-3 form-group mb-2">
                            <label>Period</label>
                            <input type="text" name="period" value="{{ $period ?? '' }}" class="form-control" placeholder="YYYYMM">
                        </div>

                        <div class="col-md-4 form-group mb-2">
                            <label>Product</label>
                            <select name="product_id" class="form-control">
                                <option value="">All Products</option>
                                @foreach($products as $product)
                                    <option value="{{ $product->special_saving_product_id }}" {{ ($product_id ?? '') == $product->special_saving_product_id ? 'selected' : '' }}>
                                        {{ $product->special_saving_product_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2 form-group mb-2 d-flex align-items-end">
                            <button class="btn btn-secondary btn-block">Filter</button>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Period</th>
                                    <th>Product</th>
                                    <th>Method</th>
                                    <th class="text-end">Accounts</th>
                                    <th class="text-end">Qualified</th>
                                    <th class="text-end">Skipped</th>
                                    <th class="text-end">Qualifying Balance</th>
                                    <th class="text-end">Interest</th>
                                    <th>Status</th>
                                    <th style="width:160px;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($records as $row)
                                    <tr>
                                        <td>{{ $row->special_saving_interest_run_period }}</td>
                                        <td>{{ $row->special_saving_product_name }}</td>
                                        <td>{{ $row->special_saving_interest_run_method }}</td>
                                        <td class="text-end">{{ number_format($row->special_saving_interest_run_total_accounts) }}</td>
                                        <td class="text-end">{{ number_format($row->special_saving_interest_run_qualified_accounts) }}</td>
                                        <td class="text-end">{{ number_format($row->special_saving_interest_run_skipped_accounts) }}</td>
                                        <td class="text-end">{{ number_format($row->special_saving_interest_run_total_qualifying_balance, 2) }}</td>
                                        <td class="text-end">{{ number_format($row->special_saving_interest_run_total_interest, 2) }}</td>
                                        <td>{{ $row->special_saving_interest_run_status }}</td>
                                        <td>
                                            <a href="{{ route('special_savings.interest.show', $row->special_saving_interest_run_id) }}" class="btn btn-sm btn-info">View</a>
                                            <a href="{{ route('special_savings.interest.items', $row->special_saving_interest_run_id) }}" class="btn btn-sm btn-outline-secondary">Items</a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="10" class="text-center text-muted">No interest runs found.</td>
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