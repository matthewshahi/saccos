@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>New Interest Run</h1>
        <ul>
            <li><a href="{{ route('special_savings.interest.index') }}">Interest Runs</a></li>
            <li>Create</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @include('special_savings.partials.alerts')

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">Interest Run Details</div>

                    <form method="POST" action="{{ route('special_savings.interest.preview') }}" class="mb-3">
                        @csrf

                        <div class="row">
                            <div class="col-md-5 form-group mb-3">
                                <label>Product</label>
                                <select name="product_id" class="form-control" required>
                                    <option value="">Select product</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->special_saving_product_id }}" {{ old('product_id') == $product->special_saving_product_id ? 'selected' : '' }}>
                                            {{ $product->special_saving_product_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3 form-group mb-3">
                                <label>Period</label>
                                <input type="text" name="period" value="{{ old('period', $period ?? date('Ym')) }}" class="form-control" placeholder="YYYYMM" required>
                            </div>

                            <div class="col-md-4 form-group mb-3 d-flex align-items-end">
                                <button class="btn btn-secondary">Preview Interest</button>
                            </div>
                        </div>
                    </form>

                    <form method="POST" action="{{ route('special_savings.interest.process') }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-5 form-group mb-3">
                                <label>Product</label>
                                <select name="product_id" class="form-control" required>
                                    <option value="">Select product</option>
                                    @foreach($products as $product)
                                        <option value="{{ $product->special_saving_product_id }}">
                                            {{ $product->special_saving_product_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-3 form-group mb-3">
                                <label>Period</label>
                                <input type="text" name="period" value="{{ $period ?? date('Ym') }}" class="form-control" placeholder="YYYYMM" required>
                            </div>

                            <div class="col-md-4 form-group mb-3 d-flex align-items-end">
                                <button class="btn btn-primary" onclick="return confirm('Create this interest run?')">Create Interest Run</button>
                                <a href="{{ route('special_savings.interest.index') }}" class="btn btn-outline-secondary ms-2">Cancel</a>
                            </div>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection