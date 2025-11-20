@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Add New Loan Type</h1>
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

<div class="row">
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-body">
                <div class="card-title mb-3">Loan Type Details</div>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ url('/loans/types/store') }}" method="POST">
                    @csrf
                    <div class="row">

                        {{-- LEFT COLUMN --}}
                        <div class="col-md-6 form-group mb-3">
                            <label>Loan Type Name</label>
                            <input type="text" class="form-control" name="loan_type_name" required>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Interest (%)</label>
                            <input type="number" step="0.01" class="form-control" name="loan_type_interest" required>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Interest Type</label>
                            <select class="form-control" name="loan_type_interest_type" required>
                                <option value="REDUCING BALANCE">REDUCING BALANCE</option>
                                <option value="FIXED INTEREST">FIXED INTEREST</option>
                            </select>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Duration (Months)</label>
                            <input type="number" class="form-control" name="loan_type_duration" required>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Guarantee Percent</label>
                            <input type="number" class="form-control" name="loan_type_guaranteable_percent" required>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Loan Type Code</label>
                            <input type="text" class="form-control" name="loan_type_code" required>
                        </div>

                        {{-- RIGHT COLUMN --}}
                        <div class="col-md-6 form-group mb-3">
                            <label>Maximum Amount</label>
                            <input type="number" step="0.01" class="form-control" name="loan_type_max_amount" required>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Qualification Period (Months)</label>
                            <input type="number" class="form-control" name="loan_type_qualification_period" required>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Loan Account</label>
                            <select class="form-control" name="loan_type_acount" required>
                                @foreach($subAccounts as $acc)
                                    <option value="{{ $acc->sub_account_id }}">
                                        {{ $acc->sub_account_name }} ({{ $acc->main_account_code }}/{{ $acc->sub_account_code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Interest Account</label>
                            <select class="form-control" name="loan_type_int_account" required>
                                @foreach($subAccounts as $acc)
                                    <option value="{{ $acc->sub_account_id }}">
                                        {{ $acc->sub_account_name }} ({{ $acc->main_account_code }}/{{ $acc->sub_account_code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Commission Account</label>
                            <select class="form-control" name="loan_type_comm_account" required>
                                @foreach($subAccounts as $acc)
                                    <option value="{{ $acc->sub_account_id }}">
                                        {{ $acc->sub_account_name }} ({{ $acc->main_account_code }}/{{ $acc->sub_account_code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Insurable</label>
                            <select class="form-control" name="loan_type_insurable" required>
                                <option value="Y">Yes</option>
                                <option value="N">No</option>
                            </select>
                        </div>

                        {{-- NEW FIELD --}}
                        <div class="col-md-12 form-group mb-3">
                            <label class="d-block font-weight-bold mb-2">Instant Qualification</label>
                            <div class="custom-control custom-checkbox">
                                <input type="checkbox" name="loan_type_instant_qualification" value="1" class="custom-control-input" id="instantLoan">
                                <label class="custom-control-label" for="instantLoan">
                                    Allow this loan even if the member has **no shares, no deposits**, and even if they are **newly registered**.
                                </label>
                            </div>
                            <small class="text-muted">Ideal for microfinance-style quick loans.</small>
                        </div>

                        <div class="col-md-12 mt-3">
                            <button class="btn btn-primary">Save Loan Type</button>
                            <a href="{{ route('loans.types') }}" class="btn btn-light">Cancel</a>
                        </div>

                    </div> {{-- end row --}}
                </form>

            </div>
        </div>
    </div>
</div>
@endsection
