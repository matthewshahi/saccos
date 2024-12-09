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
        <!-- STK Push Form -->
        <div class="col-12 col-md-6 border-right">
            <p class="text-blackbold">STK Push Method</p>
            <p>
                Enter your M-PESA registered phone number and the amount below, then click <strong>Pay Now</strong>.
                You will receive a payment request on your phone from Safaricom M-PESA. Please confirm the details and enter your PIN.
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
        </div>

        <!-- Manual Payment Instructions -->
        <div class="col-12 col-md-6">
            <p class="text-blackbold">Manual Payment Instructions</p>
            <div class="card shadow-sm p-3">
                <p class="mb-2">Follow the steps below to complete your payment manually:</p>
                <ul>
                    <li>
                        Go to the <span class="text-blackbold">Safaricom M-PESA Menu</span> on your phone, and select
                        <span class="text-blackbold">Lipa Na Mpesa</span>.
                    </li>
                    <li>Select <span class="text-blackbold">Pay Bill</span>.</li>
                    <li>
                        Enter <span class="text-blackbold">{{ $shortcode }}</span> as the <span class="text-greenbold">Business Number</span> and press <span class="text-blackbold">"OK"</span>.
                    </li>
                    <li>
                        Select <span class="text-blackbold">Enter Account Number</span>.
                    </li>
                    <li>
                        Enter your <span class="text-redbold">Order Number: {{ $unicode }}</span> and press <span class="text-blackbold">"OK"</span>.
                    </li>
                    <li>
                        Enter the <span class="text-blackbold">Amount</span> you wish to pay and press <span class="text-blackbold">"OK"</span>.
                    </li>
                    <li>
                        Enter your <span class="text-blackbold">Mpesa PIN</span> and press <span class="text-blackbold">"OK"</span>.
                    </li>
                    <li>
                        Confirm all the details displayed are correct and press <span class="text-blackbold">"OK"</span>.
                    </li>
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="submittedModal" tabindex="-1" aria-labelledby="submittedModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="submittedModalLabel">Processing Payment</h5>
            </div>
            <div class="modal-body text-center">
                <p id="responseMessage" class="text-greenbold">Please check your handset and enter your M-PESA PIN to complete the payment.</p>
                <p id="responseTime" class="text-blackbold">Time Remaining: <span id="countdown">60</span> seconds</p>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.getElementById('stkpush-form');
        const modal = new bootstrap.Modal(document.getElementById('submittedModal'));
        const countdownElement = document.getElementById('countdown');
        const responseMessage = document.getElementById('responseMessage');
        let countdown = 60;

        form.addEventListener('submit', function (e) {
            e.preventDefault(); // Prevent default form submission
            modal.show();
            startCountdown();
        });

        function startCountdown() {
            const timer = setInterval(async () => {
                countdown--;
                countdownElement.textContent = countdown;

                if (countdown <= 0) {
                    clearInterval(timer);
                    const paymentStatus = await checkPaymentStatus();
                    handlePaymentResponse(paymentStatus);
                }
            }, 1000);
        }

        async function checkPaymentStatus() {
            try {
                const response = await fetch('{{ route("payment.status") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ uniq: document.getElementById('uniq').value })
                });
                return await response.json();
            } catch (error) {
                console.error('Error checking payment status:', error);
                return { status: 'error' };
            }
        }

        function handlePaymentResponse(response) {
            if (response.status === 'success') {
                window.location.href = '{{ route("payment.success") }}';
            } else {
                responseMessage.textContent = 'An error occurred while processing your request. Please try again.';
                responseMessage.classList.replace('text-greenbold', 'text-redbold');
                countdownElement.parentElement.remove(); // Remove countdown display
            }
        }
    });
</script>
@endsection