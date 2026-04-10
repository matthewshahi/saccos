@extends('layouts.app')

@section('content')
@php
    $isAdmin = ((int) (Auth::user()->member_position ?? 0) === 2);
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
                            <input type="text" name="search" class="form-control" placeholder="Search..." value="{{ request()->search }}">
                        </div>
                    </div>

                    <div class="col-md-4 mb-2 mb-md-0">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" id="pendingLoansOnly" name="pending" value="1" {{ request()->pending ? 'checked' : '' }}>
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

                           @if($isAdmin)
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
        <input
            type="text"
            class="form-control form-control-sm js-docno-input"
            value="{{ $loan->batch_trans_doc_no ?? '' }}"
            data-url="{{ route('loans.pending.approval.docno.update', $loan->batch_trans_id) }}"
            data-loan-id="{{ $loan->batch_trans_id }}"
            placeholder="Enter doc no"
            autocomplete="off"
        >
        <small class="text-muted d-block mt-1 js-docno-status"></small>
    </div>
</td>
                                <td class="text-end">{{ number_format((float) $loan->batch_trans_monthly_payment, 2) }}</td>
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
    $isOwner = ((int) (Auth::user()->member_id ?? 0) === (int) ($loan->batch_trans_member_id ?? 0));
    $isOpenLoan = ($loan->batch_trans_updated == 'N' && $loan->batch_trans_deleted != 'Y');
@endphp

@if($isAdmin)
    <td>
        @if ($isOpenLoan)
            <button type="button"
                    class="btn btn-outline-primary btn-sm"
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
                                    <button class="btn btn-info btn-sm"
                                            data-bs-toggle="modal"
                                            data-bs-target="#loanModal{{ $loan->batch_trans_id }}">
                                        Details
                                    </button>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $isAdmin ? 19 : 18 }}" class="text-center">No loans pending approval.</td>
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
        <div class="modal fade" id="loanModal{{ $loan->batch_trans_id }}" tabindex="-1" aria-labelledby="loanModalLabel{{ $loan->batch_trans_id }}" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="loanModalLabel{{ $loan->batch_trans_id }}">Loan Details - {{ $loan->member_name }}</h5>
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
                                    <li><strong>Loan Amount:</strong> {{ number_format((float) $loan->batch_trans_loan_amount, 2) }}</li>
                                    <li><strong>Loan Type:</strong> {{ $loan->loan_type_name }}</li>
                                    <li><strong>Duration:</strong> {{ $loan->batch_trans_loan_duration }} months</li>
                                    <li><strong>Monthly Payment:</strong> {{ number_format((float) $loan->batch_trans_monthly_payment, 2) }}</li>
                                    <li><strong>Top-Up Amount:</strong> {{ number_format((float) ($loan->batch_trans_loan_to_top_up_amount ?? 0), 2) }}</li>
                                    <li><strong>Commission:</strong> {{ number_format((float) $loan->batch_trans_commission, 2) }}</li>
                                    <li><strong>Insurance:</strong> {{ number_format((float) $loan->batch_trans_insurance, 2) }}</li>
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
                                            <span class="badge {{ isset($guarantors_statuses[$index]) && $guarantors_statuses[$index] == 'Y' ? 'bg-success' : 'bg-warning' }}">
                                                {{ isset($guarantors_statuses[$index]) && $guarantors_statuses[$index] == 'Y' ? 'Accepted' : 'Pending' }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach

                                @if (empty($guarantors_names[0]))
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No guarantors available.</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>

                    <div class="modal-footer">
                        @if ($loan->batch_trans_updated == 'N' && $loan->batch_trans_deleted != 'Y')
                            @if ($isAdmin)
                                <form id="approveForm{{ $loan->batch_trans_id }}"
                                      action="{{ route('loans.approve.self', ['loanId' => $loan->batch_trans_id]) }}"
                                      method="POST" style="display: inline;">
                                    @csrf
                                    <button type="button" class="btn btn-success"
                                            onclick="confirmAction('approve', '{{ $loan->batch_trans_id }}')">Approve</button>
                                </form>

                                <form id="rejectForm{{ $loan->batch_trans_id }}"
                                      action="{{ route('loans.reject', ['id' => $loan->batch_trans_id]) }}"
                                      method="POST" style="display: inline;">
                                    @csrf
                                    <button type="button" class="btn btn-danger"
                                            onclick="confirmAction('reject', '{{ $loan->batch_trans_id }}')">Reject</button>
                                </form>
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
    @if($isAdmin)
        @foreach ($loans as $loan)
            @php
                $savedCharges = collect($applicationCharges[$loan->batch_trans_id] ?? []);
                $nextIndex = $savedCharges->count() > 0 ? $savedCharges->count() : 1;
            @endphp

            <div class="modal fade" id="chargesModal{{ $loan->batch_trans_id }}" tabindex="-1" aria-labelledby="chargesModalLabel{{ $loan->batch_trans_id }}" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-scrollable">
                    <form action="{{ route('loans.selfservice.adjust.charges.save', ['id' => $loan->batch_trans_id]) }}" method="POST">
                        @csrf

                        <div class="modal-content">
                            <div class="modal-header bg-dark text-white">
                                <h5 class="modal-title" id="chargesModalLabel{{ $loan->batch_trans_id }}" style="color: white;">
                                    Adjust Charges - {{ $loan->member_name }}
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                            </div>

                            <div class="modal-body">
                                <div class="row mb-3">
                                    <div class="col-md-4">
                                        <strong>Application ID:</strong> {{ $loan->batch_trans_id }}
                                    </div>
                                    <div class="col-md-4">
                                        <strong>Member:</strong> {{ $loan->member_name }} ({{ $loan->member_sacco_id }})
                                    </div>
                                    <div class="col-md-4 text-md-end">
                                        <strong>Requested Amount:</strong> {{ number_format((float) $loan->batch_trans_loan_amount, 2) }}
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

                                        <tbody id="chargeRows{{ $loan->batch_trans_id }}" data-next-index="{{ $nextIndex }}">
                                            @forelse($savedCharges as $index => $savedCharge)
                                                @php
                                                    $selectedType = $deductionTypes->firstWhere('deduction_type_id', $savedCharge->batch_trans_deduction_deduction_type_id);
                                                    $typeDefaultValue = (float) ($selectedType->deduction_type_default_value ?? 0);
                                                    $typeValueType = strtoupper((string) ($savedCharge->batch_trans_deduction_value_type ?? ''));
                                                    $typeEffect = strtoupper((string) ($savedCharge->batch_trans_deduction_effect ?? ''));
                                                    $existingValue = '';

                                                    if ($typeDefaultValue > 0) {
                                                        $existingValue = $typeDefaultValue;
                                                    } else {
                                                        if ($typeValueType === 'PERCENT') {
                                                            preg_match('/at\s+([0-9.]+)%/i', $savedCharge->batch_trans_deduction_description ?? '', $matches);
                                                            $existingValue = $matches[1] ?? '';
                                                        } else {
                                                            $existingValue = (float) ($savedCharge->batch_trans_deduction_amount ?? 0);
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
                                                            @foreach($deductionTypes as $type)
                                                                <option value="{{ $type->deduction_type_id }}"
                                                                        data-code="{{ $type->deduction_type_code }}"
                                                                        data-value-type="{{ strtoupper($type->deduction_type_value_type) }}"
                                                                        data-default="{{ (float) $type->deduction_type_default_value }}"
                                                                        data-effect="{{ strtoupper($type->deduction_type_effect) }}"
                                                                        data-name="{{ $type->deduction_type_name }}"
                                                                        {{ (int)$type->deduction_type_id === (int)$savedCharge->batch_trans_deduction_deduction_type_id ? 'selected' : '' }}>
                                                                    {{ $type->deduction_type_name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </td>

                                                    <td>
                                                        <input type="text" class="form-control charge-code" value="{{ $savedCharge->batch_trans_deduction_code }}" readonly>
                                                    </td>

                                                    <td>
                                                        <input type="text" class="form-control charge-value-type" value="{{ $savedCharge->batch_trans_deduction_value_type }}" readonly>
                                                    </td>

                                                    <td>
                                                        <input type="text" class="form-control charge-default-value" value="{{ $typeDefaultValue > 0 ? $typeDefaultValue : '0' }}" readonly>
                                                    </td>

                                                    <td>
                                                        <span class="badge charge-effect-badge {{ $typeEffect === 'ADD_TO_LOAN' ? 'bg-primary' : ($typeEffect === 'DEDUCT_FROM_DISBURSEMENT' ? 'bg-danger' : 'bg-secondary') }}">
                                                            {{ $typeEffect === 'ADD_TO_LOAN' ? 'Adds to loan' : ($typeEffect === 'DEDUCT_FROM_DISBURSEMENT' ? 'Reduces disbursement' : 'Not selected') }}
                                                        </span>
                                                    </td>

                                                    <td>
                                                        <input type="number"
                                                               step="0.0001"
                                                               min="0"
                                                               class="form-control charge-input-value {{ $isLocked ? 'bg-light' : '' }}"
                                                               name="charges[{{ $index }}][value]"
                                                               value="{{ $existingValue }}"
                                                               {{ $isLocked ? 'readonly' : '' }}>
                                                    </td>

                                                    <td>
                                                        <input type="text"
                                                               class="form-control charge-comment-input"
                                                               name="charges[{{ $index }}][comment]"
                                                               value=""
                                                               placeholder="Optional note">
                                                        <small class="text-muted charge-help-text d-block mt-1">
                                                            @if($isLocked)
                                                                {{ $typeValueType === 'PERCENT' ? 'Locked percentage from setup. Admin cannot change it here.' : 'Locked fixed amount from setup. Admin cannot change it here.' }}
                                                            @else
                                                                {{ $typeValueType === 'PERCENT' ? 'Saved percentage loaded. You may adjust it here.' : 'Saved amount loaded. You may adjust it here.' }}
                                                            @endif
                                                        </small>
                                                    </td>

                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-sm btn-outline-danger remove-charge-row">&times;</button>
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr class="charge-row" data-existing-value="">
                                                    <td>
                                                        <select class="form-control charge-type-select"
                                                                name="charges[0][deduction_type_id]"
                                                                data-loan-id="{{ $loan->batch_trans_id }}">
                                                            <option value="">Select charge</option>
                                                            @foreach($deductionTypes as $type)
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
                                                    <td>
                                                        <input type="number"
                                                               step="0.0001"
                                                               min="0"
                                                               class="form-control charge-input-value"
                                                               name="charges[0][value]">
                                                    </td>
                                                    <td>
                                                        <input type="text"
                                                               class="form-control charge-comment-input"
                                                               name="charges[0][comment]"
                                                               placeholder="Optional note">
                                                        <small class="text-muted charge-help-text d-block mt-1">Select a charge type first.</small>
                                                    </td>
                                                    <td class="text-center">
                                                        <button type="button" class="btn btn-sm btn-outline-danger remove-charge-row">&times;</button>
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>

                                <div class="d-flex justify-content-between mt-3">
                                    <button type="button"
                                            class="btn btn-outline-primary add-charge-row-btn"
                                            data-loan-id="{{ $loan->batch_trans_id }}">
                                        Add Another Charge
                                    </button>

                                    <div class="text-end">
                                        <small class="text-muted d-block">Existing active charges are loaded here.</small>
                                        <small class="text-muted d-block">Rows removed here will disappear on save.</small>
                                    </div>
                                </div>
                            </div>

                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
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
    let actionText = action === 'approve' ? 'Approve' : 'Reject';
    let confirmation = confirm(`Are you sure you want to ${actionText} this loan application? This action is irreversible.`);

    if (confirmation) {
        if (action === 'approve') {
            document.getElementById(`approveForm${loanId}`).submit();
        } else if (action === 'reject') {
            document.getElementById(`rejectForm${loanId}`).submit();
        }
    }
}
</script>

@if($isAdmin)
<script>
document.addEventListener('DOMContentLoaded', function () {
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
            helpText.textContent = valueType === 'PERCENT'
                ? 'Locked percentage from setup. Admin cannot change it here.'
                : 'Locked fixed amount from setup. Admin cannot change it here.';
        } else {
            valueInput.readOnly = false;
            valueInput.classList.remove('bg-light');
            valueInput.placeholder = valueType === 'PERCENT' ? 'Enter percentage' : 'Enter amount';

            if (preserveExisting && existingValue !== '') {
                valueInput.value = existingValue;
            } else if (!preserveExisting) {
                valueInput.value = '';
            }

            helpText.textContent = valueType === 'PERCENT'
                ? 'No preset percentage. Admin may enter the percentage here.'
                : 'No preset fixed amount. Admin may enter the amount here.';
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

        select.addEventListener('change', function () {
            row.dataset.existingValue = '';
            updateChargeRow(row, false);
        });

        removeBtn.addEventListener('click', function () {
            const tbody = row.closest('tbody');
            const rows = tbody.querySelectorAll('.charge-row');

            if (rows.length > 1) {
                row.remove();
            } else {
                resetSingleRow(row);
            }
        });
    }

    document.querySelectorAll('.charge-row').forEach(function (row) {
        bindRowEvents(row);
        updateChargeRow(row, true);
    });

    document.querySelectorAll('.add-charge-row-btn').forEach(function (button) {
        button.addEventListener('click', function () {
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
                        @foreach($deductionTypes as $type)
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
document.addEventListener('DOMContentLoaded', function () {
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

    document.querySelectorAll('.js-docno-input').forEach(function (input) {
        input.dataset.lastSavedValue = input.value.trim();

        input.addEventListener('blur', function () {
            saveDocNo(input);
        });

        input.addEventListener('keydown', function (event) {
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
        order: [[0, 'asc']]
    });
});
</script>
@endsection