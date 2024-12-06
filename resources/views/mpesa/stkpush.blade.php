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
        <div class="col-12 col-md-6 border-right">
            <p class="text-blackbold">STK Push Method</p>
            <p>
                Enter your M-PESA registered phone number and the amount below, then click <strong>Pay Now</strong>.
                You will receive a payment request on your phone from Safaricom M-PESA.
            </p>
            <p>
                <a href="" target="_blank" style="color:red; text-decoration:underline;">
                    By proceeding, you agree to the terms and conditions.
                </a>
            </p>

            <!-- Payment Form -->
            <form method="POST" action="{{ route('stkpush.store') }}" id="form" class="mt-4">
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
<script src="https://code.jquery.com/jquery-3.6.0.min.js" integrity="sha384-KyZXEAg3QhqLMpG8r+8fhAXLRlfA4IwUQlbNf5Y5iwPCSOm3tyYPs/lLnT86PlXg" crossorigin="anonymous"></script>
@section('scripts')
<script>
    $(document).ready(function () {
        worker(); // Initiates periodic checking

        $('#submitbtn').click(function () {
            var phone = $('#phone').val();
            var amount = $('#amount').val();
            var data = $('#form').serialize();

            // Input Validation
            if (!validatePhoneNumber(phone)) {
                $('#msg').css("color", "red").text('Please provide a valid M-Pesa registered phone number (e.g., 2547XXXXXXXX).');
                return;
            }

            if (amount.length === 0 || parseFloat(amount) <= 0) {
                $('#msg').css("color", "red").text('Please enter a valid amount greater than 0.');
                return;
            }

            // Disable button to prevent multiple clicks
            $(this).prop("disabled", true).html("Processing...");

            // STK Push Request
            $.ajax({
                type: 'POST',
                url: '{{ route("stkpush.store") }}', // Dynamic route
                data: data, // Our data object
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function (data, status, xhr) {
                    $("#submitbtn").prop("disabled", false).html("Pay Now");
                    $('#msg').css("color", "green").text('Check your phone for a payment confirmation prompt.');
                },
                error: function (xhr, status, error) {
                    $("#submitbtn").prop("disabled", false).html("Pay Now");
                    $('#msg').css("color", "red").text("Error occurred while processing. Please try again.");
                }
            });
        });
    });

    // Function to periodically check payment status
    function worker() {
        var id = $("#uniq").val(); // Unique document code
        var contextPath = '{{ route("stkpush.check", ":id") }}'.replace(':id', id); // Dynamic URL

        $.ajax({
            url: contextPath,
            type: 'GET',
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            },
            success: function (data) {
                console.log(data);

                if (data === 'good') {
                    // Redirect to a success page
                    window.location.href = '{{ route("payment.success") }}';
                } else if (data === 'bad') {
                    // Display a message if payment failed
                    $('#msg').css("color", "red").text("Payment failed or not completed. Please try again.");
                }
            },
            complete: function () {
                // Stop polling once payment is successful
                if ($('#msg').text() !== "Payment failed or not completed. Please try again.") {
                    setTimeout(worker, 5000); // Retry every 5 seconds
                }
            }
        });
    }

    // Function to validate phone numbers
    function validatePhoneNumber(phone) {
        return phone.length === 12 && phone.startsWith("254") && /^\d+$/.test(phone);
    }
</script>
@endsection
 