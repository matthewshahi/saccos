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

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="row mb-4">
        <div class="col-md-12 mb-4">
            <div class="card text-start">
                <div class="card-body">
                    <div class="table-responsive">
                        <h4>Member Information</h4>
                        <table class="display table table-striped table-bordered" style="width: 100%">
                            <thead>
                                <tr>
                                    <th>Field</th>
                                    <th>Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Name</td>
                                    <td>{{ $member->member_name }}, {{ $member->member_sacco_id }}</td>
                                </tr>
                                <tr>
                                    <td>Company</td>
                                    <td>{{ $member->company_name }}</td>
                                </tr>
                                <tr>
                                    <td>Department</td>
                                    <td>{{ $member->department_name }}</td>
                                </tr>
                                <tr>
                                    <td>Total Share Deposit</td>
                                    <td>{{ number_format($memberFinancials['total_share_deposit'], 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Total Capital Shares</td>
                                    <td>{{ number_format($memberFinancials['total_capital_shares'], 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Total FOSA Deposits</td>
                                    <td>{{ number_format($memberFinancials['total_fosa_deposits'], 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Unpaid Loan (Principal)</td>
                                    <td>{{ number_format($memberFinancials['unpaid_loan'], 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Tied Shares - Others</td>
                                    <td>{{ number_format($memberFinancials['tied_shares_others'], 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Tied Shares - Self</td>
                                    <td>{{ number_format($memberFinancials['tied_shares_self'], 2) }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <h4>Loans Taken by the Member</h4>
                        @foreach($loansTakenWithGuarantors as $loan)
                            @if(($loan->loan_amount - $loan->loan_loan_paid) > $threshold_amount)
                                <div class="card mb-3">
                                    <div class="card-header">
                                        <strong>{{ $loan->loan_type_name }} ({{ $loan->loan_id }})</strong>
                                        <div class="float-right">
                                            <strong>Loan Taken: </strong>{{ number_format($loan->loan_amount, 2) }},
                                            <strong>Loan Paid: </strong>{{ number_format($loan->loan_loan_paid, 2) }},
                                            <strong>Commission: </strong>{{ number_format($loan->loan_commision, 2) }},
                                            <strong>Insurance: </strong>{{ number_format($loan->loan_insurance, 2) }},
                                            <strong>Period Taken: </strong>{{ $loan->loan_taken_period }},
                                            <strong>Description: </strong>{{ $loan->loan_description }}
                                        </div>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="display table table-striped table-bordered" style="width: 100%">
                                                <thead>
                                                    <tr>
                                                        <th>#</th>
                                                        <th>Guarantor Name</th>
                                                        <th>Sacco No.</th>
                                                        <th>Amount Guaranteed</th>
                                                        <th>Amount Freed</th>
                                                        <th>Amount Tied</th>
                                                        @if($showHyperlinks)
                                                            <th>Action</th>
                                                        @endif
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($loan->guarantors as $guarantor)
                                                        <tr>
                                                            <td>{{ $loop->iteration }}</td>
                                                            <td>
                                                                @if($showHyperlinks)
                                                                    <a href="{{ route('changeGuarantors', ['member_id' => $guarantor->member_id, 'guarantor_id' => $guarantor->loan_guar_id]) }}">{{ $guarantor->member_name }}</a>
                                                                @else
                                                                    {{ $guarantor->member_name }}
                                                                @endif
                                                            </td>
                                                            <td>{{ $guarantor->member_sacco_id }}</td>
                                                            <td>{{ number_format($guarantor->loan_guar_amount_guaranteed, 2) }}</td>
                                                            <td>{{ number_format($guarantor->loan_guar_amount_freed, 2) }}</td>
                                                            <td>{{ number_format($guarantor->loan_guar_amount_guaranteed - $guarantor->loan_guar_amount_freed, 2) }}</td>
                                                            @if($showHyperlinks)
                                                                <td>
                                                                    <form action="{{ route('deleteGuarantor', ['member_id' => $member->member_id, 'guarantor_id' => $guarantor->loan_guar_id]) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this guarantor?');">
                                                                        @csrf
                                                                        @method('DELETE')
                                                                        <button type="submit" class="btn btn-danger btn-sm"><i class="nav-icon i-Close-Window fw-bold"></i></button>
                                                                    </form>
                                                                </td>
                                                            @endif
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        @endforeach

                        <h4>Loans Guaranteed by the Member</h4>
                        <div class="table-responsive">
                            <table class="display table table-striped table-bordered" style="width: 100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Name</th>
                                        <th>Loan Type</th>
                                        <th>Period Taken</th>
                                        <th>Loan Amount Taken</th>
                                        <th>Amount Guaranteed</th>
                                        <th>Amount Freed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($loansGuaranteed as $index => $loan)
                                        @if(($loan->loan_amount - $loan->loan_loan_paid) > $threshold_amount && ($loan->loan_guar_amount_guaranteed - $loan->loan_guar_amount_freed) > $threshold_amount)
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td>{{ $loan->member_name }}, {{ $loan->member_sacco_id }}</td>
                                                <td>{{ $loan->loan_type_name }}</td>
                                                <td>{{ $loan->loan_taken_period }}</td>
                                                <td>{{ number_format($loan->loan_amount, 2) }}</td>
                                                <td>{{ number_format($loan->loan_guar_amount_guaranteed, 2) }}</td>
                                                <td>{{ number_format($loan->loan_guar_amount_freed, 2) }}</td>
                                            </tr>
                                        @endif
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
