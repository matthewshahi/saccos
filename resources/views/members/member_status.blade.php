@extends('layouts.app')

@section('content')
<div class="container">
    <h4>Member Status Report</h4>
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
    <div class="row">
        <div class="col-md-12">
            <strong>Name: {{ $member->member_name }}, {{ $member->member_sacco_id }}</strong>
        </div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <table class="table table-bordered">
                <tr>
                    <th>Company</th>
                    <td>{{ $member->company_name }}</td>
                </tr>
                <tr>
                    <th>Department</th>
                    <td>{{ $member->department_name }}</td>
                </tr>
                <tr>
                    <th>Total Share Deposit</th>
                    <td>{{ number_format($member->member_total_share, 2) }}</td>
                </tr>
                <tr>
                    <th>Total Capital Shares</th>
                    <td>{{ number_format($member->member_total_share_capital, 2) }}</td>
                </tr>
                <tr>
                    <th>Total FOSA Deposits</th>
                    <td>{{ number_format($member->member_total_fosa, 2) }}</td>
                </tr>
                <tr>
                    <th>Unpaid Loan (Principal)</th>
                    <td>{{ number_format($member->member_total_loan, 2) }}</td>
                </tr>
                <tr>
                    <th>Tied Shares - Others</th>
                    <td>{{ number_format($member->member_tied_shares, 2) }}</td>
                </tr>
                <tr>
                    <th>Tied Shares - Self</th>
                    <td>{{ number_format($member->member_tied_shares_self, 2) }}</td>
                </tr>
            </table>
        </div>
    </div>

    <h4>Guarantors for Outstanding Loans</h4>
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
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Guarantor Name</th>
                                <th>Sacco No.</th>
                                <th>Amount Guaranteed</th>
                                <th>Amount Freed</th>
                                <th>Amount Tied</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($loan->guarantors as $index => $guarantor)
                                @if(($guarantor->loan_guar_amount_guaranteed - $guarantor->loan_guar_amount_freed) > $threshold_amount)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td><a href="{{ route('changeGuarantors', ['member_id' => $guarantor->loan_guar_guarantor_id, 'guarantor_id' => $guarantor->loan_guar_id]) }}">{{ $guarantor->member_name }}</a></td>
                                        <td>{{ $guarantor->member_sacco_id }}</td>
                                        <td>{{ number_format($guarantor->loan_guar_amount_guaranteed, 2) }}</td>
                                        <td>{{ number_format($guarantor->loan_guar_amount_guaranteed - $guarantor->loan_guar_amount_freed, 2) }}</td>
                                        <td>
                                            <form action="{{ route('deleteGuarantor', ['member_id' => $member->member_id, 'guarantor_id' => $guarantor->loan_guar_id]) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this guarantor?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-danger btn-sm"><i class="fas fa-times"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endforeach

    <h4>Loans Guaranteed by {{ $member->member_name }}</h4>
    <table class="table table-bordered">
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
@endsection

