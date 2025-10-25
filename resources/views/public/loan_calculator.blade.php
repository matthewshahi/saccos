@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Loan Repayment Calculator</h1>
    <a href="{{ route('loans.types.list') }}" class="btn btn-outline-primary">Back to Loan Types</a>
</div>
<div class="separator-breadcrumb border-top"></div>

<div class="card mb-4">
    <div class="card-body">
        <h4 class="card-title">Loan Details</h4>
        <p><strong>Loan Name:</strong> {{ $loan->loan_type_name }}</p>
        <p><strong>Interest Rate:</strong> {{ $loan->loan_type_interest }}% ({{ ucfirst(strtolower($loan->loan_type_interest_type)) }})</p>
        <p><strong>Loan Duration:</strong> {{ $loan->loan_type_duration }} months</p>
        <p><strong>Maximum Amount:</strong> Ksh {{ number_format($loan->loan_type_max_amount) }}</p>
        <p><strong>Qualification Period:</strong> {{ $loan->loan_type_qualification_period }} months</p>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h4 class="card-title">Loan Repayment Calculator</h4>
        <form action="{{ route('loan.calculate', ['id' => $loan->loan_type_id]) }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="amount">Loan Amount</label>
                <input type="number" class="form-control @error('amount') is-invalid @enderror" id="amount" name="amount" placeholder="Enter loan amount" value="{{ old('amount') }}" required>
                <small class="text-muted">Max allowed: Ksh {{ number_format($loan->loan_type_max_amount) }}</small>
                @error('amount')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <div class="form-group">
                <label for="duration">Loan Duration (months)</label>
                <input type="number" class="form-control @error('duration') is-invalid @enderror" id="duration" name="duration" placeholder="Enter loan duration" value="{{ old('duration') }}" required>
                <small class="text-muted">Max allowed: {{ $loan->loan_type_duration }} months</small>
                @error('duration')
                    <div class="invalid-feedback">{{ $message }}</div>
                @enderror
            </div>
            <button type="submit" class="btn btn-primary mt-3">Calculate Repayment Schedule</button>
        </form>
    </div>
</div>

@if(isset($repaymentSchedule))
    <div class="card">
        <div class="card-body">
            <h4 class="card-title">Repayment Schedule</h4>
 


<p class="text-danger small mb-3">
    *Note: Figures shown are indicative and exclude insurance, commissions, and processing charges. 
    The final repayment schedule will be confirmed upon loan approval.
</p>


            
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Principal</th>
                        <th>Interest</th>
                        <th>{{ isset($emi) ? 'EMI' : 'Monthly Payment' }}</th>

                        <th>Balance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($repaymentSchedule as $payment)
                        <tr>
                            <td>{{ $payment['month'] }}</td>
                            <td>{{ number_format($payment['principal'], 2) }}</td>
                            <td>{{ number_format($payment['interest'], 2) }}</td>
                           <td>
    {{ number_format($payment['emi'] ?? ($payment['payment'] ?? 0), 2) }}
</td>

                            <td>{{ number_format($payment['balance'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif
@endsection