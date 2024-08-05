@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Loan Issued</h1>
    <div class="header-part-right">
        <ul>
            @if(Auth::check())
                <li>{{ Auth::user()->member_name }}</li>
            @endif
            @if(isset($currentPeriod))
                <li><a href="{{ route('admin.periods') }}">{{ $currentPeriod->period_name }}</a></li>
            @endif
            <li><i class="i-Full-Screen header-icon d-none d-sm-inline-block" data-fullscreen=""></i></li>
        </ul>
    </div>
</div>
<div class="separator-breadcrumb border-top"></div>
<div class="container">
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                   
                    <form method="GET" action="{{ route('reports.loans.issued') }}">
                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label for="search_name">Member Name</label>
                                <input class="form-control" id="search_name" type="text" name="search_name" placeholder="Enter member name" value="{{ request('search_name') }}">
                            </div>
                            <div class="col-md-6 form-group mb-3">
                                <label for="search_company_name">Company Name</label>
                                <input class="form-control" id="search_company_name" type="text" name="search_company_name" placeholder="Enter company name" value="{{ request('search_company_name') }}">
                            </div>
                            <div class="col-md-6 form-group mb-3">
                                <label for="search_sacco_id">Sacco ID</label>
                                <input class="form-control" id="search_sacco_id" type="text" name="search_sacco_id" placeholder="Enter sacco ID" value="{{ request('search_sacco_id') }}">
                            </div>
                            <div class="col-md-3 form-group mb-3">
                                <label for="start_period">Start Period (YYYYmm)</label>
                                <input class="form-control" id="start_period" type="text" name="start_period" placeholder="e.g., 202301" value="{{ request('start_period') }}">
                            </div>
                            <div class="col-md-3 form-group mb-3">
                                <label for="end_period">End Period (YYYYmm)</label>
                                <input class="form-control" id="end_period" type="text" name="end_period" placeholder="e.g., 202312" value="{{ request('end_period') }}">
                            </div>
                            <div class="col-md-12">
                                <button class="btn btn-primary">Search</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-12 mb-4">
            <div class="card text-start">
                <div class="card-body">
                   
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Member Name</th>
                                    <th scope="col">Company Name</th>
                                    <th scope="col">Sacco ID</th>
                                    <th scope="col">Loan Amount</th>
                                    <th scope="col">Loan Taken Period</th>
                                    <th scope="col">Start Deduction Period</th>
                                    <th scope="col">Issued On</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($loansIssued as $index => $loan)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $loan->member_name }}</td>
                                    <td>{{ $loan->company_name }}</td>
                                    <td>{{ $loan->member_sacco_id }}</td>
                                    <td>{{ number_format($loan->loan_amount, 2) }}</td>
                                    <td>{{ $loan->loan_taken_period }}</td>
                                    <td>{{ $loan->loan_start_deduction_period }}</td>
                                    <td>{{ \Carbon\Carbon::parse($loan->loan_on)->format('d/m/Y') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
