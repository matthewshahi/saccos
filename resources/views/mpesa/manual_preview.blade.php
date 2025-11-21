@extends('layouts.app')

@section('content')
<div class="container">

<h4>Confirm Manual M-Pesa SMS Recovery</h4>

<div class="alert alert-warning">
    <strong>Important:</strong> Please verify the SMS details carefully before posting. This action will update SACCO financial records.
</div>

<ul class="list-group mb-3">
    <li class="list-group-item"><strong>Receipt:</strong> {{ $receipt }}</li>
    <li class="list-group-item"><strong>Account:</strong> {{ $account }}</li>
    <li class="list-group-item"><strong>Phone:</strong> {{ $phone ?? 'N/A' }}</li>
    <li class="list-group-item"><strong>Amount:</strong> Ksh {{ number_format($amount,2) }}</li>
    <li class="list-group-item">
        <strong>Transaction Time:</strong> {{ \Carbon\Carbon::parse($sms_time)->format('d M Y, h:i A') }}
    </li>
    <li class="list-group-item">
        <strong>Posting By:</strong> {{ auth()->user()->name }}
    </li>
</ul>

<form method="POST" action="{{ route('mpesa.manual.process') }}">
    @csrf

    <input type="hidden" name="receipt" value="{{ $receipt }}">
    <input type="hidden" name="account" value="{{ $account }}">
    <input type="hidden" name="amount" value="{{ $amount }}">
    <input type="hidden" name="phone" value="{{ $phone }}">
    <input type="hidden" name="sms_time" value="{{ $sms_time }}">

    <button class="btn btn-success">
        <i class="fa fa-check-circle"></i> Approve & Post Transaction
    </button>

    <a href="{{ route('mpesa.manual.form') }}" class="btn btn-secondary">
        Cancel
    </a>
</form>

</div>
@endsection
