@extends('layouts.app')

@section('content')

<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>End Month Processing - Shares</h1>
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

<div class="mb-3">
    <a href="{{ route('list.contribution') }}">List member contribution</a> || 
    <a href="{{ route('members.active', ['status' => 'y']) }}">List members</a>
</div>

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
        <h4 class="card-title mb-3">End Month Processing - Shares</h4>
        <p>Please ensure all details are correct before proceeding. This process is not reversible.</p>
        <form action="{{ route('proc.end.month.shares') }}" method="POST">
            @csrf
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Institutions</th>
                            <th scope="col">Expected Contribution</th>
                            <th scope="col">Doc. No.</th>
                            <th scope="col">Date Paid</th>
                            <th scope="col">Update</th>
                            <th scope="col">Processed</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($companies as $index => $company)
                            <tr>
                                <th scope="row">{{ $index + 1 }}</th>
                                <td>{{ $company->company_name }}</td>
                                <td>{{ number_format($company->tmember_share_contr_monthly, 2) }}</td>
                                <td>
                                    <input type="text" name="share_doc_no{{ $index }}" id="share_doc_no{{ $index }}" value="{{ old('share_doc_no'.$index) }}" class="form-control" />
                                </td>
                                <td>
                                    <input type="date" name="share_date_paid{{ $index }}" id="share_date_paid{{ $index }}" value="{{ old('share_date_paid'.$index, date('Y-m-d')) }}" class="form-control" />
                                </td>
                                <td>
                                    <label class="switch pe-5 switch-success me-3">
                                        <input type="checkbox" name="update{{ $index }}" value="{{ $company->company_id }}" {{ old('update'.$index) ? 'checked' : '' }}>
                                        <span class="slider"></span>
                                    </label>
                                    <input type="hidden" name="company_name{{ $index }}" value="{{ $company->company_name }}">
                                </td>
                                <td>
                                    @if($company->is_processed)
                                        <span class="badge bg-success">Processed</span>
                                    @else
                                        <span class="badge bg-danger">Not Processed</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
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
