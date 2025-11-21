@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2 class="mb-4">Payments Received (STK Push)</h2>
    



    <div @class(['d-flex', 'flex-wrap', 'justify-content-between', 'align-items-center', 'mb-3'])>
    <div @class(['mb-2'])>
       <a href="{{ route('reports.mpesa.paymentsreceived.c2b') }}" class="btn btn-primary mb-3">View C2B Payments</a>
    </div>

    <div @class(['mb-2'])>
        <a href="{{ route('mpesa.manual.form') }}" @class(['btn', 'btn-danger'])>
            <i @class(['i-Repair'])></i> Manual SMS Recovery
        </a>
    </div>
</div>

    <form method="GET" class="mb-3">
        <input type="text" name="search" class="form-control" placeholder="Search by unique number, checkout request ID, phone number, or receipt number" value="{{ request('search') }}">
        <button type="submit" class="btn btn-secondary mt-2">Search</button>
    </form>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered text-center">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Unique Number</th>
                            <th>Checkout Request ID</th>
                            <th>Phone Number</th>
                            <th>Amount</th>
                            <th>Receipt Number</th>
                            <th>Transaction Date</th>
                            <th>Processed</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($stkPayments as $payment)
                            <tr>
                                <td>{{ $loop->iteration + $stkPayments->firstItem() - 1 }}</td>
                                <td>{{ $payment->unique_number }}</td>
                                <td>{{ $payment->checkout_request_id }}</td>
                                <td>{{ $payment->phone_number }}</td>
                                <td>{{ number_format($payment->amount, 2) }}</td>
                                <td>{{ $payment->mpesa_receipt_number }}</td>
                                <td>{{ $payment->transaction_date }}</td>
                                <td>
                                    <span class="badge bg-{{ $payment->processed === 'Y' ? 'success' : 'warning' }}">
                                        {{ $payment->processed === 'Y' ? 'Yes' : 'No' }}
                                    </span>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8">No payments found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            <div class="mt-3">
                {{ $stkPayments->withQueryString()->links() }}
            </div>
        </div>
    </div>
</div>
@endsection