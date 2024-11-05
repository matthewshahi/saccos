@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>End Month Processing - Loans</h1>
    <div class="header-part-right">
        <ul>
            @if(Auth::check())
                <li>{{ Auth::user()->name }}</li>
            @endif
            @if(isset($monthly_cutoff_date))
                <li>Cutoff Date: {{ $monthly_cutoff_date }}</li>
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
                            <th scope="col">Institution</th>
                            <th scope="col">Loan Type</th>
                            <th scope="col">Expected Repayment</th>
                            <th scope="col">Doc. No.</th>
                            <th scope="col">Date Paid</th>
                            <th scope="col">Update</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($loans as $index => $loan)
                            <tr>
                                <th scope="row">{{ $index + 1 }}</th>
                                <td>{{ $loan->company_name }}</td>
                                <td>{{ $loan->loan_type_name }}</td>
                                <td>{{ number_format($loan->loan_monthly_repayment_amount, 2) }}</td>
                                <td>
                                    <input type="text" name="loan_doc_no{{ $index }}" class="form-control" />
                                </td>
                                <td>
                                    <input type="date" name="loan_date_paid{{ $index }}" class="form-control" value="{{ date('Y-m-d') }}" />
                                </td>
                                <td>
                                    <label class="switch switch-success">
                                        <input type="checkbox" name="update{{ $index }}" value="{{ $loan->loan_id }}">
                                        <span class="slider"></span>
                                    </label>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="text-center mt-4">
                <button type="submit" class="btn btn-primary">Process Loans</button>
                <input type="hidden" name="submitted" value="{{ count($loans) }}">
            </div>
            <p class="text-center mt-3">This process is not reversible. Please make a backup before proceeding.</p>
        </form>
    </div>
</div>
@endsection