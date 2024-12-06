@extends('layouts.app')

@section('content')
<style>
    .text-blackbold {
        font-weight: 700;
    }
    .text-redbold {
        font-weight: 700;
        color: red;
    }
    .text-greenbold {
        font-weight: 700;
        color: green;
    }
    #msg {
        margin-top: 20px;
    }
    #submitbtn {
        background-color: rgb(2, 50, 106);
        color: white;
    }
    @media (max-width: 768px) {
        .form-section {
            flex-direction: column !important;
        }
        .border-right {
            border-right: none !important;
        }
    }
</style>

<div class="container py-4">
    <div class="text-center mb-4">
        <h1>Initiate Payment</h1>
    </div>

    <div class="row justify-content-center">
        <div class="col-12 col-md-6 border-right">
            <p class="text-blackbold">STK Push Method</p>
            <p>
                Enter your M-PESA registered phone number and the amount below, then click <strong>Pay Now</strong>.
                You will receive a payment request on your phone from Safaricom M-PESA.
            </p>
            <p>
                <a href="#" style="color:red; text-decoration:underline;">
                    By proceeding, you agree to the terms and conditions.
                </a>
            </p>

            <!-- Payment Form -->
            <form method="POST" action="{{ route('stkpush.store') }}" id="stkpush-form" class="mt-4">
                @csrf
                <input type="hidden" id="uniq" name="uniq" value="{{ $unicode }}">

                <!-- Phone Number Field -->
                <div class="form-group mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input 
                        type="text" 
                        id="phone" 
                        name="phone" 
                        class="form-control" 
                        placeholder="Enter your phone number (e.g., 2547XXXXXXXX)" 
                        required>
                </div>

                <!-- Amount Field -->
                <div class="form-group mb-3">
                    <label for="amount" class="form-label">Amount (KES)</label>
                    <input 
                        type="number" 
                        id="amount" 
                        name="amount" 
                        class="form-control" 
                        placeholder="Enter the amount" 
                        min="1" 
                        required>
                </div>

                <!-- Submit Button -->
                <button type="submit" id="submitbtn" class="btn btn-primary w-100">Pay Now</button>
            </form>

            <!-- Session Messages -->
            @if (session('error'))
                <div id="msg" class="text-redbold mt-3">
                    {{ session('error') }}
                </div>
            @endif

            @if (session('success'))
                <div id="msg" class="text-greenbold mt-3">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Validation Errors -->
            @if ($errors->any())
                <div id="msg" class="text-redbold mt-3">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <!-- Manual Payment Instructions -->
        <div class="col-12 col-md-6">
            <p class="text-blackbold">Manual Payment Instructions</p>
            <ul>
                <li>Go to Safaricom M-PESA Menu, <span class="text-blackbold">Select Lipa Na Mpesa</span></li>
                <li>Select <span class="text-blackbold">Pay Bill</span></li>
                <li>Enter <span class="text-blackbold">{{ $shortcode }}</span> as the business number and press "OK"</li>
                <li>Select <span class="text-blackbold">Enter Account Number</span></li>
                <li>Enter your Order number <span class="text-redbold">{{ $unicode }}</span> and press "OK"</li>
                <li>Enter <span class="text-blackbold">Your Amount</span> and press "OK"</li>
                <li>Enter Your <span class="text-blackbold">Mpesa Pin</span> and press "OK"</li>
                <li>Confirm all the details are correct and press "OK"</li>
            </ul>
        </div>
    </div>
</div>
@endsection