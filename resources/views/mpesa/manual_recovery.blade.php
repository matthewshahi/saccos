@extends('layouts.app')

@section('content')
<div class="container">

    <h3>⚠ Manual M-Pesa Reconciliation / M-Pesa SMS Recovery</h3>

    <p class="text-muted">
        Sometimes IPN may fail due to network issues, Safaricom delivery failure, or server outages.
        Use this module ONLY when a valid M-Pesa SMS was received but transaction was not posted.
    </p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('mpesa.manual.validate') }}">
        @csrf

        <div class="form-group">
            <label>Paste Full M-Pesa SMS</label>
            <textarea class="form-control" rows="6" name="sms_message" required></textarea>
        </div>

        <br>
        <button class="btn btn-primary">Validate SMS</button>
    </form>

</div>
@endsection
