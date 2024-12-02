@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="col-md-8 mx-auto">
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <div class="card-title mb-3 text-center"><h4>Initiate M-Pesa Payment</h4></div>
                <p class="text-muted text-center">
                    Please enter your phone number and the amount to pay. After submission, you will receive an M-Pesa prompt on your phone to complete the payment.
                </p>
                <form method="POST" action="{{ route('mpesa_stkpush') }}">
                    @csrf
                    <div class="row g-3">
                        <!-- Phone Number Input -->
                        <div class="col-12">
                            <label for="phone_number" class="form-label">Phone Number</label>
                            <input 
                                type="text" 
                                id="phone_number" 
                                name="phone_number" 
                                class="form-control @error('phone_number') is-invalid @enderror" 
                                placeholder="2547XXXXXXXX" 
                                value="{{ old('phone_number') }}" 
                                required>
                            @error('phone_number')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Amount Input -->
                        <div class="col-12">
                            <label for="amount" class="form-label">Amount</label>
                            <input 
                                type="number" 
                                id="amount" 
                                name="amount" 
                                class="form-control @error('amount') is-invalid @enderror" 
                                placeholder="Enter the amount" 
                                value="{{ old('amount') }}" 
                                required>
                            @error('amount')
                                <div class="invalid-feedback">{{ $message }}</div>
                            @enderror
                        </div>

                        <!-- Submit Button -->
                        <div class="col-12 d-grid">
                            <button type="submit" class="btn btn-primary">
                                Submit Payment
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Payment Instructions -->
        <div class="alert alert-info mt-4 text-center">
            <strong>What to Expect:</strong>
            <ul class="list-unstyled mt-2">
                <li>1. You will receive an M-Pesa prompt on your phone.</li>
                <li>2. Enter your M-Pesa PIN to complete the payment.</li>
                <li>3. Once completed, you will receive a confirmation message.</li>
            </ul>
        </div>
    </div>
</div>
@endsection