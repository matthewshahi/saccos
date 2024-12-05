@extends('layouts.app')

@section('content')
<div class="container py-4">
@include('mpesa.configs.partials.navigation')
    <h3>Add M-Pesa Configuration</h3>
    <form method="POST" action="{{ route('mpesa_config.store') }}">
        @csrf
        @include('mpesa.configs.form-fields')
        <button type="submit" class="btn btn-primary">Save Configuration</button>
    </form>
</div>
@endsection