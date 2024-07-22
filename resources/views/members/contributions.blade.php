@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Edit Member Contribution for {{ $data['member']->member_name }}</h1>
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

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('members.contributions', ['id' => $data['member']->member_id]) }}" method="post">
        @csrf
        <table class="table table-bordered">
            <tr>
                <th colspan="4">Edit Member Contribution || 
                    <a href="{{ route('members.list') }}">List Members</a> || 
                    <a href="{{ route('members.statement', ['id' => $data['member']->member_id]) }}">Member Statement</a> || 
                    <a href="{{ route('members.nextOfKin', ['id' => $data['member']->member_id]) }}">Next of Kin</a> || 
                    <a href="{{ route('members.changePassword', ['id' => $data['member']->member_id]) }}">Change Password</a>
                </th>
            </tr>
            <tr class="row-a">
                <td>Member names*</td>
                <td>{{ $data['member']->member_name }} <a href="{{ route('members.edit', ['id' => $data['member']->member_id]) }}">+</a></td>
                <td>Date joined*</td>
                <td>{{ \Carbon\Carbon::parse($data['member']->member_date_joined)->format('Y-m-d') }}</td>
            </tr>
            <tr class="row-b">
                <td>Sacco no/ID.</td>
                <td>{{ $data['member']->member_sacco_id }}</td>
                <td>Company/Dept*</td>
                <td>{{ $data['member']->company_name }}/{{ $data['member']->department_name }}</td>
            </tr>
            <tr class="row-a">
                <td>Monthly share contribution</td>
                <td><input name="member_share_contr_monthly" type="text" class="form-control" value="{{ number_format(round($data['member']->member_share_contr_monthly, 2), 2) }}" /></td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <tr class="row-b">
                <td>Monthly FOSA contribution</td>
                <td><input name="member_fosa_contr_monthly" type="text" class="form-control" value="{{ number_format(round($data['member']->member_fosa_contr_monthly, 2), 2) }}" /></td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
            </tr>
            <tr class="row-a">
                <td colspan="4"><strong>Loans</strong></td>
            </tr>
            <tr class="row-b">
                <td colspan="4">
                    <table class="table table-bordered">
                        <tr>
                            <th>#</th>
                            <th>Loan Name</th>
                            <th>Category</th>
                            <th>Period</th>
                            <th>Amount</th>
                            <th>Balance</th>
                            <th>Monthly Payment</th>
                            <th>Stopped</th>
                        </tr>
                        @foreach($data['loans'] as $index => $loan)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $loan->loan_type_name }} ({{ $loan->loan_id }})</td>
                                <td>{{ $loan->loan_category_name }}</td>
                                <td>{{ $loan->loan_taken_period }}</td>
                                <td>{{ number_format($loan->loan_amount, 2) }}</td>
                                <td>{{ number_format($loan->loan_amount - $loan->loan_loan_paid, 2) }}</td>
                                <td>
                                    <input type="text" class="form-control" name="loan_monthly_repayment_amount{{ $index }}" value="{{ number_format($loan->loan_monthly_repayment_amount, 2) }}">
                                    <input type="hidden" name="loan_id{{ $index }}" value="{{ $loan->loan_id }}">
                                </td>
                                <td>
                                    <select name="loan_stoped{{ $index }}" class="form-control">
                                        <option value="N" @if($loan->loan_stoped == 'N') selected @endif>No</option>
                                        <option value="Y" @if($loan->loan_stoped == 'Y') selected @endif>Yes</option>
                                    </select>
                                </td>
                            </tr>
                        @endforeach
                    </table>
                    <input type="hidden" name="t_loans_no" value="{{ count($data['loans']) }}">
                </td>
            </tr>
            <tr class="row-b">
                <td colspan="4">
                    <button type="submit" class="btn btn-primary">Update Contributions</button>
                </td>
            </tr>
        </table>
    </form>
@endsection

