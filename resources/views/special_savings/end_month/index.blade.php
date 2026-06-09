@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Special Savings End Month</h1>
        <ul>
            <li><a href="{{ route('special_savings.dashboard') }}">Special Savings</a></li>
            <li>End Month</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @include('special_savings.partials.alerts')
    @include('special_savings.partials.nav')

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">End Month Processing</div>

                    <form method="POST" action="{{ route('special_savings.end_month.process') }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-4 form-group mb-3">
                                <label>Period</label>
                                <input type="text" name="period" value="{{ old('period', $period ?? date('Ym')) }}" class="form-control" placeholder="YYYYMM" required>
                            </div>

                            <div class="col-md-4 form-group mb-3">
                                <label>Product</label>
                                <select name="product_id" class="form-control">
                                    <option value="">All Monthly Products</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->special_saving_product_id }}" {{ old('product_id') == $product->special_saving_product_id ? 'selected' : '' }}>
                                            {{ $product->special_saving_product_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-4 form-group mb-3">
                                <label>Post Immediately</label>
                                <select name="post_immediately" class="form-control">
                                    <option value="Y" {{ old('post_immediately', 'Y') == 'Y' ? 'selected' : '' }}>Yes</option>
                                    <option value="N" {{ old('post_immediately', 'Y') == 'N' ? 'selected' : '' }}>No</option>
                                </select>
                            </div>

                            <div class="col-md-4 form-group mb-3">
                                <label>Force Processing</label>
                                <select name="force" class="form-control">
                                    <option value="N" {{ old('force', 'N') == 'N' ? 'selected' : '' }}>No</option>
                                    <option value="Y" {{ old('force', 'N') == 'Y' ? 'selected' : '' }}>Yes</option>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <button class="btn btn-primary" onclick="return confirm('Process special savings end month?')">Process End Month</button>
                                <a href="{{ route('special_savings.interest.index') }}" class="btn btn-outline-secondary">Interest Runs</a>
                            </div>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection