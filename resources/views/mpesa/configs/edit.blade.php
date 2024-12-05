@extends('layouts.app')

@section('content')
<div class="container py-4">
@include('mpesa.configs.partials.navigation')
    <h3>Edit M-Pesa Configuration</h3>
    <form method="POST" action="{{ route('mpesa_config.update', $config->id) }}">
        @csrf
        @include('mpesa.configs.form-fields', ['config' => $config])
        <button type="submit" class="btn btn-primary">Update Configuration</button>
    </form>
</div>
@endsection