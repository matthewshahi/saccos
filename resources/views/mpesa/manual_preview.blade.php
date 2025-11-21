@extends('layouts.app')

@section('content')
<div class="container">

<div class="card shadow-sm">
    <div class="card-header bg-warning text-dark">
        <strong>Confirm Manual M-Pesa SMS Recovery</strong>
    </div>

    <div class="card-body">

        <div class="alert alert-warning">
            <strong>Important:</strong> Please verify the SMS details carefully. This posting affects SACCO financial records.
        </div>

        <table class="table table-bordered">
            <tr>
                <th width="30%">Receipt</th>
                <td>{{ $receipt }}</td>
            </tr>
            <tr>
                <th>Account</th>
                <td>{{ $account }}</td>
            </tr>
            <tr>
                <th>Phone</th>
                <td>{{ $phone ?? 'N/A' }}</td>
            </tr>
            <tr>
                <th>Amount</th>
                <td><strong>Ksh {{ number_format($amount,2) }}</strong></td>
            </tr>
            <tr>
                <th>Transaction Time</th>
                <td>{{ \Carbon\Carbon::parse($sms_time)->format('d M Y, h:i A') }}</td>
            </tr>
            <tr>
                <th>Posting By</th>
                <td class="text-primary font-weight-bold">
                    {{ $posted_by }}
                </td>
            </tr>
        </table>

        <form method="POST" action="{{ route('mpesa.manual.process') }}">
            @csrf

            <input type="hidden" name="receipt" value="{{ $receipt }}">
            <input type="hidden" name="account" value="{{ $account }}">
            <input type="hidden" name="amount" value="{{ $amount }}">
            <input type="hidden" name="phone" value="{{ $phone }}">
            <input type="hidden" name="sms_time" value="{{ $sms_time }}">

            <div class="mt-3">
                <button type="submit" class="btn btn-success">
                    ✅ Approve & Post Transaction
                </button>

                <a href="{{ route('mpesa.manual.form') }}" class="btn btn-secondary">
                    Cancel
                </a>
            </div>
        </form>
    </div>
</div>

</div>
@endsection
