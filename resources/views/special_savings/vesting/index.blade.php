@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Interest Vesting</h1>
        <ul>
            <li><a href="{{ route('special_savings.dashboard') }}">Special Savings</a></li>
            <li>Vesting</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @include('special_savings.partials.alerts')
    @include('special_savings.partials.nav')

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">Vesting Details</div>

                    <form method="POST" action="{{ route('special_savings.vesting.preview') }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-4 form-group mb-3">
                                <label>Process Date</label>
                                <input type="date" name="process_date" value="{{ old('process_date', $process_date ?? date('Y-m-d')) }}" class="form-control" required>
                            </div>

                            <div class="col-md-4 form-group mb-3">
                                <label>Product</label>
                                <select name="product_id" class="form-control">
                                    <option value="">All Products</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->special_saving_product_id }}" {{ old('product_id') == $product->special_saving_product_id ? 'selected' : '' }}>
                                            {{ $product->special_saving_product_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 form-group mb-3 d-flex align-items-end">
                                <button class="btn btn-secondary">Preview Vesting</button>
                            </div>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('special_savings.vesting.process') }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-4 form-group mb-3">
                                <label>Process Date</label>
                                <input type="date" name="process_date" value="{{ old('process_date', $process_date ?? date('Y-m-d')) }}" class="form-control" required>
                            </div>

                            <div class="col-md-4 form-group mb-3">
                                <label>Product</label>
                                <select name="product_id" class="form-control">
                                    <option value="">All Products</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->special_saving_product_id }}">
                                            {{ $product->special_saving_product_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 form-group mb-3 d-flex align-items-end">
                                <button class="btn btn-primary" onclick="return confirm('Process interest vesting?')">Process Vesting</button>
                            </div>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection