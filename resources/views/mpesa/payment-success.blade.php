@extends('layouts.app')

@section('content')
<div class="container text-center mt-5">
    <h1 class="text-success mb-4">Payment Successful</h1>
    
    <div class="card shadow p-4">
        <div class="card-body">
            <h4 class="mb-3">{{ $message }}</h4>
            <p><strong>Transaction ID:</strong> {{ $transaction_id }}</p>
            <p><strong>Amount Paid:</strong> {{ number_format((float) $amount, 2) }} KES</p>
            <p><strong>Phone Number:</strong> {{ $phone_number }}</p>
        </div>
    </div>

    <!-- <div class="mt-4">
        <a href="{{ route('stkpush.form') }}" class="btn btn-primary">Make Another Payment</a>
        <a href="/" class="btn btn-secondary">Go to Dashboard</a>
    </div> -->
</div>
@endsection