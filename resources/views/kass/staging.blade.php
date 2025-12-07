@extends('layouts.app')

@section('content')
<div class="container">

    <h3>Staging Table — Contributions</h3>

    <table class="table table-bordered table-sm">
        <thead>
            <tr>
                <th>Name</th><th>Type</th><th>Amount</th><th>Year</th><th>Month</th><th>Company</th>
            </tr>
        </thead>
        @foreach($contrib as $c)
            <tr>
                <td>{{ $c->raw_name }}</td>
                <td>{{ $c->raw_type }}</td>
                <td>{{ $c->amount }}</td>
                <td>{{ $c->year }}</td>
                <td>{{ $c->month }}</td>
                <td>{{ $c->company }}</td>
            </tr>
        @endforeach
    </table>

    {{ $contrib->links() }}

    <hr>

    <h3>Staging Table — Loans</h3>

    <table class="table table-bordered table-sm">
        <thead>
            <tr>
                <th>Name</th><th>Loan Type</th><th>DR</th><th>CR</th><th>INT</th><th>Year</th><th>Month</th>
            </tr>
        </thead>
        @foreach($loans as $l)
            <tr>
                <td>{{ $l->raw_name }}</td>
                <td>{{ $l->loan_type }}</td>
                <td>{{ $l->repayment_amount }}</td>
                <td>{{ $l->principal_disbursed }}</td>
                <td>{{ $l->interest_amount }}</td>
                <td>{{ $l->year }}</td>
                <td>{{ $l->month }}</td>
            </tr>
        @endforeach
    </table>

    {{ $loans->links() }}

    <hr>

    <form action="{{ route('kass.staging.clear') }}" method="POST">
        @csrf @method('DELETE')
        <button class="btn btn-danger">Clear Staging Tables</button>
    </form>

</div>
@endsection
