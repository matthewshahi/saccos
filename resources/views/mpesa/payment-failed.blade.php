@extends('layouts.app')

@section('content')
<div class="container text-center mt-5">
    <h1 class="text-danger mb-4">Payment Failed</h1>
    
    <div class="alert alert-danger">
        {{ $message }}
    </div>

    <div class="mt-4">
        <a href="{{ route('stkpush.form') }}" class="btn btn-primary">Try Again</a>
        <a href="/" class="btn btn-secondary">Go to Dashboard</a>
    </div>
</div>
@endsection