@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Add Rate Tier</h1>
        <ul>
            <li><a href="{{ route('special_savings.rate_tiers.index', $product->special_saving_product_id) }}">Rate Tiers</a></li>
            <li>Create</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @include('special_savings.partials.alerts')

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">{{ $product->special_saving_product_name }}</div>

                    <form method="POST" action="{{ route('special_savings.rate_tiers.store', $product->special_saving_product_id) }}">
                        @csrf
                        @include('special_savings.rate_tiers.form', ['record' => null])
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection