@extends('layouts.app')

@section('content')
    @php
        $isAdmin = (int) (Auth::user()->member_position ?? 0) === 2;
        $deductionTypes = collect($deductionTypes ?? []);
        $applicationCharges = collect($applicationCharges ?? []);
    @endphp

    <div class="container">
        <div class="row mb-4">
            <div class="col-md-12">
                <h3 class="text-center">Loans Pending Approval</h3>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                {!! session('success') !!}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{!! $error !!}</li>
                    @endforeach
                </ul>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        <div class="row mb-3">
            <div class="col-md-12">
                <form method="GET" action="{{ route('loans.pending.approval') }}">
                    <div class="row align-items-center">
                        <div class="col-md-6 mb-2 mb-md-0">
                            <div class="input-group">
                                <input type="text" name="search" class="form-control" placeholder="Search..."
                                    value="{{ request()->search }}">
                            </div>
                        </div>

                        <div class="col-md-4 mb-2 mb-md-0">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="pendingLoansOnly" name="pending"
                                    value="1" {{ request()->pending ? 'checked' : '' }}>
                                <label class="form-check-label" for="pendingLoansOnly">Pending Loans Only</label>
                            </div>
                        </div>

                        <div class="col-md-2 text-md-end">
                            <button class="btn btn-primary w-100" type="submit">Search</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table id="memberContributionsTable" class="table table-striped table-hover text-start">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th class="text-start">Applicant Name</th>
                                <th class="text-start">Member Number</th>
                                <th class="text-start">Phone</th>
                                <th class="text-start">National ID</th>
                                <th class="text-end">Loan Amount</th>
                                <th>Loan Type</th>
                                <th>Loan Category</th>
                                <th>Doc No</th>
                                <th class="text-end">EMI</th>
                                <th class="text-end">Insurance</th>
                                <th class="text-end">Commission</th>
                                <th>Period to Pay</th>
                                <th>Date Applied</th>
                                <th>Updated</th>

                                @if ($isAdmin)
                                    <th>Adjust Charges</th>
                                    <th>Edit</th>
                                @else
                                    <th>Edit</th>
                                @endif

                                <th>PDF</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($loans as $index => $loan)
                                <tr>
                                    <td>{{ $loans->firstItem() + $index }}</td>
                                    <td class="text-start">{{ $loan->member_name }}</td>
                                    <td class="text-start">{{ $loan->member_sacco_id }}</td>
                                    <td class="text-start">{{ $loan->member_phone_no }}</td>
                                    <td class="text-start">{{ $loan->member_national_id }}</td>
                                    <td class="text-end">{{ number_format((float) $loan->batch_trans_loan_amount, 2) }}</td>
                                    <td>{{ $loan->loan_type_name }}</td>
                                    <td>{{ $loan->loan_category_name }}</td>
                                    <td style="min-width: 180px;">
                                        <div class="docno-edit-wrap">
                                            <input type="text" class="form-control form-control-sm js-docno-input"
                                                value="{{ $loan->batch_trans_doc_no ?? '' }}"
                                                data-url="{{ route('loans.pending.approval.docno.update', $loan->batch_trans_id) }}"
                                                data-loan-id="{{ $loan->batch_trans_id }}" placeholder="Enter doc no"
                                                autocomplete="off">
                                            <small class="text-muted d-block mt-1 js-docno-status"></small>
                                        </div>
                                    </td>
                                    <td class="text-end">{{ number_format((float) $loan->batch_trans_monthly_payment, 2) }}
                                    </td>
                                    <td class="text-end">{{ number_format((float) $loan->batch_trans_insurance, 2) }}</td>
                                    <td class="text-end">{{ number_format((float) $loan->batch_trans_commission, 2) }}</td>
                                    <td>{{ $loan->batch_trans_loan_duration }} months</td>
                                    <td>{{ \Carbon\Carbon::parse($loan->batch_trans_on)->format('d/m/Y') }}</td>
                                    <td>
                                        @if ($loan->batch_trans_deleted == 'Y')
                                            <span class="badge bg-danger">Rejected</span>
                                        @elseif ($loan->batch_trans_updated == 'Y')
                                            <span class="badge bg-success">Updated</span>
                                        @else
                                            <span class="badge bg-warning">Pending</span>
                                        @endif
                                    </td>


                                    @php
                                        $isOwner =
                                            (int) (Auth::user()->member_id ?? 0) ===
                                            (int) ($loan->batch_trans_member_id ?? 0);
                                        $isOpenLoan =
                                            $loan->batch_trans_updated == 'N' && $loan->batch_trans_deleted != 'Y';
                                    @endphp

                                    @if ($isAdmin)
                                        <td>
                                            @if ($isOpenLoan)
                                                <button type="button" class="btn btn-outline-primary btn-sm"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#chargesModal{{ $loan->batch_trans_id }}">
                                                    Adjust Charges
                                                </button>
                                            @else
                                                <span class="text-muted small">Closed</span>
                                            @endif
                                        </td>

                                        <td>
                                            @if ($isOpenLoan && $isOwner)
                                                <a href="{{ route('loans.pending.approval.selfedit', ['id' => $loan->batch_trans_id]) }}"
                                                    class="btn btn-primary btn-sm">
                                                    Edit
                                                </a>
                                            @elseif (!$isOwner)
                                                <span class="text-muted small">Not owner</span>
                                            @else
                                                <span class="text-muted small">Closed</span>
                                            @endif
                                        </td>
                                    @else
                                        <td>
                                            @if ($isOpenLoan)
                                                <a href="{{ route('loans.pending.approval.selfedit', ['id' => $loan->batch_trans_id]) }}"
                                                    class="btn btn-primary btn-sm">
                                                    Edit
                                                </a>
                                            @else
                                                <span class="text-muted small">Closed</span>
                                            @endif
                                        </td>
                                    @endif

                                    <td>
                                        <a href="{{ route('loans.pending.getPDF', ['loanId' => $loan->batch_trans_id]) }}"
                                            class="btn btn-secondary btn-sm" target="_blank">
                                            PDF
                                        </a>
                                    </td>

                                    <td>
                                        <button class="btn btn-info btn-sm" data-bs-toggle="modal"
                                            data-bs-target="#loanModal{{ $loan->batch_trans_id }}">
                                            Details
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $isAdmin ? 19 : 18 }}" class="text-center">No loans pending approval.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="d-flex justify-content-center mt-3">
                    {{ $loans->links('pagination::bootstrap-4') }}
                </div>
            </div>
        </div>

        {{-- DETAILS MODALS --}}
        @foreach ($loans as $loan)
            <div class="modal fade" id="loanModal{{ $loan->batch_trans_id }}" tabindex="-1"
                aria-labelledby="loanModalLabel{{ $loan->batch_trans_id }}" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-primary text-white">
                            <h5 class="modal-title" id="loanModalLabel{{ $loan->batch_trans_id }}">Loan Details -
                                {{ $loan->member_name }}</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body">
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <h6 class="text-primary">Applicant Information</h6>
                                    <ul class="list-unstyled">
                                        <li><strong>Name:</strong> {{ $loan->member_name }}</li>
                                        <li><strong>Phone:</strong> {{ $loan->member_phone_no }}</li>
                                        <li><strong>National ID:</strong> {{ $loan->member_national_id }}</li>
                                    </ul>
                                </div>

                                <div class="col-md-6">
                                    <h6 class="text-primary">Loan Information</h6>
                                    <ul class="list-unstyled">
                                        <li><strong>Loan Amount:</strong>
                                            {{ number_format((float) $loan->batch_trans_loan_amount, 2) }}</li>
                                        <li><strong>Loan Type:</strong> {{ $loan->loan_type_name }}</li>
                                        <li><strong>Duration:</strong> {{ $loan->batch_trans_loan_duration }} months</li>
                                        <li><strong>Monthly Payment:</strong>
                                            {{ number_format((float) $loan->batch_trans_monthly_payment, 2) }}</li>
                                        <li><strong>Top-Up Amount:</strong>
                                            {{ number_format((float) ($loan->batch_trans_loan_to_top_up_amount ?? 0), 2) }}
                                        </li>
                                        <li><strong>Commission:</strong>
                                            {{ number_format((float) $loan->batch_trans_commission, 2) }}</li>
                                        <li><strong>Insurance:</strong>
                                            {{ number_format((float) $loan->batch_trans_insurance, 2) }}</li>
                                    </ul>
                                </div>
                            </div>

                            <h6 class="text-primary">Application Details</h6>
                            <ul class="list-unstyled mb-3">
                                <li><strong>Applied On:</strong> {{ $loan->batch_trans_on }}</li>
                                <li><strong>IP Address:</strong> {{ $loan->batch_trans_ip }}</li>
                            </ul>

                            <h6 class="text-primary mb-3">Guarantors</h6>
                            <table class="table table-bordered table-sm">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Guarantor Name</th>
                                        <th class="text-end">Amount Guaranteed</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $guarantors_names = explode('|', $loan->guarantors_names ?? '');
                                        $guarantors_amounts = explode('|', $loan->guarantors_amounts ?? '');
                                        $guarantors_statuses = explode('|', $loan->guarantors_approval_status ?? '');
                                    @endphp

                                    @foreach ($guarantors_names as $index => $name)
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $name }}</td>
                                            <td class="text-end">
                                                {{ is_numeric($guarantors_amounts[$index] ?? null) ? number_format((float) $guarantors_amounts[$index], 2) : '0.00' }}
                                            </td>
                                            <td>
                                                <span
                                                    class="badge {{ isset($guarantors_statuses[$index]) && $guarantors_statuses[$index] == 'Y' ? 'bg-success' : 'bg-warning' }}">
                                                    {{ isset($guarantors_statuses[$index]) && $guarantors_statuses[$index] == 'Y' ? 'Accepted' : 'Pending' }}
                                                </span>
                                            </td>
                                        </tr>
                                    @endforeach

                                    @if (empty($guarantors_names[0]))
                                        <tr>
                                            <td colspan="4" class="text-center text-muted">No guarantors available.
                                            </td>
                                        </tr>
                                    @endif
                                </tbody>
                            </table>

                            {{-- CREDIT COMMITTEE REVIEW --}}
                            @php
                                $creditCommittee = $loan->credit_committee ?? null;

                                $committeeStatus = $creditCommittee['status'] ?? null;

                                $committeeStatusClass = match ($committeeStatus) {
                                    'APPROVED' => 'bg-success',
                                    'DECLINED' => 'bg-danger',
                                    'PENDING' => 'bg-warning text-dark',
                                    'NOT_REQUIRED' => 'bg-secondary',
                                    'CONFIGURATION_ERROR' => 'bg-danger',
                                    default => 'bg-secondary',
                                };

                                $committeeStatusText = match ($committeeStatus) {
                                    'APPROVED' => 'Committee Approved',
                                    'DECLINED' => 'Blocked',
                                    'PENDING' => 'Awaiting Approval',
                                    'NOT_REQUIRED' => 'Not Required',
                                    'CONFIGURATION_ERROR' => 'Configuration Error',
                                    default => 'Unknown',
                                };
                            @endphp

                            @if ($creditCommittee)
                                <hr class="my-4">

                                <div class="credit-committee-section"
                                    id="creditCommitteeSection{{ $loan->batch_trans_id }}"
                                    data-loan-id="{{ $loan->batch_trans_id }}">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="text-primary mb-0">
                                            Credit Committee Review
                                        </h6>

                                        <span id="ccStatusBadge{{ $loan->batch_trans_id }}"
                                            class="badge {{ $committeeStatusClass }}">
                                            {{ $committeeStatusText }}
                                        </span>
                                    </div>

                                    @if (!$creditCommittee['required'])
                                        <div class="alert alert-secondary py-2 mb-3">
                                            Credit Committee approval is not required for this SACCO.
                                        </div>
                                    @else
                                        <div id="ccSummary{{ $loan->batch_trans_id }}"
                                            class="alert {{ $creditCommittee['can_final_approve'] ? 'alert-success' : 'alert-warning' }} py-2 mb-3">
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <strong>Required:</strong>
                                                    <span id="ccRequired{{ $loan->batch_trans_id }}">
                                                        {{ $creditCommittee['required_approvals'] }}
                                                    </span>
                                                </div>

                                                <div class="col-md-3">
                                                    <strong>Approved:</strong>
                                                    <span id="ccYesCount{{ $loan->batch_trans_id }}">
                                                        {{ $creditCommittee['yes_count'] }}
                                                    </span>
                                                </div>

                                                <div class="col-md-3">
                                                    <strong>Not Approved:</strong>
                                                    <span id="ccNoCount{{ $loan->batch_trans_id }}">
                                                        {{ $creditCommittee['no_count'] }}
                                                    </span>
                                                </div>

                                                <div class="col-md-3">
                                                    <strong>Pending:</strong>
                                                    <span id="ccPendingCount{{ $loan->batch_trans_id }}">
                                                        {{ $creditCommittee['pending_count'] }}
                                                    </span>
                                                </div>
                                            </div>

                                            <div id="ccBlockingMessage{{ $loan->batch_trans_id }}"
                                                class="small mt-2 {{ $creditCommittee['can_final_approve'] ? 'text-success' : 'text-danger' }}">
                                                @if ($creditCommittee['can_final_approve'])
                                                    Credit Committee approval conditions have been satisfied.
                                                @else
                                                    {{ $creditCommittee['blocking_message'] }}
                                                @endif
                                            </div>
                                        </div>
                                    @endif

                                    @if (!empty($creditCommittee['members']))
                                        <div class="table-responsive">
                                            <table class="table table-bordered table-sm align-middle mb-0">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th style="width: 45px;">#</th>
                                                        <th>Committee Member</th>
                                                        <th>Role</th>
                                                        <th style="width: 130px;">Decision</th>
                                                        <th style="width: 230px;">Action</th>
                                                    </tr>
                                                </thead>

                                                <tbody>
                                                    @foreach ($creditCommittee['members'] as $committeeIndex => $committeeMember)
                                                        @php
                                                            $committeeDecision = $committeeMember['decision'] ?? null;
                                                            $committeeMemberId = (int) $committeeMember['member_id'];
                                                            $canVote = (bool) ($committeeMember['can_vote'] ?? false);

                                                            $decisionClass = match ($committeeDecision) {
                                                                'Y' => 'bg-success',
                                                                'N' => 'bg-danger',
                                                                default => 'bg-secondary',
                                                            };

                                                            $decisionText = match ($committeeDecision) {
                                                                'Y' => 'Approved',
                                                                'N' => 'Not Approved',
                                                                default => 'Pending',
                                                            };
                                                        @endphp

                                                        <tr
                                                            id="ccMemberRow{{ $loan->batch_trans_id }}_{{ $committeeMemberId }}">
                                                            <td>
                                                                {{ $committeeIndex + 1 }}
                                                            </td>

                                                            <td>
                                                                <strong>{{ $committeeMember['member_name'] }}</strong>

                                                                @if (!empty($committeeMember['member_sacco_id']))
                                                                    <small class="text-muted d-block">
                                                                        {{ $committeeMember['member_sacco_id'] }}
                                                                    </small>
                                                                @endif
                                                            </td>

                                                            <td>
                                                                {{ $committeeMember['classification_name'] }}
                                                            </td>

                                                            <td>
                                                                <span
                                                                    id="ccDecisionBadge{{ $loan->batch_trans_id }}_{{ $committeeMemberId }}"
                                                                    class="badge {{ $decisionClass }}">
                                                                    {{ $decisionText }}
                                                                </span>

                                                                @if (!empty($committeeMember['decided_at']))
                                                                    <small
                                                                        id="ccDecisionTime{{ $loan->batch_trans_id }}_{{ $committeeMemberId }}"
                                                                        class="text-muted d-block mt-1">
                                                                        {{ $committeeMember['decided_at'] }}
                                                                    </small>
                                                                @else
                                                                    <small
                                                                        id="ccDecisionTime{{ $loan->batch_trans_id }}_{{ $committeeMemberId }}"
                                                                        class="text-muted d-block mt-1"></small>
                                                                @endif
                                                            </td>

                                                            <td>
                                                                @if ($canVote && $loan->batch_trans_updated == 'N' && $loan->batch_trans_deleted != 'Y')
                                                                    <div class="d-flex gap-2">
                                                                        <button type="button"
                                                                            id="ccYesButton{{ $loan->batch_trans_id }}_{{ $committeeMemberId }}"
                                                                            class="btn btn-sm {{ $committeeDecision === 'Y' ? 'btn-success' : 'btn-outline-success' }} js-credit-committee-decision"
                                                                            data-loan-id="{{ $loan->batch_trans_id }}"
                                                                            data-member-id="{{ $committeeMemberId }}"
                                                                            data-decision="Y"
                                                                            data-url="{{ route('loans.pending.approval.credit_committee.decision', ['id' => $loan->batch_trans_id]) }}">
                                                                            Approve
                                                                        </button>

                                                                        <button type="button"
                                                                            id="ccNoButton{{ $loan->batch_trans_id }}_{{ $committeeMemberId }}"
                                                                            class="btn btn-sm {{ $committeeDecision === 'N' ? 'btn-danger' : 'btn-outline-danger' }} js-credit-committee-decision"
                                                                            data-loan-id="{{ $loan->batch_trans_id }}"
                                                                            data-member-id="{{ $committeeMemberId }}"
                                                                            data-decision="N"
                                                                            data-url="{{ route('loans.pending.approval.credit_committee.decision', ['id' => $loan->batch_trans_id]) }}">
                                                                            Do Not Approve
                                                                        </button>
                                                                    </div>

                                                                    <small
                                                                        id="ccVoteStatus{{ $loan->batch_trans_id }}_{{ $committeeMemberId }}"
                                                                        class="d-block mt-1"></small>
                                                                @else
                                                                    <span class="text-muted small">
                                                                        Read only
                                                                    </span>
                                                                @endif
                                                            </td>
                                                        </tr>
                                                    @endforeach
                                                </tbody>
                                            </table>
                                        </div>
                                    @elseif($creditCommittee['required'])
                                        <div class="alert alert-danger mb-0">
                                            No active Credit Committee members are configured.
                                        </div>
                                    @endif
                                </div>
                            @endif
                        </div>

                        <div class="modal-footer">
                            @if ($loan->batch_trans_updated == 'N' && $loan->batch_trans_deleted != 'Y')
                                @if ($isAdmin)
                                    @php
                                        $creditCommittee = $loan->credit_committee ?? null;

                                        $committeeAllowsFinalApproval =
                                            $creditCommittee !== null &&
                                            (bool) ($creditCommittee['can_final_approve'] ?? false);

                                        $committeeBlockingMessage =
                                            $creditCommittee['blocking_message'] ??
                                            'Credit Committee approval status is not available.';
                                    @endphp

                                    {{-- FINAL APPROVAL --}}
                                    <form id="approveForm{{ $loan->batch_trans_id }}"
                                        action="{{ route('loans.approve.self', ['loanId' => $loan->batch_trans_id]) }}"
                                        method="POST" style="display: inline;">
                                        @csrf

                                        <button type="button" id="finalApproveButton{{ $loan->batch_trans_id }}"
                                            class="btn btn-success"
                                            onclick="confirmAction('approve', '{{ $loan->batch_trans_id }}')"
                                            {{ !$committeeAllowsFinalApproval ? 'disabled' : '' }}>
                                            Approve
                                        </button>
                                    </form>

                                    {{-- FINAL REJECTION - INDEPENDENT OF COMMITTEE --}}
                                    <form id="rejectForm{{ $loan->batch_trans_id }}"
                                        action="{{ route('loans.reject', ['id' => $loan->batch_trans_id]) }}"
                                        method="POST" style="display: inline;">
                                        @csrf

                                        <button type="button" class="btn btn-danger"
                                            onclick="confirmAction('reject', '{{ $loan->batch_trans_id }}')">
                                            Reject
                                        </button>
                                    </form>

                                    <small id="finalApprovalBlockMessage{{ $loan->batch_trans_id }}"
                                        class="{{ $committeeAllowsFinalApproval ? 'text-success' : 'text-danger' }} d-block mt-1"
                                        style="max-width: 360px;">
                                        @if (!$committeeAllowsFinalApproval)
                                            {{ $committeeBlockingMessage }}
                                        @endif
                                    </small>
                                @endif
                            @else
                                <span class="text-muted">This application is closed or deleted.</span>
                            @endif

                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        {{-- ADMIN-ONLY CHARGES MODALS --}}
        @if ($isAdmin)
            @foreach ($loans as $loan)
                @php
                    $savedCharges = collect($applicationCharges[$loan->batch_trans_id] ?? []);
                    $nextIndex = $savedCharges->count() > 0 ? $savedCharges->count() : 1;
                @endphp

                <div class="modal fade" id="chargesModal{{ $loan->batch_trans_id }}" tabindex="-1"
                    aria-labelledby="chargesModalLabel{{ $loan->batch_trans_id }}" aria-hidden="true">
                    <div class="modal-dialog modal-xl modal-dialog-scrollable">
                        <form
                            action="{{ route('loans.selfservice.adjust.charges.save', ['id' => $loan->batch_trans_id]) }}"
                            method="POST">
                            @csrf

                            <div class="modal-content">
                                <div class="modal-header bg-dark text-white">
                                    <h5 class="modal-title" id="chargesModalLabel{{ $loan->batch_trans_id }}"
                                        style="color: white;">
                                        Adjust Charges - {{ $loan->member_name }}
                                    </h5>
                                    <button type="button" class="btn-close btn-close-white"
                                        data-bs-dismiss="modal"></button>
                                </div>

                                <div class="modal-body">
                                    <div class="row mb-3">
                                        <div class="col-md-4">
                                            <strong>Application ID:</strong> {{ $loan->batch_trans_id }}
                                        </div>
                                        <div class="col-md-4">
                                            <strong>Member:</strong> {{ $loan->member_name }}
                                            ({{ $loan->member_sacco_id }})
                                        </div>
                                        <div class="col-md-4 text-md-end">
                                            <strong>Requested Amount:</strong>
                                            {{ number_format((float) $loan->batch_trans_loan_amount, 2) }}
                                        </div>
                                    </div>

                                    <div class="alert alert-info">
                                        Charges here are tied to this exact loan application.
                                        Charges with a setup default value above zero are locked.
                                        Charges with blank or zero default value can be entered here.
                                    </div>

                                    <div class="table-responsive">
                                        <table class="table table-bordered table-sm align-middle">
                                            <thead class="table-light">
                                                <tr>
                                                    <th style="min-width: 220px;">Charge Type</th>
                                                    <th style="min-width: 100px;">Code</th>
                                                    <th style="min-width: 100px;">Value Type</th>
                                                    <th style="min-width: 120px;">Default Value</th>
                                                    <th style="min-width: 160px;">Effect</th>
                                                    <th style="min-width: 170px;">Value To Use</th>
                                                    <th style="min-width: 220px;">Notes</th>
                                                    <th style="width: 70px;">Remove</th>
                                                </tr>
                                            </thead>

                                            <tbody id="chargeRows{{ $loan->batch_trans_id }}"
                                                data-next-index="{{ $nextIndex }}">
                                                @forelse($savedCharges as $index => $savedCharge)
                                                    @php
                                                        $selectedType = $deductionTypes->firstWhere(
                                                            'deduction_type_id',
                                                            $savedCharge->batch_trans_deduction_deduction_type_id,
                                                        );
                                                        $typeDefaultValue =
                                                            (float) ($selectedType->deduction_type_default_value ?? 0);
                                                        $typeValueType = strtoupper(
                                                            (string) ($savedCharge->batch_trans_deduction_value_type ??
                                                                ''),
                                                        );
                                                        $typeEffect = strtoupper(
                                                            (string) ($savedCharge->batch_trans_deduction_effect ?? ''),
                                                        );
                                                        $existingValue = '';

                                                        if ($typeDefaultValue > 0) {
                                                            $existingValue = $typeDefaultValue;
                                                        } else {
                                                            if ($typeValueType === 'PERCENT') {
                                                                preg_match(
                                                                    '/at\s+([0-9.]+)%/i',
                                                                    $savedCharge->batch_trans_deduction_description ??
                                                                        '',
                                                                    $matches,
                                                                );
                                                                $existingValue = $matches[1] ?? '';
                                                            } else {
                                                                $existingValue =
                                                                    (float) ($savedCharge->batch_trans_deduction_amount ??
                                                                        0);
                                                            }
                                                        }

                                                        $isLocked = $typeDefaultValue > 0;
                                                    @endphp

                                                    <tr class="charge-row" data-existing-value="{{ $existingValue }}">
                                                        <td>
                                                            <select class="form-control charge-type-select"
                                                                name="charges[{{ $index }}][deduction_type_id]"
                                                                data-loan-id="{{ $loan->batch_trans_id }}">
                                                                <option value="">Select charge</option>
                                                                @foreach ($deductionTypes as $type)
                                                                    <option value="{{ $type->deduction_type_id }}"
                                                                        data-code="{{ $type->deduction_type_code }}"
                                                                        data-value-type="{{ strtoupper($type->deduction_type_value_type) }}"
                                                                        data-default="{{ (float) $type->deduction_type_default_value }}"
                                                                        data-effect="{{ strtoupper($type->deduction_type_effect) }}"
                                                                        data-name="{{ $type->deduction_type_name }}"
                                                                        {{ (int) $type->deduction_type_id === (int) $savedCharge->batch_trans_deduction_deduction_type_id ? 'selected' : '' }}>
                                                                        {{ $type->deduction_type_name }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </td>

                                                        <td>
                                                            <input type="text" class="form-control charge-code"
                                                                value="{{ $savedCharge->batch_trans_deduction_code }}"
                                                                readonly>
                                                        </td>

                                                        <td>
                                                            <input type="text" class="form-control charge-value-type"
                                                                value="{{ $savedCharge->batch_trans_deduction_value_type }}"
                                                                readonly>
                                                        </td>

                                                        <td>
                                                            <input type="text"
                                                                class="form-control charge-default-value"
                                                                value="{{ $typeDefaultValue > 0 ? $typeDefaultValue : '0' }}"
                                                                readonly>
                                                        </td>

                                                        <td>
                                                            <span
                                                                class="badge charge-effect-badge {{ $typeEffect === 'ADD_TO_LOAN' ? 'bg-primary' : ($typeEffect === 'DEDUCT_FROM_DISBURSEMENT' ? 'bg-danger' : 'bg-secondary') }}">
                                                                {{ $typeEffect === 'ADD_TO_LOAN' ? 'Adds to loan' : ($typeEffect === 'DEDUCT_FROM_DISBURSEMENT' ? 'Reduces disbursement' : 'Not selected') }}
                                                            </span>
                                                        </td>

                                                        <td>
                                                            <input type="number" step="0.0001" min="0"
                                                                class="form-control charge-input-value {{ $isLocked ? 'bg-light' : '' }}"
                                                                name="charges[{{ $index }}][value]"
                                                                value="{{ $existingValue }}"
                                                                {{ $isLocked ? 'readonly' : '' }}>
                                                        </td>

                                                        <td>
                                                            <input type="text"
                                                                class="form-control charge-comment-input"
                                                                name="charges[{{ $index }}][comment]"
                                                                value="" placeholder="Optional note">
                                                            <small class="text-muted charge-help-text d-block mt-1">
                                                                @if ($isLocked)
                                                                    {{ $typeValueType === 'PERCENT' ? 'Locked percentage from setup. Admin cannot change it here.' : 'Locked fixed amount from setup. Admin cannot change it here.' }}
                                                                @else
                                                                    {{ $typeValueType === 'PERCENT' ? 'Saved percentage loaded. You may adjust it here.' : 'Saved amount loaded. You may adjust it here.' }}
                                                                @endif
                                                            </small>
                                                        </td>

                                                        <td class="text-center">
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-danger remove-charge-row">&times;</button>
                                                        </td>
                                                    </tr>
                                                @empty
                                                    <tr class="charge-row" data-existing-value="">
                                                        <td>
                                                            <select class="form-control charge-type-select"
                                                                name="charges[0][deduction_type_id]"
                                                                data-loan-id="{{ $loan->batch_trans_id }}">
                                                                <option value="">Select charge</option>
                                                                @foreach ($deductionTypes as $type)
                                                                    <option value="{{ $type->deduction_type_id }}"
                                                                        data-code="{{ $type->deduction_type_code }}"
                                                                        data-value-type="{{ strtoupper($type->deduction_type_value_type) }}"
                                                                        data-default="{{ (float) $type->deduction_type_default_value }}"
                                                                        data-effect="{{ strtoupper($type->deduction_type_effect) }}"
                                                                        data-name="{{ $type->deduction_type_name }}">
                                                                        {{ $type->deduction_type_name }}
                                                                    </option>
                                                                @endforeach
                                                            </select>
                                                        </td>
                                                        <td><input type="text" class="form-control charge-code"
                                                                readonly></td>
                                                        <td><input type="text" class="form-control charge-value-type"
                                                                readonly></td>
                                                        <td><input type="text"
                                                                class="form-control charge-default-value" readonly></td>
                                                        <td><span class="badge bg-secondary charge-effect-badge">Not
                                                                selected</span></td>
                                                        <td>
                                                            <input type="number" step="0.0001" min="0"
                                                                class="form-control charge-input-value"
                                                                name="charges[0][value]">
                                                        </td>
                                                        <td>
                                                            <input type="text"
                                                                class="form-control charge-comment-input"
                                                                name="charges[0][comment]" placeholder="Optional note">
                                                            <small class="text-muted charge-help-text d-block mt-1">Select
                                                                a charge type first.</small>
                                                        </td>
                                                        <td class="text-center">
                                                            <button type="button"
                                                                class="btn btn-sm btn-outline-danger remove-charge-row">&times;</button>
                                                        </td>
                                                    </tr>
                                                @endforelse
                                            </tbody>
                                        </table>
                                    </div>

                                    <div class="d-flex justify-content-between mt-3">
                                        <button type="button" class="btn btn-outline-primary add-charge-row-btn"
                                            data-loan-id="{{ $loan->batch_trans_id }}">
                                            Add Another Charge
                                        </button>

                                        <div class="text-end">
                                            <small class="text-muted d-block">Existing active charges are loaded
                                                here.</small>
                                            <small class="text-muted d-block">Rows removed here will disappear on
                                                save.</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary"
                                        data-bs-dismiss="modal">Close</button>
                                    <button type="submit" class="btn btn-primary">Save Charges</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
    <style>
        .docno-edit-wrap .js-docno-status {
            min-height: 16px;
            font-size: 11px;
        }
    </style>

    <script>
        function confirmAction(action, loanId) {
            if (action === 'approve') {
                const approveButton =
                    document.getElementById(`finalApproveButton${loanId}`);

                if (approveButton && approveButton.disabled) {
                    return;
                }
            }

            const actionText =
                action === 'approve' ?
                'Approve' :
                'Reject';

            const confirmation = confirm(
                `Are you sure you want to ${actionText} this loan application? This action is irreversible.`
            );

            if (!confirmation) {
                return;
            }

            if (action === 'approve') {
                document
                    .getElementById(`approveForm${loanId}`)
                    .submit();
            }

            if (action === 'reject') {
                document
                    .getElementById(`rejectForm${loanId}`)
                    .submit();
            }
        }
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const csrfToken = '{{ csrf_token() }}';

            function committeeStatusPresentation(status) {
                switch (status) {
                    case 'APPROVED':
                        return {
                            text: 'Committee Approved',
                                className: 'badge bg-success'
                        };

                    case 'DECLINED':
                        return {
                            text: 'Blocked',
                                className: 'badge bg-danger'
                        };

                    case 'PENDING':
                        return {
                            text: 'Awaiting Approval',
                                className: 'badge bg-warning text-dark'
                        };

                    case 'NOT_REQUIRED':
                        return {
                            text: 'Not Required',
                                className: 'badge bg-secondary'
                        };

                    case 'CONFIGURATION_ERROR':
                        return {
                            text: 'Configuration Error',
                                className: 'badge bg-danger'
                        };

                    default:
                        return {
                            text: 'Unknown',
                                className: 'badge bg-secondary'
                        };
                }
            }

            function updateCommitteeState(loanId, state) {
                if (!state) {
                    return;
                }

                const yesCount =
                    document.getElementById(`ccYesCount${loanId}`);

                const noCount =
                    document.getElementById(`ccNoCount${loanId}`);

                const pendingCount =
                    document.getElementById(`ccPendingCount${loanId}`);

                const requiredCount =
                    document.getElementById(`ccRequired${loanId}`);

                if (yesCount) {
                    yesCount.textContent =
                        state.yes_count ?? 0;
                }

                if (noCount) {
                    noCount.textContent =
                        state.no_count ?? 0;
                }

                if (pendingCount) {
                    pendingCount.textContent =
                        state.pending_count ?? 0;
                }

                if (requiredCount) {
                    requiredCount.textContent =
                        state.required_approvals ?? 0;
                }

                /*
                |--------------------------------------------------------------------------
                | Update committee status badge
                |--------------------------------------------------------------------------
                */

                const statusBadge =
                    document.getElementById(
                        `ccStatusBadge${loanId}`
                    );

                if (statusBadge) {
                    const presentation =
                        committeeStatusPresentation(
                            state.status
                        );

                    statusBadge.className =
                        presentation.className;

                    statusBadge.textContent =
                        presentation.text;
                }

                /*
                |--------------------------------------------------------------------------
                | Update committee member rows
                |--------------------------------------------------------------------------
                */

                if (Array.isArray(state.members)) {
                    state.members.forEach(function(member) {
                        const memberId =
                            parseInt(member.member_id, 10);

                        const badge =
                            document.getElementById(
                                `ccDecisionBadge${loanId}_${memberId}`
                            );

                        const yesButton =
                            document.getElementById(
                                `ccYesButton${loanId}_${memberId}`
                            );

                        const noButton =
                            document.getElementById(
                                `ccNoButton${loanId}_${memberId}`
                            );

                        const timeElement =
                            document.getElementById(
                                `ccDecisionTime${loanId}_${memberId}`
                            );

                        if (badge) {
                            if (member.decision === 'Y') {
                                badge.className =
                                    'badge bg-success';

                                badge.textContent =
                                    'Approved';
                            } else if (member.decision === 'N') {
                                badge.className =
                                    'badge bg-danger';

                                badge.textContent =
                                    'Not Approved';
                            } else {
                                badge.className =
                                    'badge bg-secondary';

                                badge.textContent =
                                    'Pending';
                            }
                        }

                        if (timeElement) {
                            timeElement.textContent =
                                member.decided_at ?? '';
                        }

                        if (yesButton) {
                            yesButton.className =
                                member.decision === 'Y' ?
                                'btn btn-sm btn-success js-credit-committee-decision' :
                                'btn btn-sm btn-outline-success js-credit-committee-decision';
                        }

                        if (noButton) {
                            noButton.className =
                                member.decision === 'N' ?
                                'btn btn-sm btn-danger js-credit-committee-decision' :
                                'btn btn-sm btn-outline-danger js-credit-committee-decision';
                        }
                    });
                }

                /*
                |--------------------------------------------------------------------------
                | Update summary
                |--------------------------------------------------------------------------
                */

                const summary =
                    document.getElementById(
                        `ccSummary${loanId}`
                    );

                const blockingMessage =
                    document.getElementById(
                        `ccBlockingMessage${loanId}`
                    );

                if (summary) {
                    summary.className =
                        state.can_final_approve ?
                        'alert alert-success py-2 mb-3' :
                        'alert alert-warning py-2 mb-3';
                }

                if (blockingMessage) {
                    if (state.can_final_approve) {
                        blockingMessage.className =
                            'small mt-2 text-success';

                        blockingMessage.textContent =
                            'Credit Committee approval conditions have been satisfied.';
                    } else {
                        blockingMessage.className =
                            'small mt-2 text-danger';

                        blockingMessage.textContent =
                            state.blocking_message ||
                            'Credit Committee approval requirements have not been satisfied.';
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Final SACCO approval button
                |--------------------------------------------------------------------------
                */

                const finalApproveButton =
                    document.getElementById(
                        `finalApproveButton${loanId}`
                    );

                const finalBlockMessage =
                    document.getElementById(
                        `finalApprovalBlockMessage${loanId}`
                    );

                if (finalApproveButton) {
                    finalApproveButton.disabled = !state.can_final_approve;
                }

                if (finalBlockMessage) {
                    if (state.can_final_approve) {
                        finalBlockMessage.className =
                            'text-success d-block mt-1';

                        finalBlockMessage.textContent =
                            'Credit Committee approval complete.';
                    } else {
                        finalBlockMessage.className =
                            'text-danger d-block mt-1';

                        finalBlockMessage.textContent =
                            state.blocking_message ||
                            'Credit Committee approval requirements have not been satisfied.';
                    }
                }
            }

            document
                .querySelectorAll(
                    '.js-credit-committee-decision'
                )
                .forEach(function(button) {
                    button.addEventListener(
                        'click',
                        async function() {
                            const loanId =
                                this.dataset.loanId;

                            const memberId =
                                this.dataset.memberId;

                            const decision =
                                this.dataset.decision;

                            const url =
                                this.dataset.url;

                            const yesButton =
                                document.getElementById(
                                    `ccYesButton${loanId}_${memberId}`
                                );

                            const noButton =
                                document.getElementById(
                                    `ccNoButton${loanId}_${memberId}`
                                );

                            const statusElement =
                                document.getElementById(
                                    `ccVoteStatus${loanId}_${memberId}`
                                );

                            if (!url) {
                                return;
                            }

                            const wording =
                                decision === 'Y' ?
                                'approve' :
                                'not approve';

                            if (
                                !confirm(
                                    `Are you sure you want to ${wording} this loan application?`
                                )
                            ) {
                                return;
                            }

                            if (yesButton) {
                                yesButton.disabled = true;
                            }

                            if (noButton) {
                                noButton.disabled = true;
                            }

                            if (statusElement) {
                                statusElement.className =
                                    'd-block mt-1 text-muted';

                                statusElement.textContent =
                                    'Saving...';
                            }

                            try {
                                const response = await fetch(
                                    url, {
                                        method: 'POST',

                                        headers: {
                                            'Content-Type': 'application/json',

                                            'Accept': 'application/json',

                                            'X-Requested-With': 'XMLHttpRequest',

                                            'X-CSRF-TOKEN': csrfToken
                                        },

                                        body: JSON.stringify({
                                            decision: decision
                                        })
                                    }
                                );

                                const data =
                                    await response.json();

                                if (
                                    !response.ok ||
                                    !data.success
                                ) {
                                    throw new Error(
                                        data.message ||
                                        'Failed to save decision.'
                                    );
                                }

                                updateCommitteeState(
                                    loanId,
                                    data.credit_committee
                                );

                                if (statusElement) {
                                    statusElement.className =
                                        'd-block mt-1 text-success';

                                    statusElement.textContent =
                                        data.message ||
                                        'Decision saved.';
                                }
                            } catch (error) {
                                if (statusElement) {
                                    statusElement.className =
                                        'd-block mt-1 text-danger';

                                    statusElement.textContent =
                                        error.message ||
                                        'Failed to save decision.';
                                }
                            } finally {
                                if (yesButton) {
                                    yesButton.disabled = false;
                                }

                                if (noButton) {
                                    noButton.disabled = false;
                                }
                            }
                        }
                    );
                });
        });
    </script>
    @if ($isAdmin)
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                function updateChargeRow(row, preserveExisting = false) {
                    const select = row.querySelector('.charge-type-select');
                    const selectedOption = select.options[select.selectedIndex];

                    const codeInput = row.querySelector('.charge-code');
                    const valueTypeInput = row.querySelector('.charge-value-type');
                    const defaultValueInput = row.querySelector('.charge-default-value');
                    const effectBadge = row.querySelector('.charge-effect-badge');
                    const valueInput = row.querySelector('.charge-input-value');
                    const helpText = row.querySelector('.charge-help-text');
                    const existingValue = row.dataset.existingValue || '';

                    if (!selectedOption || !selectedOption.value) {
                        codeInput.value = '';
                        valueTypeInput.value = '';
                        defaultValueInput.value = '';
                        effectBadge.className = 'badge bg-secondary charge-effect-badge';
                        effectBadge.textContent = 'Not selected';
                        valueInput.value = '';
                        valueInput.readOnly = false;
                        valueInput.placeholder = '';
                        valueInput.classList.remove('bg-light');
                        helpText.textContent = 'Select a charge type first.';
                        return;
                    }

                    const code = selectedOption.dataset.code || '';
                    const valueType = selectedOption.dataset.valueType || '';
                    const defaultValue = parseFloat(selectedOption.dataset.default || '0');
                    const effect = selectedOption.dataset.effect || '';

                    codeInput.value = code;
                    valueTypeInput.value = valueType;
                    defaultValueInput.value = defaultValue > 0 ? defaultValue : '0';

                    effectBadge.className = 'badge charge-effect-badge';
                    if (effect === 'ADD_TO_LOAN') {
                        effectBadge.classList.add('bg-primary');
                        effectBadge.textContent = 'Adds to loan';
                    } else if (effect === 'DEDUCT_FROM_DISBURSEMENT') {
                        effectBadge.classList.add('bg-danger');
                        effectBadge.textContent = 'Reduces disbursement';
                    } else {
                        effectBadge.classList.add('bg-secondary');
                        effectBadge.textContent = effect || 'Unknown';
                    }

                    if (defaultValue > 0) {
                        valueInput.value = defaultValue;
                        valueInput.readOnly = true;
                        valueInput.classList.add('bg-light');
                        helpText.textContent = valueType === 'PERCENT' ?
                            'Locked percentage from setup. Admin cannot change it here.' :
                            'Locked fixed amount from setup. Admin cannot change it here.';
                    } else {
                        valueInput.readOnly = false;
                        valueInput.classList.remove('bg-light');
                        valueInput.placeholder = valueType === 'PERCENT' ? 'Enter percentage' : 'Enter amount';

                        if (preserveExisting && existingValue !== '') {
                            valueInput.value = existingValue;
                        } else if (!preserveExisting) {
                            valueInput.value = '';
                        }

                        helpText.textContent = valueType === 'PERCENT' ?
                            'No preset percentage. Admin may enter the percentage here.' :
                            'No preset fixed amount. Admin may enter the amount here.';
                    }
                }

                function resetSingleRow(row) {
                    row.dataset.existingValue = '';
                    row.querySelector('.charge-type-select').value = '';
                    row.querySelector('.charge-code').value = '';
                    row.querySelector('.charge-value-type').value = '';
                    row.querySelector('.charge-default-value').value = '';
                    row.querySelector('.charge-effect-badge').className = 'badge bg-secondary charge-effect-badge';
                    row.querySelector('.charge-effect-badge').textContent = 'Not selected';
                    row.querySelector('.charge-input-value').value = '';
                    row.querySelector('.charge-input-value').readOnly = false;
                    row.querySelector('.charge-input-value').placeholder = '';
                    row.querySelector('.charge-input-value').classList.remove('bg-light');
                    row.querySelector('.charge-comment-input').value = '';
                    row.querySelector('.charge-help-text').textContent = 'Select a charge type first.';
                }

                function bindRowEvents(row) {
                    const select = row.querySelector('.charge-type-select');
                    const removeBtn = row.querySelector('.remove-charge-row');

                    select.addEventListener('change', function() {
                        row.dataset.existingValue = '';
                        updateChargeRow(row, false);
                    });

                    removeBtn.addEventListener('click', function() {
                        const tbody = row.closest('tbody');
                        const rows = tbody.querySelectorAll('.charge-row');

                        if (rows.length > 1) {
                            row.remove();
                        } else {
                            resetSingleRow(row);
                        }
                    });
                }

                document.querySelectorAll('.charge-row').forEach(function(row) {
                    bindRowEvents(row);
                    updateChargeRow(row, true);
                });

                document.querySelectorAll('.add-charge-row-btn').forEach(function(button) {
                    button.addEventListener('click', function() {
                        const loanId = this.dataset.loanId;
                        const tbody = document.getElementById('chargeRows' + loanId);
                        let nextIndex = parseInt(tbody.dataset.nextIndex || '1', 10);

                        const tr = document.createElement('tr');
                        tr.className = 'charge-row';
                        tr.dataset.existingValue = '';
                        tr.innerHTML = `
                <td>
                    <select class="form-control charge-type-select"
                            name="charges[${nextIndex}][deduction_type_id]"
                            data-loan-id="${loanId}">
                        <option value="">Select charge</option>
                        @foreach ($deductionTypes as $type)
                            <option value="{{ $type->deduction_type_id }}"
                                    data-code="{{ $type->deduction_type_code }}"
                                    data-value-type="{{ strtoupper($type->deduction_type_value_type) }}"
                                    data-default="{{ (float) $type->deduction_type_default_value }}"
                                    data-effect="{{ strtoupper($type->deduction_type_effect) }}"
                                    data-name="{{ $type->deduction_type_name }}">
                                {{ $type->deduction_type_name }}
                            </option>
                        @endforeach
                    </select>
                </td>
                <td><input type="text" class="form-control charge-code" readonly></td>
                <td><input type="text" class="form-control charge-value-type" readonly></td>
                <td><input type="text" class="form-control charge-default-value" readonly></td>
                <td><span class="badge bg-secondary charge-effect-badge">Not selected</span></td>
                <td><input type="number" step="0.0001" min="0" class="form-control charge-input-value" name="charges[${nextIndex}][value]"></td>
                <td>
                    <input type="text" class="form-control charge-comment-input" name="charges[${nextIndex}][comment]" placeholder="Optional note">
                    <small class="text-muted charge-help-text d-block mt-1">Select a charge type first.</small>
                </td>
                <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger remove-charge-row">&times;</button></td>
            `;

                        tbody.appendChild(tr);
                        tbody.dataset.nextIndex = (nextIndex + 1).toString();

                        bindRowEvents(tr);
                        updateChargeRow(tr, false);
                    });
                });
            });
        </script>

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                const csrfToken = '{{ csrf_token() }}';

                async function saveDocNo(input) {
                    const wrapper = input.closest('.docno-edit-wrap');
                    const statusEl = wrapper ? wrapper.querySelector('.js-docno-status') : null;
                    const url = input.dataset.url;
                    const value = input.value.trim();
                    const originalValue = input.dataset.lastSavedValue ?? '';

                    if (!url) {
                        return;
                    }

                    if (value === originalValue) {
                        if (statusEl) {
                            statusEl.textContent = '';
                        }
                        return;
                    }

                    input.disabled = true;
                    if (statusEl) {
                        statusEl.textContent = 'Saving...';
                        statusEl.classList.remove('text-success', 'text-danger');
                        statusEl.classList.add('text-muted');
                    }

                    try {
                        const response = await fetch(url, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                batch_trans_doc_no: value
                            })
                        });

                        const data = await response.json();

                        if (!response.ok || !data.success) {
                            throw new Error(data.message || 'Failed to save document number.');
                        }

                        input.dataset.lastSavedValue = data.batch_trans_doc_no ?? '';
                        if (statusEl) {
                            statusEl.textContent = 'Saved';
                            statusEl.classList.remove('text-muted', 'text-danger');
                            statusEl.classList.add('text-success');
                        }
                    } catch (error) {
                        if (statusEl) {
                            statusEl.textContent = error.message || 'Save failed';
                            statusEl.classList.remove('text-muted', 'text-success');
                            statusEl.classList.add('text-danger');
                        }
                    } finally {
                        input.disabled = false;
                    }
                }

                document.querySelectorAll('.js-docno-input').forEach(function(input) {
                    input.dataset.lastSavedValue = input.value.trim();

                    input.addEventListener('blur', function() {
                        saveDocNo(input);
                    });

                    input.addEventListener('keydown', function(event) {
                        if (event.key === 'Enter') {
                            event.preventDefault();
                            input.blur();
                        }
                    });
                });
            });
        </script>
    @endif

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

    <link href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css" rel="stylesheet">

    <script src="https://code.jquery.com/jquery-3.5.1.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.flash.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>

    <script>
        $(document).ready(function() {
            $('#memberContributionsTable').DataTable({
                dom: 'Bfrtip',
                buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
                searching: false,
                paging: false,
                lengthChange: false,
                order: [
                    [0, 'asc']
                ]
            });
        });
    </script>
    @endsection
