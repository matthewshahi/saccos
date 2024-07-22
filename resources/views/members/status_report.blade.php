@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Member Status Report</h1>
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
                <h5 class="mb-4">Member Information</h5>
                <table class="table">
                    <tr>
                        <th>Name</th>
                        <td>{{ $member->member_name }}</td>
                    </tr>
                    <tr>
                        <th>Sacco ID</th>
                        <td>{{ $member->member_sacco_id }}</td>
                    </tr>
                    <tr>
                        <th>Date Joined</th>
                        <td>{{ \Carbon\Carbon::parse($member->member_date_joined)->format('d/m/Y') }}</td>
                    </tr>
                    <tr>
                        <th>Phone Number</th>
                        <td>{{ $member->member_phone_no }}</td>
                    </tr>
                    <tr>
                        <th>Email</th>
                        <td>{{ $member->member_email }}</td>
                    </tr>
                    <tr>
                        <th>Department</th>
                        <td>{{ $member->member_dept }}</td>
                    </tr>
                    <tr>
                        <th>Position</th>
                        <td>{{ $member->member_position }}</td>
                    </tr>
                </table>

                <h5 class="mb-4 mt-5">Loan Information</h5>
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Loan ID</th>
                            <th>Amount</th>
                            <th>Interest Payable</th>
                            <th>Monthly Repayment</th>
                            <th>Loan Start Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($loans as $loan)
                        <tr>
                            <td>{{ $loan->loan_id }}</td>
                            <td>{{ $loan->loan_amount }}</td>
                            <td>{{ $loan->loan_interest_payable }}</td>
                            <td>{{ $loan->loan_monthly_repayment_amount }}</td>
                            <td>{{ \Carbon\Carbon::parse($loan->loan_on)->format('d/m/Y') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                <h5 class="mb-4 mt-5">Share Information</h5>
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Share ID</th>
                            <th>Amount</th>
                            <th>Date Paid</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($shares as $share)
                        <tr>
                            <td>{{ $share->share_id }}</td>
                            <td>{{ $share->share_amount_paying }}</td>
                            <td>{{ \Carbon\Carbon::parse($share->share_date_paid)->format('d/m/Y') }}</td>
                            <td>{{ $share->share_description }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                <h5 class="mb-4 mt-5">Guaranteed Loans</h5>
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Loan ID</th>
                            <th>Member Name</th>
                            <th>Amount Guaranteed</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($guaranteedLoans as $guaranteedLoan)
                        <tr>
                            <td>{{ $guaranteedLoan->loan_id }}</td>
                            <td>{{ $guaranteedLoan->member_name }}</td>
                            <td>{{ $guaranteedLoan->loan_guar_amount_guaranteed }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                <h5 class="mb-4 mt-5">Guarantors</h5>
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>Member Name</th>
                            <th>Amount Guaranteed</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($guarantors as $guarantor)
                        <tr>
                            <td>{{ $guarantor->member_name }}</td>
                            <td>{{ $guarantor->loan_guar_amount_guaranteed }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
