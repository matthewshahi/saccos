@extends('layouts.app')

@section('content')
<div class="container">

<h4>Confirm SMS Transaction</h4>

<ul class="list-group mb-3">
    <li class="list-group-item"><strong>Receipt:</strong> {{ $receipt }}</li>
    <li class="list-group-item"><strong>Account:</strong> {{ $account }}</li>
    <li class="list-group-item"><strong>Phone:</strong> {{ $phone }}</li>
    <li class="list-group-item"><strong>Amount:</strong> Ksh {{ number_format($amount,2) }}</li>
</ul>

<form method="POST" action="{{ route('mpesa.manual.process') }}">
    @csrf
    <input type="hidden" name="receipt" value="{{ $receipt }}">
    <input type="hidden" name="account" value="{{ $account }}">
    <input type="hidden" name="amount" value="{{ $amount }}">
    <input type="hidden" name="phone" value="{{ $phone }}">

    <button class="btn btn-success">Approve & Post Transaction</button>
    <a href="{{ route('mpesa.manual.form') }}" class="btn btn-secondary">Cancel</a>
</form>

</div>
@endsection
