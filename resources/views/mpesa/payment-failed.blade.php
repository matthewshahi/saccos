@extends('layouts.app')

@section('content')
<div class="container text-center mt-5">
    <h1 class="text-danger mb-4">Payment Failed</h1>
    
    <div class="card shadow p-4">
        <div class="card-body">
            <h4 class="mb-3">{{ $message }}</h4>
            <p><strong>Transaction ID:</strong> {{ $transaction_id }}</p>
            <p><strong>Amount Attempted:</strong> {{ number_format($amount, 2) }} KES</p>
            <p><strong>Phone Number:</strong> {{ $phone_number }}</p>
        </div>
    </div>

    <div class="mt-4">
        <a href="{{ route('stkpush.form') }}" class="btn btn-primary">Retry Payment</a>
        <a href="/" class="btn btn-secondary">Go to Dashboard</a>
    </div>
</div>
@endsection