@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Interest Run Details</h1>
        <ul>
            <li><a href="{{ route('special_savings.interest.index') }}">Interest Runs</a></li>
            <li>{{ $record->special_saving_interest_run_period }}</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @include('special_savings.partials.alerts')

    <div class="row">
        <div class="col-md-3">
            <div class="card mb-4"><div class="card-body">
                <p class="text-muted mb-1">Accounts</p>
                <h4>{{ number_format($record->special_saving_interest_run_total_accounts) }}</h4>
            </div></div>
        </div>

        <div class="col-md-3">
            <div class="card mb-4"><div class="card-body">
                <p class="text-muted mb-1">Qualified</p>
                <h4>{{ number_format($record->special_saving_interest_run_qualified_accounts) }}</h4>
            </div></div>
        </div>

        <div class="col-md-3">
            <div class="card mb-4"><div class="card-body">
                <p class="text-muted mb-1">Interest</p>
                <h4>{{ number_format($record->special_saving_interest_run_total_interest, 2) }}</h4>
            </div></div>
        </div>

        <div class="col-md-3">
            <div class="card mb-4"><div class="card-body">
                <p class="text-muted mb-1">Status</p>
                <h4>{{ $record->special_saving_interest_run_status }}</h4>
            </div></div>
        </div>

        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">{{ $record->special_saving_product_name }} - {{ $record->special_saving_interest_run_period }}</div>

                    <table class="table table-bordered table-sm">
                        <tr><th>Start Date</th><td>{{ $record->special_saving_interest_run_start_date }}</td></tr>
                        <tr><th>End Date</th><td>{{ $record->special_saving_interest_run_end_date }}</td></tr>
                        <tr><th>Method</th><td>{{ $record->special_saving_interest_run_method }}</td></tr>
                        <tr><th>Annual Rate</th><td>{{ number_format($record->special_saving_interest_run_annual_rate, 6) }}%</td></tr>
                        <tr><th>Monthly Rate</th><td>{{ number_format($record->special_saving_interest_run_monthly_rate, 6) }}%</td></tr>
                        <tr><th>Total Qualifying Balance</th><td>{{ number_format($record->special_saving_interest_run_total_qualifying_balance, 2) }}</td></tr>
                    </table>

                    @if(in_array($record->special_saving_interest_run_status, ['Draft', 'Processing']))
                        <form method="POST" action="{{ route('special_savings.interest.post', $record->special_saving_interest_run_id) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-primary" onclick="return confirm('Post this interest run?')">Post Interest</button>
                        </form>

                        <form method="POST" action="{{ route('special_savings.interest.cancel', $record->special_saving_interest_run_id) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-outline-danger" onclick="return confirm('Cancel this interest run?')">Cancel</button>
                        </form>
                    @endif

                    @if($record->special_saving_interest_run_status == 'Posted')
                        <form method="POST" action="{{ route('special_savings.interest.reverse', $record->special_saving_interest_run_id) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-danger" onclick="return confirm('Reverse this posted interest run?')">Reverse</button>
                        </form>
                    @endif

                    <a href="{{ route('special_savings.interest.items', $record->special_saving_interest_run_id) }}" class="btn btn-outline-secondary">View Items</a>
                    <a href="{{ route('special_savings.interest.index') }}" class="btn btn-outline-secondary">Back</a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection