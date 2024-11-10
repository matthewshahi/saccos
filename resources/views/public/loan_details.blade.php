@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>{{ $loan->loan_type_name }} Details</h1>
    <a href="{{ route('loan.calculator', ['id' => $loan->loan_type_id]) }}" class="btn btn-outline-primary">Loan Calculator</a>
</div>
<div class="separator-breadcrumb border-top"></div>

<div class="card">
    <div class="card-body">
        <p>Interest Rate: {{ $loan->loan_type_interest }}% ({{ ucfirst(strtolower($loan->loan_type_interest_type)) }})</p>
        <p>Loan Duration: {{ $loan->loan_type_duration }} months</p>
        <p>Maximum Amount: Ksh {{ number_format($loan->loan_type_max_amount) }}</p>
        <p>Qualification Period: {{ $loan->loan_type_qualification_period }} months</p>
    </div>
</div>
@endsection