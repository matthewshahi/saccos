@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="col-md-10 mx-auto">
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h4 class="mb-0">Add M-Pesa Configuration</h4>
                <a href="{{ route('mpesa_config.index') }}" class="btn btn-primary btn-sm">Back to List</a>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('mpesa_config.store') }}">
                    @csrf
                    <div class="row">
                        @include('mpesa.configs.form-fields')
                        <div class="col-md-12 d-grid">
                            <button class="btn btn-success">Save Configuration</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection