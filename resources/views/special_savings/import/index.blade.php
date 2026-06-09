@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Import Special Savings</h1>
        <ul>
            <li><a href="{{ route('special_savings.dashboard') }}">Special Savings</a></li>
            <li>Import</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @include('special_savings.partials.alerts')
    @include('special_savings.partials.nav')

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">Upload Contributions</div>

                    <form method="POST" action="{{ route('special_savings.import.preview') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="row">
                            <div class="col-md-4 form-group mb-3">
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

                            <div class="col-md-4 form-group mb-3">
                                <label>Transaction Date</label>
                                <input type="date" name="transaction_date" value="{{ old('transaction_date', date('Y-m-d')) }}" class="form-control" required>
                            </div>

                            <div class="col-md-4 form-group mb-3">
                                <label>CSV File</label>
                                <input type="file" name="csv_file" class="form-control" required>
                            </div>

                            <div class="col-md-12">
                                <button class="btn btn-primary">Preview Import</button>
                                <a href="{{ route('special_savings.import.sample') }}" class="btn btn-outline-secondary">Download Sample</a>
                            </div>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection