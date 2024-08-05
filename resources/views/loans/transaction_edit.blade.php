@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Edit Transaction in Batch: {{ $batch->batch_reference }}</h1>
    <div class="header-part-right">
        <ul>
            @if(Auth::check())
                <li>{{ Auth::user()->member_name }}</li>
            @endif
            @if(isset($currentPeriod))
                <li><a href="#">{{ $currentPeriod->period_name }}</a></li>
            @endif
            <li><i class="i-Full-Screen header-icon d-none d-sm-inline-block" data-fullscreen=""></i></li>
        </ul>
    </div>
</div>
<div class="separator-breadcrumb border-top"></div>

<div class="row mb-4">
    <div class="col-md-12 mb-4">
        <div class="card text-start">
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger">
                        @foreach(session('error') as $error)
                            <p>{{ $error }}</p>
                        @endforeach
                    </div>
                @endif
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <form action="{{ route('loans.batch.transactions.update', [$batch->batch_id, $transaction->batch_trans_id]) }}" method="POST" autocomplete="off">
                    @csrf
                    <div class="form-group row">
                    <div class="col-md-12 mb-3">
    <label for="member">Member*</label>
    <input type="text" id="member" name="batch_trans_member_id" class="form-control" autocomplete="off" required value="{{ old('batch_trans_member_id', $transaction->member_name . ' - (' . $transaction->member_sacco_id . ')') }}" readonly>
    <div id="suggestions-box" class="suggestions-box"></div>
</div>

                        <div class="col-md-6 mb-3">
                            <label for="loan_type">Loan Type*</label>
                            <select name="batch_trans_loan_type" id="loan_type" class="form-control" required>
                                @foreach($loanTypes as $type)
                                    <option value="{{ $type->loan_type_id }}" {{ old('batch_trans_loan_type', $transaction->batch_trans_loan_type) == $type->loan_type_id ? 'selected' : '' }}>{{ $type->loan_type_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="loan_category">Loan Category*</label>
                            <select name="batch_trans_loan_category" id="loan_category" class="form-control" required>
                                @foreach($loanCategories as $category)
                                    <option value="{{ $category->loan_category_id }}" {{ old('batch_trans_loan_category', $transaction->batch_trans_loan_category) == $category->loan_category_id ? 'selected' : '' }}>{{ $category->loan_category_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="loan_amount">Loan Amount*</label>
                            <input type="text" name="batch_trans_loan_amount" id="loan_amount" class="form-control" required value="{{ old('batch_trans_loan_amount', $transaction->batch_trans_loan_amount) }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="commission_amount">Commission Amount (Optional)</label>
                            <input type="text" name="batch_trans_commission_amount" id="commission_amount" class="form-control" value="{{ old('batch_trans_commission_amount', $transaction->batch_trans_commission) }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="loan_duration">Loan Duration (Months)*</label>
                            <input type="number" name="batch_trans_loan_duration" id="loan_duration" class="form-control" required value="{{ old('batch_trans_loan_duration', $transaction->batch_trans_loan_duration) }}">
                        </div>
                        <div class="col-md-6 mb-3">
    <label for="loan_to_top_up">Loan to Top Up (Optional)</label>
    <select name="batch_trans_loan_to_top_up" id="loan_to_top_up" class="form-control">
        <option value="">Select Loan</option>
        @forelse($loansToTopUp as $loan)
            <option value="{{ $loan->loan_id }}" {{ old('batch_trans_loan_to_top_up', $transaction->batch_trans_loan_to_top_up) == $loan->loan_id ? 'selected' : '' }}>
                {{ $loan->loan_type_name }} - (ID: {{ $loan->loan_id }}) Balance: {{ number_format($loan->loan_balance, 2) }}
            </option>
        @empty
            <option value="" disabled>No loans available for top-up</option>
        @endforelse
    </select>
</div>

                        <div class="col-md-6 mb-3">
                            <label for="doc_no">Document No*</label>
                            <input type="text" name="batch_trans_doc_no" id="doc_no" class="form-control" required value="{{ old('batch_trans_doc_no', $transaction->batch_trans_doc_no) }}">
                        </div>
                        <div class="col-md-12 mb-3">
                            <label for="description">Description</label>
                            <textarea name="batch_trans_description" id="description" class="form-control">{{ old('batch_trans_description', $transaction->batch_trans_description) }}</textarea>
                        </div>
                    </div>

                    <!-- Guarantors Section -->
                    <div class="form-group row">
                        <div class="col-md-12 mb-3">
                            <label for="guarantors">Guarantors</label>
                            <table class="table table-bordered" id="guarantors-table">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Amount</th>
                                        <th>Free Shares</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($guarantors as $index => $guarantor)
                                    <tr>
                                        <td>
                                            <input type="text" name="guarantors[{{ $index }}][member]" class="form-control guarantor-member" onkeyup="showHintGuarantor(this.value, {{ $index }})" autocomplete="off" value="{{ old('guarantors.'.$index.'.member', $guarantor->member_name . ' - (' . $guarantor->member_sacco_id . ')') }}">
                                            <div id="suggestions-box-{{ $index }}" class="suggestions-box"></div>
                                        </td>
                                        <td>
                                            <input type="text" name="guarantors[{{ $index }}][amount]" class="form-control" value="{{ old('guarantors.'.$index.'.amount', $guarantor->guarantors_amount_guaranteed) }}">
                                        </td>
                                        <td>
                                            <input type="text" name="guarantors[{{ $index }}][free_shares]" class="form-control" readonly value="{{ old('guarantors.'.$index.'.free_shares', $guarantor->free_shares ?? '') }}">
                                        </td>
                                        <td>
                                            <button type="button" class="btn btn-danger" onclick="removeGuarantorRow(this)">Remove</button>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <button type="button" class="btn btn-secondary" onclick="addGuarantorRow()">Add Row</button>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary">Update Transaction</button>
                    <a href="{{ route('loans.batch.transactions', $batch->batch_id) }}" class="btn btn-secondary">Back to Transactions</a>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
    .suggestions-box {
        background-color: #fff;
        max-height: 150px;
        overflow-y: auto;
        position: absolute;
        z-index: 1000;
        width: 100%;
        box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
    }

    .suggestion-item {
        padding: 10px;
        cursor: pointer;
    }

    .suggestion-item:last-child {
        border-bottom: none;
    }

    .suggestion-item:hover {
        background-color: #f0f0f0;
    }
</style>

<script>
    function showHintMembers(str) {
        if (str.length == 0) {
            document.getElementById('suggestions-box').innerHTML = "";
            return;
        }
        let xmlhttp;
        if (window.XMLHttpRequest) {
            xmlhttp = new XMLHttpRequest();
        } else {
            xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
        }
        xmlhttp.onreadystatechange = function () {
            if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                let response = JSON.parse(xmlhttp.responseText);
                let suggestions = '';
                response.forEach(member => {
                    suggestions += `<div class="suggestion-item" onclick="selectMember('${member.label}', '${member.member_id}')">${member.label}</div>`;
                });
                document.getElementById('suggestions-box').innerHTML = suggestions;
            }
        };
        xmlhttp.open("GET", "{{ url('/search/loan_batch/members') }}?query=" + str, true);
        xmlhttp.send();
    }

    function selectMember(label, memberId) {
        document.getElementById('member').value = label;
        document.getElementById('suggestions-box').innerHTML = ''; // Clear suggestions

        // Fetch outstanding loans and free shares for the selected member
        fetchOutstandingLoans(memberId);
        fetchFreeShares(memberId);
    }

    function fetchOutstandingLoans(memberId) {
        let xmlhttp;
        if (window.XMLHttpRequest) {
            xmlhttp = new XMLHttpRequest();
        } else {
            xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
        }
        xmlhttp.onreadystatechange = function () {
            if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
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
        let loanMemberId = document.getElementById('member').value; // Retrieve the loan member ID from the input field
        let xmlhttp;

        if (window.XMLHttpRequest) {
            xmlhttp = new XMLHttpRequest();
        } else {
            xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
        }

        xmlhttp.onreadystatechange = function () {
            if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                let response = JSON.parse(xmlhttp.responseText);
                if (response && response.free_shares !== undefined) {
                    let freeSharesField = document.querySelectorAll('[name^="guarantors"]')[index * 3 + 2];
                    if (freeSharesField) {
                        freeSharesField.value = response.free_shares ? response.free_shares.toFixed(2) : '0.00';
                    } else {
                        console.error('Free shares input element not found for index:', index);
                    }
                } else {
                    console.error('Response does not contain free_shares or response is undefined.');
                }
            } else if (xmlhttp.readyState == 4) {
                console.error('Failed to fetch free shares:', xmlhttp.status, xmlhttp.statusText);
            }
        };

        let url = "{{ url('/loans/get-free-shares') }}?member_id=" + memberId + "&loan_member_id=" + (loanMemberId || '');
        xmlhttp.open("GET", url, true);
        xmlhttp.send();
    }

    function showHintGuarantor(str, index) {
        if (str.length == 0) {
            document.getElementById(`suggestions-box-${index}`).innerHTML = "";
            return;
        }
        let xmlhttp;
        if (window.XMLHttpRequest) {
            xmlhttp = new XMLHttpRequest();
        } else {
            xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
        }
        xmlhttp.onreadystatechange = function () {
            if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                let response = JSON.parse(xmlhttp.responseText);
                let suggestions = '';
                response.forEach(member => {
                    suggestions += `<div class="suggestion-item" onclick="selectGuarantor('${member.label}', '${member.member_id}', ${index})">${member.label}</div>`;
                });
                document.getElementById(`suggestions-box-${index}`).innerHTML = suggestions;
            }
        };
        xmlhttp.open("GET", "{{ url('/search/loan_batch/members') }}?query=" + str, true);
        xmlhttp.send();
    }

    function selectGuarantor(label, memberId, index) {
        document.querySelectorAll('.guarantor-member')[index].value = label;
        document.getElementById(`suggestions-box-${index}`).innerHTML = ''; // Clear suggestions

        // Fetch free shares for the selected guarantor
        fetchFreeShares(memberId, index);
    }

    function addGuarantorRow() {
        const table = document.getElementById('guarantors-table').getElementsByTagName('tbody')[0];
        const newRow = table.insertRow();
        const memberCell = newRow.insertCell(0);
        const amountCell = newRow.insertCell(1);
        const freeSharesCell = newRow.insertCell(2);
        const actionCell = newRow.insertCell(3);

        const rowIndex = table.rows.length - 1; // Get the index of the new row

        memberCell.innerHTML = `<input type="text" name="guarantors[${rowIndex}][member]" class="form-control guarantor-member" onkeyup="showHintGuarantor(this.value, ${rowIndex})" autocomplete="off"><div id="suggestions-box-${rowIndex}" class="suggestions-box"></div>`;
        amountCell.innerHTML = `<input type="text" name="guarantors[${rowIndex}][amount]" class="form-control">`;
        freeSharesCell.innerHTML = `<input type="text" name="guarantors[${rowIndex}][free_shares]" class="form-control guarantor-free-shares" readonly>`;
        actionCell.innerHTML = `<button type="button" class="btn btn-danger" onclick="removeGuarantorRow(this)">Remove</button>`;
    }

    function removeGuarantorRow(button) {
        const row = button.closest('tr');
        row.remove();
    }

</script>

@endsection
