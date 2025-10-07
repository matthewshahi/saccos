@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center flex-wrap">
    <h1 class="mb-0 fw-bold text-primary">Guarantee Requests</h1>
    <div class="header-part-right mt-2 mt-sm-0">
        <ul class="list-inline mb-0">
            @auth
                <li class="list-inline-item text-muted">{{ Auth::user()->member_name }}</li>
            @endauth
            @isset($currentPeriod)
                <li class="list-inline-item">
                    <a href="{{ route('admin.periods') }}" class="text-decoration-none">{{ $currentPeriod->period_name }}</a>
                </li>
            @endisset
            <li class="list-inline-item">
                <i class="i-Full-Screen header-icon d-none d-sm-inline-block" data-fullscreen></i>
            </li>
        </ul>
    </div>
</div>

<hr class="mb-4"/>

<div class="container-fluid">

    {{-- ✅ Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

     {{-- ⚠️ Guarantor Responsibility Notice --}}
    <div class="alert alert-warning border-start border-4 border-warning shadow-sm mb-4" role="alert">
        <h5 class="fw-bold mb-2 text-dark">Important Notice to the Guarantor</h5>
        <p class="mb-1 text-dark small">
            By accepting to guarantee a loan, you commit to settle any outstanding balance of this loan in the event that the borrower fails to meet their repayment obligations. Your guaranteed amount may be recovered from your savings, deposits, or any other funds held within the SACCO should default occur.
        </p>
        <p class="mb-0 text-muted small">
            Please review the loan details carefully before confirming. Once accepted, your guarantee forms a binding financial commitment under SACCO regulations. However, this does not restrict your ability to apply for your own loans, provided you have the required guarantors or meet the lending criteria.
        </p>
    </div>
    {{-- 🔗 Quick Links --}}
    <div class="text-center mb-3">
        <small class="fw-semibold">
            <a href="{{ route('loans.guarantee.requests') }}" class="text-decoration-none me-2">Guarantee Requests</a> |
            <a href="{{ route('loans.pending.approval') }}" class="text-decoration-none ms-2">Loans Pending Approval</a>
        </small>
    </div>

    {{-- 📋 Guarantee Requests Table --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-light d-flex justify-content-between align-items-center">
            <h5 class="mb-0 fw-bold text-secondary">Loans Pending Guarantee Approval</h5>
        </div>

        <div class="card-body p-3">
            <div class="table-responsive">
                <table class="table table-striped table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Loan Taken By</th>
                            <th>Sacco ID</th>
                            <th>Loan Type</th>
                            <th class="text-end">Loan Amount</th>
                            <th class="text-end">Insurance</th>
                            <th class="text-end">Commission</th>
                            <th class="text-end">EMI</th>
                            <th>Payment Period</th>
                            <th>Top-Up</th>
                            <th class="text-end">You Guarantee</th>
                            <th class="text-center">Decline</th>
                            <th class="text-center">Accept</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($loans as $index => $loan)
                            <tr>
                                <td>{{ $index + 1 }}</td>
                                <td>{{ $loan->member_name }}</td>
                                <td>{{ $loan->member_sacco_id }}</td>
                                <td>{{ $loan->loan_type_name }}</td>
                                <td class="text-end">{{ number_format($loan->batch_trans_loan_amount, 2) }}</td>
                                <td class="text-end">{{ number_format($loan->batch_trans_insurance, 2) }}</td>
                                <td class="text-end">{{ number_format($loan->batch_trans_commission, 2) }}</td>
                                <td class="text-end">{{ number_format($loan->batch_trans_monthly_payment, 2) }}</td>
                                <td>{{ $loan->batch_trans_loan_duration }}</td>
                                <td>
                                    @if(is_numeric($loan->batch_trans_loan_to_top_up) && $loan->batch_trans_loan_to_top_up > 0)
                                        @php
                                            $topUpLoan = DB::table('sacco_loans')
                                                ->join('sacco_loan_types', 'sacco_loans.loan_loan_type', '=', 'sacco_loan_types.loan_type_id')
                                                ->where('loan_member', Auth::id())
                                                ->where('loan_id', $loan->batch_trans_loan_to_top_up)
                                                ->first();
                                        @endphp
                                        <span class="badge bg-info text-dark">
                                            {{ $topUpLoan->loan_type_name ?? 'N/A' }} (#{{ $loan->batch_trans_loan_to_top_up }})
                                        </span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    @php
                                        $guaranteedAmount = DB::table('sacco_loan_batch_trans_members')
                                            ->join('sacco_loan_batch_guarantors_members', 'sacco_loan_batch_trans_members.batch_trans_id', '=', 'sacco_loan_batch_guarantors_members.guarantors_loan_batch_trans_id')
                                            ->where('guarantors_guarantor_id', Auth::id())
                                            ->where('guarantors_loan_batch_trans_id', $loan->batch_trans_id)
                                            ->sum('guarantors_amount_guaranteed');
                                    @endphp
                                    {{ number_format($guaranteedAmount, 2) }}
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('loans.guarantee.requests', ['rid' => $loan->batch_trans_id]) }}" 
                                       class="btn btn-sm btn-outline-danger" 
                                       title="Decline this guarantee" 
                                       onclick="return confirm('Are you sure you want to decline this guarantee?')">
                                        <i class="i-Close"></i>
                                    </a>
                                </td>
                                <td class="text-center">
                                    <a href="{{ route('loans.guarantee.requests', ['yid' => $loan->batch_trans_id]) }}" 
                                       class="btn btn-sm btn-outline-success" 
                                       title="Accept to guarantee" 
                                       onclick="return confirm('Are you sure you want to accept to guarantee this loan?')">
                                        <i class="i-Yes"></i>
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="text-center text-muted py-3">No guarantee requests found.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<style>
    .breadcrumb h1 {
        font-size: 1.4rem;
    }
    .table th, .table td {
        vertical-align: middle !important;
    }
    .table-hover tbody tr:hover {
        background-color: #f8fafc;
    }
    .btn-sm i {
        font-size: 0.9rem;
    }
    .badge {
        font-size: 0.75rem;
    }
</style>
@endsection