@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Edit Loan Type</h1>
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

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card text-start">
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ url('/loans/types/update/' . $loanType->loan_type_id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <div class="form-group">
                            <label for="loan_type_name">Loan Type Name</label>
                            <input type="text" class="form-control" id="loan_type_name" name="loan_type_name" value="{{ $loanType->loan_type_name }}" required>
                        </div>
                        <div class="form-group">
                            <label for="loan_type_interest">Interest</label>
                            <input type="number" step="0.01" class="form-control" id="loan_type_interest" name="loan_type_interest" value="{{ $loanType->loan_type_interest }}" required>
                        </div>
                        <div class="form-group">
                            <label for="loan_type_interest_type">Interest Type</label>
                            <select class="form-control" id="loan_type_interest_type" name="loan_type_interest_type" required>
                                <option value="REDUCING BALANCE" {{ $loanType->loan_type_interest_type == 'REDUCING BALANCE' ? 'selected' : '' }}>REDUCING BALANCE</option>
                                <option value="FIXED INTEREST" {{ $loanType->loan_type_interest_type == 'FIXED INTEREST' ? 'selected' : '' }}>FIXED INTEREST</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="loan_type_duration">Duration (Months)</label>
                            <input type="number" class="form-control" id="loan_type_duration" name="loan_type_duration" value="{{ $loanType->loan_type_duration }}" required>
                        </div>
                        <div class="form-group">
                            <label for="loan_type_guaranteable_percent">Guarantee Percent</label>
                            <input type="number" class="form-control" id="loan_type_guaranteable_percent" name="loan_type_guaranteable_percent" value="{{ $loanType->loan_type_guaranteable_percent }}" required>
                        </div>
                        <div class="form-group">
                            <label for="loan_type_code">Loan Type Code</label>
                            <input type="text" class="form-control" id="loan_type_code" name="loan_type_code" value="{{ $loanType->loan_type_code }}" required>
                        </div>
                        <div class="form-group">
                            <label for="loan_type_max_amount">Max Amount</label>
                            <input type="number" step="0.01" class="form-control" id="loan_type_max_amount" name="loan_type_max_amount" value="{{ $loanType->loan_type_max_amount }}" required>
                        </div>
                        <div class="form-group">
                            <label for="loan_type_qualification_period">Qualification Period</label>
                            <input type="number" class="form-control" id="loan_type_qualification_period" name="loan_type_qualification_period" value="{{ $loanType->loan_type_qualification_period }}" required>
                        </div>
                        <div class="form-group">
                            <label for="loan_type_acount">Loan Account</label>
                            <select class="form-control" id="loan_type_acount" name="loan_type_acount" required>
                                @foreach($subAccounts as $subAccount)
                                    <option value="{{ $subAccount->sub_account_id }}" {{ $loanType->loan_type_acount == $subAccount->sub_account_id ? 'selected' : '' }}>
                                        {{ $subAccount->sub_account_name }} ({{ $subAccount->main_account_code }}/{{ $subAccount->sub_account_code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="loan_type_int_account">Interest Account</label>
                            <select class="form-control" id="loan_type_int_account" name="loan_type_int_account" required>
                                @foreach($subAccounts as $subAccount)
                                    <option value="{{ $subAccount->sub_account_id }}" {{ $loanType->loan_type_int_account == $subAccount->sub_account_id ? 'selected' : '' }}>
                                        {{ $subAccount->sub_account_name }} ({{ $subAccount->main_account_code }}/{{ $subAccount->sub_account_code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="loan_type_comm_account">Commission Account</label>
                            <select class="form-control" id="loan_type_comm_account" name="loan_type_comm_account" required>
                                @foreach($subAccounts as $subAccount)
                                    <option value="{{ $subAccount->sub_account_id }}" {{ $loanType->loan_type_comm_account == $subAccount->sub_account_id ? 'selected' : '' }}>
                                        {{ $subAccount->sub_account_name }} ({{ $subAccount->main_account_code }}/{{ $subAccount->sub_account_code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="loan_type_insurable">Insurable</label>
                            <select class="form-control" id="loan_type_insurable" name="loan_type_insurable" required>
                                <option value="Y" {{ $loanType->loan_type_insurable == 'Y' ? 'selected' : '' }}>Yes</option>
                                <option value="N" {{ $loanType->loan_type_insurable == 'N' ? 'selected' : '' }}>No</option>
                            </select>
                        </div>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
