@extends('layouts.app')

@section('content')
<div class="container mt-4">
    <h2 class="mb-4">Payments Received (C2B)</h2>
   <div class="d-flex flex-column flex-md-row align-items-stretch align-items-md-center justify-content-between gap-2 mb-3">

    <a href="{{ route('reports.mpesa.paymentsreceived') }}" 
       class="btn btn-outline-primary d-flex align-items-center justify-content-center px-3 py-2">
        <i class="i-Eye me-2"></i>
        <span>View STK Payments</span>
    </a>

    <a href="{{ route('mpesa.manual.list') }}" 
       class="btn btn-outline-secondary d-flex align-items-center justify-content-center px-3 py-2">
        <i class="i-File-Clipboard me-2"></i>
        <span>Manual Recoveries</span>
    </a>

    <a href="{{ route('mpesa.manual.form') }}" 
       class="btn btn-danger d-flex align-items-center justify-content-center px-3 py-2">
        <i class="i-Repair me-2"></i>
        <span>Add Manual Recovery by SMS</span>
    </a>

</div>


    <form method="GET" class="mb-3">
        <input type="text" name="search" class="form-control" placeholder="Search by transaction ID, MSISDN, bill reference, or first name" value="{{ request('search') }}">
        <button type="submit" class="btn btn-secondary mt-2">Search</button>
    </form>

    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered text-center">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Transaction ID</th>
                            <th>MSISDN</th>
                            <th>Bill Reference</th>
                            <th>Amount</th>
                            <th>Transaction Date</th>
                            <th>First Name</th>
                            <th>Processed</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($c2bPayments as $payment)
                            <tr>
                                <td>{{ $loop->iteration + $c2bPayments->firstItem() - 1 }}</td>
                                <td>{{ $payment->transaction_id }}</td>
                                <td>{{ $payment->msisdn }}</td>
                                <td>{{ $payment->bill_ref_number }}</td>
                                <td>{{ number_format($payment->transaction_amount, 2) }}</td>
                                <td>{{ $payment->transaction_time }}</td>
                                <td>{{ $payment->first_name }}</td>
                                <td>
                                    <span class="badge bg-{{ $payment->processed === 'Yes' ? 'success' : 'warning' }}">
                                        {{ $payment->processed === 'Yes' ? 'Yes' : 'No' }}
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
                {{ $c2bPayments->withQueryString()->links() }}
            </div>
        </div>
    </div>
</div>
@endsection