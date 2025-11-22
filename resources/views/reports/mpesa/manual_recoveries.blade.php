@extends('layouts.app')

@section('content')
<div class="container mt-4">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4>Manually Recovered M-Pesa Transactions</h4>
        <a href="{{ route('mpesa.manual.form') }}" class="btn btn-danger btn-sm">
            <i class="i-Repair"></i> Add Manual Recovery
        </a>
    </div>

    <form method="GET" class="mb-3">
        <input type="text" name="search" class="form-control" 
               placeholder="Search: Receipt, Account, Phone, Admin"
               value="{{ request('search') }}">
    </form>

    <div class="card">
        <div class="card-body table-responsive">
            <table class="table table-bordered text-center">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Receipt</th>
                        <th>Account</th>
                        <th>Phone</th>
                        <th>Amount</th>
                        <th>Transaction Time</th>
                        <th>Recovered By</th>
                        <th>Processed</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($manualPayments as $row)
                    <tr>
                        <td>{{ $loop->iteration + $manualPayments->firstItem() - 1 }}</td>
                        <td>{{ $row->transaction_id }}</td>
                        <td>{{ $row->bill_ref_number }}</td>
                        <td>{{ $row->msisdn }}</td>
                        <td>{{ number_format($row->transaction_amount,2) }}</td>
                        <td>{{ $row->transaction_time }}</td>
                        <td>{{ $row->last_name }}</td>
                        <td>
                            <span class="badge badge-{{ $row->processed == 'Yes' ? 'success' : 'warning' }}">
                                {{ $row->processed }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">No manual recoveries found.</td>
                    </tr>
                @endforelse
                </tbody>
            </table>

            {{ $manualPayments->withQueryString()->links() }}
        </div>
    </div>

</div>
@endsection
