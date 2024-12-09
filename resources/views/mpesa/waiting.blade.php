@extends('layouts.app')

@section('content')
<div class="container py-4 text-center">
    <h1>Processing Payment</h1>
    <p>Please check your handset and enter your M-PESA PIN to complete the payment.</p>
    <p>Time Remaining: <span id="countdown">60</span> seconds</p>
</div>

<!-- Modal -->
<div class="modal fade show" id="waitingModal" tabindex="-1" aria-labelledby="waitingModalLabel" aria-hidden="true" style="display: block;">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="waitingModalLabel">Waiting for Payment</h5>
            </div>
            <div class="modal-body text-center">
                <p>Please confirm the payment request on your phone.</p>
                <p>Time Remaining: <span id="countdown">60</span> seconds</p>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const countdownElement = document.getElementById('countdown');
        let countdown = 60;

        const timer = setInterval(async () => {
            countdown--;
            countdownElement.textContent = countdown;

            if (countdown <= 0) {
                clearInterval(timer);
                checkPaymentStatus();
            }
        }, 1000);

        async function checkPaymentStatus() {
            try {
                const response = await fetch('{{ route("payment.status") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    },
                    body: JSON.stringify({
                        checkoutRequestId: '{{ $checkoutRequestId }}'
                    }),
                });

                const data = await response.json();

                if (data.status === 'success') {
                    window.location.href = '{{ route("payment.success") }}';
                } else {
                    window.location.href = '{{ route("payment.failed") }}';
                }
            } catch (error) {
                console.error('Error checking payment status:', error);
                window.location.href = '{{ route("payment.failed") }}';
            }
        }
    });
</script>
@endsection