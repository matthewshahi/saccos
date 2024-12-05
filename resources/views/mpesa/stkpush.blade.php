@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-body">
                <div class="card-title mb-3">STK Push Payment</div>
                <!-- Instructions -->
                <p class="text-muted">
                    Please fill in the required fields below to initiate an STK Push request. Ensure the phone number is in the 
                    <strong>international format</strong> (e.g., <code>2547XXXXXXXX</code>). An STK Push prompt will be sent to the phone number provided.
                </p>
                <p class="text-muted">
                    <strong>Note:</strong> Follow the instructions on your phone to complete the transaction.
                </p>
                
                <!-- Form -->
                <form method="POST" action="{{ route('stkpush.store') }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label for="phone_number">Phone Number <span class="text-danger">*</span></label>
                            <input 
                                class="form-control" 
                                id="phone_number" 
                                name="phone_number" 
                                type="text" 
                                placeholder="Enter phone number in 2547XXXXXXXX format" 
                                value="{{ old('phone_number') }}" 
                                required>
                            @error('phone_number')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label for="amount">Amount (KES) <span class="text-danger">*</span></label>
                            <input 
                                class="form-control" 
                                id="amount" 
                                name="amount" 
                                type="number" 
                                placeholder="Enter amount" 
                                min="1" 
                                value="{{ old('amount') }}" 
                                required>
                            @error('amount')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label for="shortcode">Shortcode <span class="text-danger">*</span></label>
                            <select class="form-control" id="shortcode" name="shortcode" required>
                                <option value="" selected disabled>-- Select Shortcode --</option>
                                @foreach($configs as $config)
                                    @if($config->api_type === 'mpesa_express')
                                        <option value="{{ $config->shortcode }}" {{ old('shortcode') == $config->shortcode ? 'selected' : '' }}>
                                            {{ $config->shortcode }}
                                        </option>
                                    @endif
                                @endforeach
                            </select>
                            @error('shortcode')
                                <span class="text-danger">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary w-100">Send STK Push</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection