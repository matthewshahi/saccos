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
    <a href="{{ url('/loans/types/add') }}" class="btn btn-primary mt-4">Add New Loan Type</a>
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card text-start">
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">{{ session('success') }}</div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger">{{ session('error') }}</div>
                    @endif
                    <div class="table-responsive">
                        <table class="display table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Loan Type Name</th>
                                    <th>Interest</th>
                                    <th>Interest Type</th>
                                    <th>Duration</th>
                                    <th>Guarantee Percent</th>
                                    <th>Loan Type Code</th>
                                    <th>Max Amount</th>
                                    <th>Qualification Period</th>
                                    <th>Loan Account</th>
                                    <th>Interest Account</th>
                                    <th>Commission Account</th>
                                    <th>Insurable</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            @foreach($loanTypes as $loanType)
    <tr>
        <td>{{ $loanType->loan_type_name }}</td>
        <td>{{ number_format($loanType->loan_type_interest, 2) }}</td>
        <td>{{ $loanType->loan_type_interest_type }}</td>
        <td>{{ $loanType->loan_type_duration }}</td>
        <td>{{ $loanType->loan_type_guaranteable_percent }}%</td>
        <td>{{ $loanType->loan_type_code }}</td>
        <td>{{ number_format($loanType->loan_type_max_amount, 2) }}</td>
        <td>{{ $loanType->loan_type_qualification_period }} months</td>
        <td>
            @php
                $acountKey = $loanType->loan_type_acount;
                $account = (!empty($acountKey) && isset($subAccountDetails[$acountKey])) 
                            ? $subAccountDetails[$acountKey] 
                            : null;
            @endphp
            @if($account)
                {{ $account->sub_account_name }} ({{ $account->main_account_code }}/{{ $account->sub_account_code }})
            @else
                No Account Set
            @endif
        </td>
        <td>
            @php
                $intAccountKey = $loanType->loan_type_int_account;
                $intAccount = (!empty($intAccountKey) && isset($subAccountDetails[$intAccountKey])) 
                              ? $subAccountDetails[$intAccountKey] 
                              : null;
            @endphp
            @if($intAccount)
                {{ $intAccount->sub_account_name }} ({{ $intAccount->main_account_code }}/{{ $intAccount->sub_account_code }})
            @else
                No Account Set
            @endif
        </td>
        <td>
            @php
                $commAccountKey = $loanType->loan_type_comm_account;
                $commAccount = (!empty($commAccountKey) && isset($subAccountDetails[$commAccountKey])) 
                               ? $subAccountDetails[$commAccountKey] 
                               : null;
            @endphp
            @if($commAccount)
                {{ $commAccount->sub_account_name }} ({{ $commAccount->main_account_code }}/{{ $commAccount->sub_account_code }})
            @else
                No Account Set
            @endif
        </td>
        <td>{{ $loanType->loan_type_insurable == 'Y' ? 'Yes' : 'No' }}</td>
        <td>
            <a href="{{ url('/loans/types/edit/' . $loanType->loan_type_id) }}" class="btn btn-warning">Edit</a>
            <form action="{{ url('/loans/types/delete/' . $loanType->loan_type_id) }}" method="POST" style="display:inline;">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-danger">Delete</button>
            </form>
        </td>
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
