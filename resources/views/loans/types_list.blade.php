@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Loan Types</h1>
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

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card text-start">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="display table table-striped table-bordered" style="width: 100%">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Interest</th>
                                    <th>Interest Type</th>
                                    <th>Duration (Months)</th>
                                    <th>Guarantee Percent</th>
                                    <th>Loan Type Code</th>
                                    <th>Max Amount</th>
                                    <th>Qualification Period</th>
                                    <th>Loan Account</th>
                                    <th>Interest Account</th>
                                    <th>Commission Account</th>
                                    <th>Insurable</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($loanTypes as $loanType)
                                    <tr>
                                        <td>{{ $loanType->loan_type_name }}</td>
                                        <td>{{ $loanType->loan_type_interest }}</td>
                                        <td>{{ $loanType->loan_type_interest_type }}</td>
                                        <td>{{ $loanType->loan_type_duration }}</td>
                                        <td>{{ $loanType->loan_type_guaranteable_percent }}</td>
                                        <td>{{ $loanType->loan_type_code }}</td>
                                        <td>{{ $loanType->loan_type_max_amount }}</td>
                                        <td>{{ $loanType->loan_type_qualification_period }}</td>
                                        <td>{{ $subAccountDetails[$loanType->loan_type_acount]->sub_account_name }} ({{ $subAccountDetails[$loanType->loan_type_acount]->main_account_code }}/{{ $subAccountDetails[$loanType->loan_type_acount]->sub_account_code }})</td>
                                        <td>{{ $subAccountDetails[$loanType->loan_type_int_account]->sub_account_name }} ({{ $subAccountDetails[$loanType->loan_type_int_account]->main_account_code }}/{{ $subAccountDetails[$loanType->loan_type_int_account]->sub_account_code }})</td>
                                        <td>{{ $subAccountDetails[$loanType->loan_type_comm_account]->sub_account_name }} ({{ $subAccountDetails[$loanType->loan_type_comm_account]->main_account_code }}/{{ $subAccountDetails[$loanType->loan_type_comm_account]->sub_account_code }})</td>
                                        <td>{{ $loanType->loan_type_insurable == 'Y' ? 'Yes' : 'No' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
