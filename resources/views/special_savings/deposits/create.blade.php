@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Post Special Saving Deposit</h1>
        <ul>
            <li><a href="{{ route('special_savings.dashboard') }}">Special Savings</a></li>
            <li><a href="{{ route('special_savings.accounts.index') }}">Accounts</a></li>
            <li>Deposit</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @include('special_savings.partials.alerts')
    @include('special_savings.partials.nav')

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">Deposit Details</div>

                    <form method="POST" action="{{ route('special_savings.deposits.store') }}">
                        @csrf

                        <input
                            type="hidden"
                            id="special_saving_transaction_account_id"
                            name="special_saving_transaction_account_id"
                            value="{{ old('special_saving_transaction_account_id') }}"
                            required>

                        <div class="row">
                            <div class="col-md-12 form-group mb-3">
                                <label for="account_search">Search Special Saving Account</label>

                                <input
                                    type="text"
                                    id="account_search"
                                    class="form-control"
                                    placeholder="Search by account number, SACCO no, phone number, national ID, or member name"
                                    autocomplete="off">

                                <div id="search_status_box" class="mt-2" style="display:none;"></div>

                                <div id="search_results_box" class="mt-3" style="display:none;">
                                    <div class="card border">
                                        <div class="card-body p-3">
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <strong id="search_results_title">Search Results</strong>
                                                <span id="search_results_count" class="badge badge-secondary"></span>
                                            </div>

                                            <div id="search_results_content"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-12" id="selected_account_box" style="display:none;">
                                <div class="alert alert-info mb-3">
                                    <strong id="selected_account_name"></strong><br>
                                    <span id="selected_account_details"></span><br>
                                    <span id="selected_account_balance"></span>
                                </div>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="special_saving_transaction_amount">Amount</label>

                                <input
                                    type="number"
                                    step="0.01"
                                    min="0.01"
                                    id="special_saving_transaction_amount"
                                    name="special_saving_transaction_amount"
                                    value="{{ old('special_saving_transaction_amount') }}"
                                    class="form-control"
                                    placeholder="Enter amount"
                                    required>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="special_saving_transaction_date">Transaction Date</label>

                                <input
                                    type="date"
                                    id="special_saving_transaction_date"
                                    name="special_saving_transaction_date"
                                    value="{{ old('special_saving_transaction_date', date('Y-m-d')) }}"
                                    class="form-control"
                                    required>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="special_saving_transaction_reference">Reference</label>

                                <input
                                    type="text"
                                    id="special_saving_transaction_reference"
                                    name="special_saving_transaction_reference"
                                    value="{{ old('special_saving_transaction_reference') }}"
                                    class="form-control"
                                    placeholder="Receipt, M-Pesa code, payroll ref, or bank ref">
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="special_saving_transaction_sub_account_id">Source Ledger</label>

                                <select
                                    id="special_saving_transaction_sub_account_id"
                                    name="special_saving_transaction_sub_account_id"
                                    class="form-control">
                                    <option value="">Select source ledger</option>

                                    @foreach(($sub_accounts ?? []) as $acc)
                                        <option
                                            value="{{ $acc->sub_account_id }}"
                                            {{ old('special_saving_transaction_sub_account_id') == $acc->sub_account_id ? 'selected' : '' }}>
                                            {{ $acc->sub_account_name }} - {{ $acc->sub_account_code }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-12 form-group mb-3">
                                <label for="special_saving_transaction_description">Description</label>

                                <textarea
                                    id="special_saving_transaction_description"
                                    name="special_saving_transaction_description"
                                    class="form-control"
                                    rows="2"
                                    placeholder="Optional description">{{ old('special_saving_transaction_description') }}</textarea>
                            </div>

                            <div class="col-md-12">
                                <button
                                    id="post_deposit_btn"
                                    class="btn btn-primary"
                                    onclick="return validateSelectedAccount();"
                                    disabled>
                                    Post Deposit
                                </button>

                                <a href="{{ route('special_savings.accounts.index') }}" class="btn btn-outline-secondary">
                                    Cancel
                                </a>
                            </div>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const accountSearchInput = document.getElementById('account_search');
    const selectedAccountInput = document.getElementById('special_saving_transaction_account_id');

    const searchStatusBox = document.getElementById('search_status_box');
    const searchResultsBox = document.getElementById('search_results_box');
    const searchResultsTitle = document.getElementById('search_results_title');
    const searchResultsCount = document.getElementById('search_results_count');
    const searchResultsContent = document.getElementById('search_results_content');

    const selectedAccountBox = document.getElementById('selected_account_box');
    const selectedAccountName = document.getElementById('selected_account_name');
    const selectedAccountDetails = document.getElementById('selected_account_details');
    const selectedAccountBalance = document.getElementById('selected_account_balance');

    const postDepositBtn = document.getElementById('post_deposit_btn');

    let searchTimer = null;

    accountSearchInput.addEventListener('keyup', function () {
        const q = this.value.trim();

        clearSelectedAccount();
        clearSearchResults();

        if (searchTimer) {
            clearTimeout(searchTimer);
        }

        if (q.length < 2) {
            showStatus('Enter at least 2 characters.', 'light');
            return;
        }

        showStatus('Searching...', 'light');

        searchTimer = setTimeout(function () {
            fetch("{{ route('special_savings.search.accounts') }}?q=" + encodeURIComponent(q))
                .then(response => response.json())
                .then(payload => {
                    clearSearchResults();

                    const records = payload.records || [];
                    const members = payload.members || [];

                    if (payload.status === 'empty') {
                        showStatus(payload.message || 'No record found.', 'danger');
                        return;
                    }

                    if (payload.status === 'ok' && records.length === 1) {
                        showStatus(payload.message || 'One active Special Savings account found.', 'success');
                        renderAccountResults(records, true, 'Matching Special Savings Account');
                        return;
                    }

                    if (payload.status === 'ambiguous') {
                        showStatus(payload.message || 'More than one matching account found.', 'warning');
                        renderAccountResults(records, false, 'Multiple Matching Special Savings Accounts');
                        return;
                    }

                    if (payload.status === 'member_found_no_account') {
                        showStatus(payload.message || 'Member found, but no active Special Savings account exists.', 'warning');
                        renderMemberResults(members, 'Member Found Without Special Savings Account');
                        return;
                    }

                    showStatus('No usable result returned.', 'danger');
                })
                .catch(() => {
                    clearSearchResults();
                    showStatus('Search failed. Try again.', 'danger');
                });
        }, 300);
    });

    function renderAccountResults(records, canSelect, title) {
        searchResultsTitle.innerText = title;
        searchResultsCount.innerText = records.length + ' found';
        searchResultsContent.innerHTML = '';

        records.forEach(row => {
            const item = document.createElement('div');
            item.className = 'border rounded p-3 mb-2';
            item.style.backgroundColor = canSelect ? '#ffffff' : '#fff8e1';

            const principal = formatMoney(row.special_saving_account_principal_balance);
            const accrued = formatMoney(row.special_saving_account_accrued_interest_balance);
            const available = formatMoney(row.special_saving_account_available_interest_balance);
            const total = formatMoney(row.special_saving_account_total_balance);

            item.innerHTML = `
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="mb-1">${safeText(row.member_name)}</h5>

                        <div class="text-muted mb-1">
                            SACCO No: <strong>${safeText(row.member_sacco_id)}</strong>
                            | Phone: <strong>${safeText(row.member_phone_no)}</strong>
                            | ID: <strong>${safeText(row.member_national_id)}</strong>
                        </div>

                        <div class="mb-1">
                            Account: <strong>${safeText(row.special_saving_account_number)}</strong>
                            | Product: <strong>${safeText(row.special_saving_product_name)}</strong>
                        </div>

                        <div class="text-muted">
                            Principal: <strong>${principal}</strong>
                            | Accrued: <strong>${accrued}</strong>
                            | Available: <strong>${available}</strong>
                            | Total: <strong>${total}</strong>
                        </div>
                    </div>

                    <div>
                        ${
                            canSelect
                                ? '<button type="button" class="btn btn-sm btn-primary select-account-btn">Select</button>'
                                : '<span class="badge badge-warning">Refine Search</span>'
                        }
                    </div>
                </div>
            `;

            if (canSelect) {
                item.querySelector('.select-account-btn').addEventListener('click', function () {
                    selectAccount(row);
                });
            }

            searchResultsContent.appendChild(item);
        });

        searchResultsBox.style.display = 'block';
    }

    function renderMemberResults(members, title) {
        searchResultsTitle.innerText = title;
        searchResultsCount.innerText = members.length + ' found';
        searchResultsContent.innerHTML = '';

        members.forEach(row => {
            const item = document.createElement('div');
            item.className = 'border rounded p-3 mb-2';
            item.style.backgroundColor = '#fff8e1';

            item.innerHTML = `
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h5 class="mb-1">${safeText(row.member_name)}</h5>

                        <div class="text-muted mb-1">
                            SACCO No: <strong>${safeText(row.member_sacco_id)}</strong>
                            | Phone: <strong>${safeText(row.member_phone_no)}</strong>
                            | ID: <strong>${safeText(row.member_national_id)}</strong>
                        </div>

                        <div class="text-danger">
                            No active Special Savings account found for this member.
                        </div>
                    </div>

                    <div>
                        <a
                            href="{{ route('special_savings.accounts.create') }}?member_id=${row.member_id}"
                            class="btn btn-sm btn-outline-primary">
                            Open Account
                        </a>
                    </div>
                </div>
            `;

            searchResultsContent.appendChild(item);
        });

        searchResultsBox.style.display = 'block';
    }

    function selectAccount(row) {
        selectedAccountInput.value = row.special_saving_account_id;

        accountSearchInput.value =
            safeText(row.member_name) +
            ' - ' +
            safeText(row.member_sacco_id) +
            ' - ' +
            safeText(row.special_saving_account_number);

        selectedAccountName.innerText =
            safeText(row.member_name) + ' - ' + safeText(row.member_sacco_id);

        selectedAccountDetails.innerText =
            'Account: ' + safeText(row.special_saving_account_number) +
            ' | Product: ' + safeText(row.special_saving_product_name) +
            ' | Phone: ' + safeText(row.member_phone_no) +
            ' | ID: ' + safeText(row.member_national_id);

        selectedAccountBalance.innerText =
            'Principal: ' + formatMoney(row.special_saving_account_principal_balance) +
            ' | Accrued Interest: ' + formatMoney(row.special_saving_account_accrued_interest_balance) +
            ' | Available Interest: ' + formatMoney(row.special_saving_account_available_interest_balance) +
            ' | Total: ' + formatMoney(row.special_saving_account_total_balance);

        selectedAccountBox.style.display = 'block';
        searchResultsBox.style.display = 'none';
        searchResultsContent.innerHTML = '';

        postDepositBtn.disabled = false;

        showStatus('Account selected. You can now post the deposit.', 'success');
    }

    function clearSelectedAccount() {
        selectedAccountInput.value = '';
        selectedAccountBox.style.display = 'none';
        postDepositBtn.disabled = true;
    }

    function clearSearchResults() {
        searchStatusBox.style.display = 'none';
        searchStatusBox.innerHTML = '';
        searchResultsBox.style.display = 'none';
        searchResultsContent.innerHTML = '';
        searchResultsCount.innerText = '';
    }

    function showStatus(message, type) {
        let className = 'alert alert-light border py-2 mb-0';

        if (type === 'success') {
            className = 'alert alert-success py-2 mb-0';
        }

        if (type === 'warning') {
            className = 'alert alert-warning py-2 mb-0';
        }

        if (type === 'danger') {
            className = 'alert alert-danger py-2 mb-0';
        }

        searchStatusBox.className = className;
        searchStatusBox.innerHTML = message;
        searchStatusBox.style.display = 'block';
    }

    function validateSelectedAccount() {
        if (!selectedAccountInput.value) {
            alert('Please search and select one exact special saving account first.');
            accountSearchInput.focus();
            return false;
        }

        return confirm('Post this special saving deposit?');
    }

    function formatMoney(value) {
        return Number(value || 0).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function safeText(value) {
        return value || '-';
    }
</script>
@endsection