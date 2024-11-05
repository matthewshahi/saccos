@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>End Month Processing - Loans</h1>
    <div class="header-part-right">
        <ul>
            @if(Auth::check())
                <li>{{ Auth::user()->name }}</li>
            @endif
            @if(isset($currentPeriod))
                <li>{{ $currentPeriod->period_name }}</li>
            @endif
            <li><i class="i-Full-Screen header-icon d-none d-sm-inline-block" data-fullscreen=""></i></li>
        </ul>
    </div>
</div>
<div class="separator-breadcrumb border-top"></div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<!-- Search Form with Template Styling -->
<div class="col-md-12 mb-4">
    <div class="card">
        <div class="card-body">
            <div class="card-title mb-3">Search Loans</div>
            <form action="{{ route('proc.end.month.loans') }}" method="GET">
                <div class="row">
                    <div class="col-md-6 form-group mb-3">
                        <label for="search_institution">Institution</label>
                        <input type="text" name="search_institution" id="search_institution" class="form-control" placeholder="Search Institution" value="{{ request('search_institution') }}">
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label for="search_loan_type">Loan Type</label>
                        <input type="text" name="search_loan_type" id="search_loan_type" class="form-control" placeholder="Search Loan Type" value="{{ request('search_loan_type') }}">
                    </div>
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="card text-start">
    <div class="card-body">
        <h4 class="card-title mb-3">End Month Processing - Loans</h4>
        <p>Please ensure all details are correct before proceeding. This process is not reversible.</p>
        <form action="{{ route('proc.end.month.loans') }}" method="POST">
            @csrf
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Institutions</th>
                            <th scope="col">Loan Types</th>
                            <th scope="col">Expected Contribution</th>
                            <th scope="col">Doc. No.</th>
                            <th scope="col">Date Paid</th>
                            <th scope="col">Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $totalContribution = 0;
                        @endphp
                        @foreach ($companies as $index => $company)
                            @php
                                $totalContribution += $company->lr_m_payment;
                            @endphp
                            <tr>
                                <th scope="row">{{ $index + 1 }}</th>
                                <td>{{ $company->company_name }}</td>
                                <td>{{ $company->loan_type_name }}</td>
                                <td>{{ number_format($company->lr_m_payment, 2) }}</td>
                                <td>
                                    <input type="text" name="loan_doc_no{{ $index }}" class="form-control" />
                                </td>
                                <td>
                                    <input type="date" name="loan_date_paid{{ $index }}" class="form-control" value="{{ date('Y-m-d') }}" />
                                </td>
                                <td>
                                    <input type="checkbox" name="update{{ $index }}" value="{{ $company->company_id }}" />
                                </td>
                            </tr>
                        @endforeach
                        <tr>
                            <td colspan="3" class="text-right"><strong>Total Expected Contribution:</strong></td>
                            <td><strong>{{ number_format($totalContribution, 2) }}</strong></td>
                            <td colspan="3"></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="text-center mt-4">
                <button type="submit" class="btn btn-primary">Save</button>
                <input type="hidden" name="submitted" value="{{ count($companies) }}">
            </div>
            <p class="text-center mt-3">This process is not reversible. Please make a backup before proceeding.</p>
        </form>
    </div>
</div>
@endsection