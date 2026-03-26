@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Add Transaction to Batch: {{ $batch->batch_reference }}</h1>
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
        <div class="card mb-3">
            <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
                <div>
                    <strong>Loan Batch Transactions</strong><br>
                    <small class="text-muted">Add a member loan transaction into batch <strong>{{ $batch->batch_reference }}</strong>.</small>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('loans.batch.transactions', $batch->batch_id) }}" class="btn btn-outline-secondary btn-sm">
                        Back to Transactions
                    </a>
                    <a href="{{ route('loans.batches') }}" class="btn btn-outline-primary btn-sm">
                        List Loan Batches
                    </a>
                </div>
            </div>
        </div>

        <div class="card text-start">
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger">
                        @if(is_array(session('error')))
                            @foreach(session('error') as $error)
                                <p class="mb-1">{{ $error }}</p>
                            @endforeach
                        @else
                            {{ session('error') }}
                        @endif
                    </div>
                @endif

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('loans.batch.transactions.add', $batch->batch_id) }}" method="POST" autocomplete="off">
                    @csrf

                    {{-- SECTION 1: MEMBER + LOAN DETAILS --}}
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header">
                            <h4 class="card-title mb-0">1. Member & Loan Details</h4>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-12 mb-3 position-relative">
                                    <label for="member">Member <span class="text-danger">*</span></label>
                                    <input
                                        type="text"
                                        id="member"
                                        name="batch_trans_member_id"
                                        class="form-control @error('batch_trans_member_id') is-invalid @enderror"
                                        onkeyup="showHintMembers(this.value)"
                                        autocomplete="off"
                                        required
                                        value="{{ old('batch_trans_member_id') }}"
                                    >
                                    <div id="suggestions-box" class="suggestions-box"></div>
                                    @error('batch_trans_member_id')
                                        <span class="invalid-feedback d-block" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="loan_type">Loan Type <span class="text-danger">*</span></label>
                                    <select name="batch_trans_loan_type" id="loan_type" class="form-control @error('batch_trans_loan_type') is-invalid @enderror" required>
                                        <option value="">Select Loan Type</option>
                                        @foreach($loanTypes as $type)
                                            <option value="{{ $type->loan_type_id }}" {{ old('batch_trans_loan_type') == $type->loan_type_id ? 'selected' : '' }}>
                                                {{ $type->loan_type_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('batch_trans_loan_type')
                                        <span class="invalid-feedback d-block" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="loan_category">Loan Category <span class="text-danger">*</span></label>
                                    <select name="batch_trans_loan_category" id="loan_category" class="form-control @error('batch_trans_loan_category') is-invalid @enderror" required>
                                        <option value="">Select Loan Category</option>
                                        @foreach($loanCategories as $category)
                                            <option value="{{ $category->loan_category_id }}" {{ old('batch_trans_loan_category') == $category->loan_category_id ? 'selected' : '' }}>
                                                {{ $category->loan_category_name }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('batch_trans_loan_category')
                                        <span class="invalid-feedback d-block" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="loan_amount">Loan Amount <span class="text-danger">*</span></label>
                                    <input
                                        type="text"
                                        name="batch_trans_loan_amount"
                                        id="loan_amount"
                                        class="form-control @error('batch_trans_loan_amount') is-invalid @enderror"
                                        required
                                        value="{{ old('batch_trans_loan_amount') }}"
                                    >
                                    @error('batch_trans_loan_amount')
                                        <span class="invalid-feedback d-block" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="loan_duration">Loan Duration (Months) <span class="text-danger">*</span></label>
                                    <input
                                        type="number"
                                        name="batch_trans_loan_duration"
                                        id="loan_duration"
                                        class="form-control @error('batch_trans_loan_duration') is-invalid @enderror"
                                        required
                                        value="{{ old('batch_trans_loan_duration') }}"
                                    >
                                    @error('batch_trans_loan_duration')
                                        <span class="invalid-feedback d-block" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="commission_amount">Commission Amount (Optional)</label>
                                    <input
                                        type="text"
                                        name="batch_trans_commission_amount"
                                        id="commission_amount"
                                        class="form-control @error('batch_trans_commission_amount') is-invalid @enderror"
                                        value="{{ old('batch_trans_commission_amount') }}"
                                    >
                                    @error('batch_trans_commission_amount')
                                        <span class="invalid-feedback d-block" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="loan_to_top_up">Loan to Top Up (Optional)</label>
                                    <select name="batch_trans_loan_to_top_up" id="loan_to_top_up" class="form-control @error('batch_trans_loan_to_top_up') is-invalid @enderror">
                                        <option value="">Select Loan</option>
                                        @foreach($loansToTopUp as $loan)
                                            <option value="{{ $loan->loan_id }}" {{ old('batch_trans_loan_to_top_up') == $loan->loan_id ? 'selected' : '' }}>
                                                {{ $loan->loan_type_name }} - (ID: {{ $loan->loan_id }}) Balance: {{ number_format($loan->loan_balance, 2) }}
                                            </option>
                                        @endforeach
                                    </select>
                                    @error('batch_trans_loan_to_top_up')
                                        <span class="invalid-feedback d-block" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="doc_no">Document No <span class="text-danger">*</span></label>
                                    <input
                                        type="text"
                                        name="batch_trans_doc_no"
                                        id="doc_no"
                                        class="form-control @error('batch_trans_doc_no') is-invalid @enderror"
                                        required
                                        value="{{ old('batch_trans_doc_no') }}"
                                    >
                                    @error('batch_trans_doc_no')
                                        <span class="invalid-feedback d-block" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>

                                <div class="col-md-12 mb-3">
                                    <label for="description">Description</label>
                                    <textarea
                                        name="batch_trans_description"
                                        id="description"
                                        class="form-control @error('batch_trans_description') is-invalid @enderror"
                                        rows="3"
                                    >{{ old('batch_trans_description') }}</textarea>
                                    @error('batch_trans_description')
                                        <span class="invalid-feedback d-block" role="alert">
                                            <strong>{{ $message }}</strong>
                                        </span>
                                    @enderror
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- SECTION 2: OTHER CHARGES / DEDUCTIONS --}}
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <h4 class="card-title mb-1">2. Other Charges / Deductions</h4>
                                <small class="text-muted d-block">
                                    Add extra fees, deductions, or additions. A charge type can only be selected once.
                                </small>
                                <a href="{{ route('loans.deduction-types') }}" class="btn btn-link btn-sm p-0 mt-1">
                                    Manage deduction types / add missing deductible
                                </a>
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="addChargeRow()">
                                Add Charge Row
                            </button>
                        </div>

                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle" id="other-charges-table">
                                    <thead>
                                        <tr>
                                            <th style="min-width: 220px;">Charge Type</th>
                                            <th style="min-width: 150px;">Effect</th>
                                            <th style="min-width: 130px;">Value Type</th>
                                            <th style="min-width: 170px;">Amount / Value</th>
                                            <th style="min-width: 220px;">Description / Remarks</th>
                                            <th style="width: 100px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @php
                                            $oldCharges = old('other_charges', []);
                                        @endphp

                                        @if(count($oldCharges) > 0)
                                            @foreach($oldCharges as $i => $charge)
                                                <tr class="charge-row">
                                                    <td>
                                                        <select
                                                            name="other_charges[{{ $i }}][deduction_type_id]"
                                                            class="form-control charge-type-select @error('other_charges.'.$i.'.deduction_type_id') is-invalid @enderror"
                                                            onchange="handleChargeTypeChange(this)"
                                                            data-row-index="{{ $i }}"
                                                        >
                                                            <option value="">Select Charge Type</option>
                                                            @foreach($additionalDeductionTypes as $dtype)
                                                                <option
                                                                    value="{{ $dtype->deduction_type_id }}"
                                                                    data-effect="{{ $dtype->deduction_type_effect }}"
                                                                    data-value-type="{{ $dtype->deduction_type_value_type }}"
                                                                    data-default-value="{{ $dtype->deduction_type_default_value }}"
                                                                    {{ (string)($charge['deduction_type_id'] ?? '') === (string)$dtype->deduction_type_id ? 'selected' : '' }}
                                                                >
                                                                    {{ $dtype->deduction_type_name }}
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                        @error('other_charges.'.$i.'.deduction_type_id')
                                                            <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                                        @enderror
                                                    </td>

                                                    <td>
                                                        <input type="text" class="form-control charge-effect-display" readonly value="{{ $charge['effect'] ?? '' }}">
                                                        <input type="hidden" name="other_charges[{{ $i }}][effect]" class="charge-effect-hidden" value="{{ $charge['effect'] ?? '' }}">
                                                    </td>

                                                    <td>
                                                        <input type="text" class="form-control charge-value-type-display" readonly value="{{ $charge['value_type'] ?? '' }}">
                                                        <input type="hidden" name="other_charges[{{ $i }}][value_type]" class="charge-value-type-hidden" value="{{ $charge['value_type'] ?? '' }}">
                                                    </td>

                                                    <td>
                                                        <input
                                                            type="text"
                                                            name="other_charges[{{ $i }}][amount]"
                                                            class="form-control charge-amount-input @error('other_charges.'.$i.'.amount') is-invalid @enderror"
                                                            value="{{ $charge['amount'] ?? '' }}"
                                                        >
                                                        <small class="text-muted charge-amount-help d-block mt-1"></small>
                                                        @error('other_charges.'.$i.'.amount')
                                                            <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                                        @enderror
                                                    </td>

                                                    <td>
                                                        <input
                                                            type="text"
                                                            name="other_charges[{{ $i }}][description]"
                                                            class="form-control @error('other_charges.'.$i.'.description') is-invalid @enderror"
                                                            value="{{ $charge['description'] ?? '' }}"
                                                        >
                                                        @error('other_charges.'.$i.'.description')
                                                            <span class="invalid-feedback d-block"><strong>{{ $message }}</strong></span>
                                                        @enderror
                                                    </td>

                                                    <td>
                                                        <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeChargeRow(this)">Remove</button>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        @else
                                            <tr class="charge-row">
                                                <td>
                                                    <select
                                                        name="other_charges[0][deduction_type_id]"
                                                        class="form-control charge-type-select"
                                                        onchange="handleChargeTypeChange(this)"
                                                        data-row-index="0"
                                                    >
                                                        <option value="">Select Charge Type</option>
                                                        @foreach($additionalDeductionTypes as $dtype)
                                                            <option
                                                                value="{{ $dtype->deduction_type_id }}"
                                                                data-effect="{{ $dtype->deduction_type_effect }}"
                                                                data-value-type="{{ $dtype->deduction_type_value_type }}"
                                                                data-default-value="{{ $dtype->deduction_type_default_value }}"
                                                            >
                                                                {{ $dtype->deduction_type_name }}
                                                            </option>
                                                        @endforeach
                                                    </select>
                                                </td>

                                                <td>
                                                    <input type="text" class="form-control charge-effect-display" readonly>
                                                    <input type="hidden" name="other_charges[0][effect]" class="charge-effect-hidden">
                                                </td>

                                                <td>
                                                    <input type="text" class="form-control charge-value-type-display" readonly>
                                                    <input type="hidden" name="other_charges[0][value_type]" class="charge-value-type-hidden">
                                                </td>

                                                <td>
                                                    <input type="text" name="other_charges[0][amount]" class="form-control charge-amount-input">
                                                    <small class="text-muted charge-amount-help d-block mt-1"></small>
                                                </td>

                                                <td>
                                                    <input type="text" name="other_charges[0][description]" class="form-control">
                                                </td>

                                                <td>
                                                    <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeChargeRow(this)">Remove</button>
                                                </td>
                                            </tr>
                                        @endif
                                    </tbody>
                                </table>
                            </div>

                            <small class="text-muted d-block mt-2">
                                The same charge type cannot be added more than once in the same transaction.
                            </small>
                        </div>
                    </div>

                    {{-- SECTION 3: GUARANTORS --}}
                    <div class="card mb-4 shadow-sm">
                        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <h4 class="card-title mb-0">3. Guarantors</h4>
                                <small class="text-muted">Add one or more guarantors and specify guaranteed amounts.</small>
                            </div>
                            <button type="button" class="btn btn-secondary btn-sm" onclick="addGuarantorRow()">
                                Add Guarantor Row
                            </button>
                        </div>

                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered align-middle" id="guarantors-table">
                                    <thead>
                                        <tr>
                                            <th style="min-width: 250px;">Member</th>
                                            <th style="min-width: 140px;">Amount</th>
                                            <th style="min-width: 140px;">Free Shares</th>
                                            <th style="width: 110px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach(old('guarantors', range(0, 4)) as $i => $guarantor)
                                            <tr>
                                                <td class="position-relative">
                                                    <input
                                                        type="text"
                                                        name="guarantors[{{ $i }}][member]"
                                                        class="form-control guarantor-member @error('guarantors.'.$i.'.member') is-invalid @enderror"
                                                        onkeyup="showHintGuarantor(this.value, {{ $i }})"
                                                        autocomplete="off"
                                                        value="{{ old('guarantors.'.$i.'.member') }}"
                                                    >
                                                    <div id="suggestions-box-{{ $i }}" class="suggestions-box"></div>
                                                    @error('guarantors.'.$i.'.member')
                                                        <span class="invalid-feedback d-block" role="alert">
                                                            <strong>{{ $message }}</strong>
                                                        </span>
                                                    @enderror
                                                </td>

                                                <td>
                                                    <input
                                                        type="text"
                                                        name="guarantors[{{ $i }}][amount]"
                                                        class="form-control @error('guarantors.'.$i.'.amount') is-invalid @enderror"
                                                        value="{{ old('guarantors.'.$i.'.amount') }}"
                                                    >
                                                    @error('guarantors.'.$i.'.amount')
                                                        <span class="invalid-feedback d-block" role="alert">
                                                            <strong>{{ $message }}</strong>
                                                        </span>
                                                    @enderror
                                                </td>

                                                <td>
                                                    <input
                                                        type="text"
                                                        name="guarantors[{{ $i }}][free_shares]"
                                                        class="form-control guarantor-free-shares"
                                                        readonly
                                                        value="{{ old('guarantors.'.$i.'.free_shares') }}"
                                                    >
                                                </td>

                                                <td>
                                                    <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeGuarantorRow(this)">Remove</button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn btn-primary">
                            Add Transaction
                        </button>
                        <a href="{{ route('loans.batch.transactions', $batch->batch_id) }}" class="btn btn-secondary">
                            Back to Transactions
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .suggestions-box {
        background-color: #fff;
        max-height: 180px;
        overflow-y: auto;
        position: absolute;
        z-index: 1000;
        width: calc(100% - 30px);
        box-shadow: 0 8px 16px rgba(0, 0, 0, 0.12);
        border: 1px solid #ddd;
        border-radius: 0.35rem;
        margin-top: 2px;
    }

    .suggestion-item {
        padding: 10px 12px;
        cursor: pointer;
        border-bottom: 1px solid #f1f1f1;
    }

    .suggestion-item:last-child {
        border-bottom: none;
    }

    .suggestion-item:hover {
        background-color: #f8f9fa;
    }

    .card-header .card-title {
        font-size: 1rem;
    }

    .charge-amount-input.bg-light {
        background-color: #f8f9fa !important;
    }

    @media (max-width: 767.98px) {
        .breadcrumb h1 {
            font-size: 1.1rem;
        }

        .table {
            font-size: 0.92rem;
        }

        .btn {
            width: auto;
        }
    }
</style>

<script>
    const deductionTypes = @json($additionalDeductionTypes ?? []);

    function showHintMembers(str) {
        if (str.length === 0) {
            document.getElementById('suggestions-box').innerHTML = "";
            return;
        }

        let xmlhttp = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTTP");

        xmlhttp.onreadystatechange = function () {
            if (xmlhttp.readyState === 4 && xmlhttp.status === 200) {
                let response = JSON.parse(xmlhttp.responseText);
                let suggestions = '';
                response.forEach(member => {
                    suggestions += `<div class="suggestion-item" onclick="selectMember('${member.label}', '${member.member_id}')">${member.label}</div>`;
                });
                document.getElementById('suggestions-box').innerHTML = suggestions;
            }
        };

        xmlhttp.open("GET", "{{ url('/search/loan_batch/members') }}?query=" + encodeURIComponent(str), true);
        xmlhttp.send();
    }

    function selectMember(label, memberId) {
        document.getElementById('member').value = label;
        document.getElementById('suggestions-box').innerHTML = '';
        fetchOutstandingLoans(memberId);
        fetchFreeShares(memberId);
    }

    function fetchOutstandingLoans(memberId) {
        let xmlhttp = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTTP");

        xmlhttp.onreadystatechange = function () {
            if (xmlhttp.readyState === 4 && xmlhttp.status === 200) {
                let loans = JSON.parse(xmlhttp.responseText);
                let options = '<option value="">Select Loan</option>';

                loans.forEach(loan => {
                    let formattedBalance = new Intl.NumberFormat('en-US', { style: 'decimal' }).format(loan.loan_balance);
                    options += `<option value="${loan.loan_id}">${loan.loan_type_name} - (ID: ${loan.loan_id}) Balance: ${formattedBalance}</option>`;
                });

                document.getElementById('loan_to_top_up').innerHTML = options;
            }
        };

        xmlhttp.open("GET", "{{ url('/search/loan_batch/member_loans') }}?member_id=" + memberId, true);
        xmlhttp.send();
    }

    function fetchFreeShares(memberId, index) {
        let loanMemberId = document.getElementById('member').value;
        let xmlhttp = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTTP");

        xmlhttp.onreadystatechange = function () {
            if (xmlhttp.readyState === 4 && xmlhttp.status === 200) {
                let response = JSON.parse(xmlhttp.responseText);
                if (response && response.free_shares !== undefined && index !== undefined) {
                    let freeSharesField = document.querySelectorAll('.guarantor-free-shares')[index];
                    if (freeSharesField) {
                        freeSharesField.value = response.free_shares ? parseFloat(response.free_shares).toFixed(2) : '0.00';
                    }
                }
            }
        };

        let url = "{{ url('/loans/get-free-shares') }}?member_id=" + memberId + "&loan_member_id=" + encodeURIComponent(loanMemberId || '');
        xmlhttp.open("GET", url, true);
        xmlhttp.send();
    }

    function showHintGuarantor(str, index) {
        if (str.length === 0) {
            document.getElementById(`suggestions-box-${index}`).innerHTML = "";
            return;
        }

        let xmlhttp = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTTP");

        xmlhttp.onreadystatechange = function () {
            if (xmlhttp.readyState === 4 && xmlhttp.status === 200) {
                let response = JSON.parse(xmlhttp.responseText);
                let suggestions = '';

                response.forEach(member => {
                    suggestions += `<div class="suggestion-item" onclick="selectGuarantor('${member.label}', '${member.member_id}', ${index})">${member.label}</div>`;
                });

                document.getElementById(`suggestions-box-${index}`).innerHTML = suggestions;
            }
        };

        xmlhttp.open("GET", "{{ url('/search/loan_batch/members') }}?query=" + encodeURIComponent(str), true);
        xmlhttp.send();
    }

    function selectGuarantor(label, memberId, index) {
        document.querySelectorAll('.guarantor-member')[index].value = label;
        document.getElementById(`suggestions-box-${index}`).innerHTML = '';
        fetchFreeShares(memberId, index);
    }

    function addGuarantorRow() {
        const tableBody = document.querySelector('#guarantors-table tbody');
        const rowIndex = tableBody.rows.length;

        const row = document.createElement('tr');
        row.innerHTML = `
            <td class="position-relative">
                <input type="text" name="guarantors[${rowIndex}][member]" class="form-control guarantor-member" onkeyup="showHintGuarantor(this.value, ${rowIndex})" autocomplete="off">
                <div id="suggestions-box-${rowIndex}" class="suggestions-box"></div>
            </td>
            <td>
                <input type="text" name="guarantors[${rowIndex}][amount]" class="form-control">
            </td>
            <td>
                <input type="text" name="guarantors[${rowIndex}][free_shares]" class="form-control guarantor-free-shares" readonly>
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeGuarantorRow(this)">Remove</button>
            </td>
        `;

        tableBody.appendChild(row);
    }

    function removeGuarantorRow(button) {
        button.closest('tr').remove();
    }

    function getSelectedChargeTypeIds(excludeSelect = null) {
        const selects = document.querySelectorAll('.charge-type-select');
        let selected = [];

        selects.forEach(select => {
            if (excludeSelect && select === excludeSelect) return;
            if (select.value) {
                selected.push(String(select.value));
            }
        });

        return selected;
    }

    function buildChargeTypeOptions(selectedValue = '') {
        let options = `<option value="">Select Charge Type</option>`;

        deductionTypes.forEach(type => {
            const isSelected = String(selectedValue) === String(type.deduction_type_id);
            options += `
                <option
                    value="${type.deduction_type_id}"
                    data-effect="${type.deduction_type_effect ?? ''}"
                    data-value-type="${type.deduction_type_value_type ?? ''}"
                    data-default-value="${type.deduction_type_default_value ?? ''}"
                    ${isSelected ? 'selected' : ''}
                >
                    ${type.deduction_type_name}
                </option>
            `;
        });

        return options;
    }

    function refreshChargeOptions() {
        const selects = document.querySelectorAll('.charge-type-select');

        selects.forEach(select => {
            const currentValue = String(select.value || '');
            const selectedElsewhere = getSelectedChargeTypeIds(select);

            Array.from(select.options).forEach(option => {
                if (!option.value) return;
                option.disabled = selectedElsewhere.includes(String(option.value));
            });

            if (currentValue) {
                const ownOption = Array.from(select.options).find(opt => String(opt.value) === currentValue);
                if (ownOption) {
                    ownOption.disabled = false;
                }
            }
        });
    }
function handleChargeTypeChange(select) {
    const row = select.closest('tr');
    const selectedOption = select.options[select.selectedIndex];

    const effect = selectedOption ? (selectedOption.dataset.effect || '') : '';
    const valueType = selectedOption ? (selectedOption.dataset.valueType || '') : '';
    const defaultValueRaw = selectedOption ? (selectedOption.dataset.defaultValue || '') : '';
    const defaultValue = parseFloat(defaultValueRaw);
    const typeName = selectedOption && selectedOption.value ? selectedOption.text.trim() : '';

    const effectDisplay = row.querySelector('.charge-effect-display');
    const effectHidden = row.querySelector('.charge-effect-hidden');
    const valueTypeDisplay = row.querySelector('.charge-value-type-display');
    const valueTypeHidden = row.querySelector('.charge-value-type-hidden');
    const amountInput = row.querySelector('.charge-amount-input');
    const amountHelp = row.querySelector('.charge-amount-help');
    const descriptionInput = row.querySelector('input[name*="[description]"]');

    effectDisplay.value = effect;
    effectHidden.value = effect;

    valueTypeDisplay.value = valueType;
    valueTypeHidden.value = valueType;

    if (descriptionInput && !descriptionInput.value.trim() && typeName !== '') {
        descriptionInput.value = typeName;
    }

    if (!isNaN(defaultValue) && defaultValue > 0) {
        amountInput.value = defaultValueRaw;
        amountInput.readOnly = true;
        amountInput.classList.add('bg-light');
        amountInput.required = true;

        if (amountHelp) {
            amountHelp.textContent = 'This value is fixed from deduction setup and cannot be edited here.';
        }
    } else {
        amountInput.readOnly = false;
        amountInput.classList.remove('bg-light');
        amountInput.required = !!select.value;

        if (amountInput.value === '' || amountInput.value === defaultValueRaw) {
            amountInput.value = '';
        }

        if (amountHelp) {
            amountHelp.textContent = 'Enter the applicable amount/value for this charge. This field is required.';
        }
    }

    refreshChargeOptions();
}
    function addChargeRow() {
        const tableBody = document.querySelector('#other-charges-table tbody');
        const rowIndex = tableBody.querySelectorAll('tr').length;

        const row = document.createElement('tr');
        row.classList.add('charge-row');
        row.innerHTML = `
            <td>
                <select name="other_charges[${rowIndex}][deduction_type_id]" class="form-control charge-type-select" onchange="handleChargeTypeChange(this)" data-row-index="${rowIndex}">
                    ${buildChargeTypeOptions()}
                </select>
            </td>
            <td>
                <input type="text" class="form-control charge-effect-display" readonly>
                <input type="hidden" name="other_charges[${rowIndex}][effect]" class="charge-effect-hidden">
            </td>
            <td>
                <input type="text" class="form-control charge-value-type-display" readonly>
                <input type="hidden" name="other_charges[${rowIndex}][value_type]" class="charge-value-type-hidden">
            </td>
            <td>
                <input type="text" name="other_charges[${rowIndex}][amount]" class="form-control charge-amount-input">
                <small class="text-muted charge-amount-help d-block mt-1"></small>
            </td>
            <td>
                <input type="text" name="other_charges[${rowIndex}][description]" class="form-control">
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeChargeRow(this)">Remove</button>
            </td>
        `;

        tableBody.appendChild(row);
        refreshChargeOptions();
    }

    function removeChargeRow(button) {
        const row = button.closest('tr');
        row.remove();
        refreshChargeOptions();
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.charge-type-select').forEach(function(select) {
            handleChargeTypeChange(select);
        });

        refreshChargeOptions();
    });
</script>
@endsection