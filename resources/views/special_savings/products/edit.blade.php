@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Edit Product</h1>
        <ul>
            <li><a href="{{ route('special_savings.products.index') }}">Products</a></li>
            <li>Edit</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @include('special_savings.partials.alerts')

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">Product Details</div>

                    <form method="POST" action="{{ route('special_savings.products.update', $record->special_saving_product_id) }}">
                        @csrf
                        @include('special_savings.products.form', ['record' => $record])
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection