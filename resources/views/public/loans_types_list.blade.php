@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Loan Types</h1>
</div>
<div class="separator-breadcrumb border-top"></div>

<div class="card">
    <div class="card-body">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Loan Name</th>
                    <th>Interest (%)</th>
                    <th>Interest Type</th>
                    <th>Duration</th>
                    <th>Max Amount</th>
                    <th>Qualification Period</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($loanTypes as $index => $loan)
                    <tr>
                        <td>{{ $loanTypes->firstItem() + $index }}</td>
                        <td>{{ $loan->loan_type_name }}</td>
                        <td>{{ $loan->loan_type_interest }}%</td>
                        <td>{{ $loan->loan_type_interest_type }}</td>
                        <td>{{ $loan->loan_type_duration }} months</td>
                        <td>Ksh {{ number_format($loan->loan_type_max_amount) }}</td>
                        <td>{{ $loan->loan_type_qualification_period }} months</td>
                        <td>
                            <a href="{{ route('loan.details', ['id' => $loan->loan_type_id]) }}" class="btn btn-info btn-sm">Details</a>
                            <a href="{{ route('loan.calculator', ['id' => $loan->loan_type_id]) }}" class="btn btn-primary btn-sm">Calculate</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="d-flex justify-content-center mt-4">
            {{ $loanTypes->links() }}
        </div>
    </div>
</div>
@endsection