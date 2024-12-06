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
        <!-- Payment Form Section -->
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
            <form id="stkForm" class="mt-4">
                @csrf
                <input type="hidden" id="uniq" name="uniq" value="{{ $unicode }}"/>

                <!-- Phone Number Field -->
                <div class="form-group mb-3">
                    <label for="phone" class="form-label">Phone Number</label>
                    <input 
                        type="text" 
                        id="phone" 
                        name="phone_number" 
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

                <!-- Shortcode Field -->
                <div class="form-group mb-3">
                    <label for="shortcode" class="form-label">Shortcode</label>
                    <input 
                        type="text" 
                        id="shortcode" 
                        name="shortcode" 
                        class="form-control" 
                        value="{{ $shortcode }}" 
                        readonly>
                </div>

                <!-- Submit Button -->
                <button type="button" id="submitbtn" class="btn btn-primary w-100">Pay Now</button>
            </form>

            <!-- Message Area -->
            <div id="msg" class="mt-3"></div>
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

@section('scripts')
<!-- Load jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js" crossorigin="anonymous"></script>

<script>
    $(document).ready(function () {
        let isWorkerStarted = false; // To prevent multiple worker instances

        // Handle STK Push form submission
        $('#submitbtn').click(function () {
            var phone = $('#phone').val();
            var amount = $('#amount').val();
            var data = $('#stkForm').serialize();

            // Validate phone number
            if (!validatePhoneNumber(phone)) {
                $('#msg').css("color", "red").text('Please provide a valid M-Pesa registered phone number (e.g., 2547XXXXXXXX).');
                return;
            }

            // Validate amount
            if (!amount || parseFloat(amount) <= 0) {
                $('#msg').css("color", "red").text('Please enter a valid amount greater than 0.');
                return;
            }

            // Disable button to prevent multiple clicks
            $(this).prop("disabled", true).html("Processing...");

            // Make STK Push Request
            $.ajax({
                type: 'POST',
                url: '{{ route("stkpush.store") }}',
                data: data,
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function (response) {
                    $("#submitbtn").prop("disabled", false).html("Pay Now");
                    $('#msg').css("color", "green").text('Check your phone for a payment confirmation prompt.');

                    if (!isWorkerStarted) {
                        worker(); // Start polling payment status
                        isWorkerStarted = true; // Prevent duplicate workers
                    }
                },
                error: function (xhr) {
                    $("#submitbtn").prop("disabled", false).html("Pay Now");
                    var errorMsg = xhr.responseJSON?.error || "Error occurred while processing. Please try again.";
                    $('#msg').css("color", "red").text(errorMsg);
                }
            });
        });

        // Worker to poll payment status
        function worker() {
            var id = $("#uniq").val(); // Unique transaction code
            var contextPath = '{{ route("stkpush.check", ":id") }}'.replace(':id', id);

            $.ajax({
                url: contextPath,
                type: 'GET',
                success: function (data) {
                    if (data === 'good') {
                        window.location.href = '{{ route("payment.success") }}'; // Redirect on success
                    } else if (data === 'bad') {
                        $('#msg').css("color", "red").text("Payment failed or not completed. Please try again.");
                    }
                },
                error: function () {
                    $('#msg').css("color", "red").text("Unable to check payment status. Please try again.");
                },
                complete: function () {
                    setTimeout(worker, 5000); // Retry every 5 seconds
                }
            });
        }

        // Validate phone number
        function validatePhoneNumber(phone) {
            return phone.length === 12 && phone.startsWith("254") && /^\d+$/.test(phone);
        }
    });
</script>
@endsection