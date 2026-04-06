{{-- resources/views/shares/reductions/create.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid">

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <strong>Please fix the following:</strong>
            <ul class="mb-0 mt-2">
                @foreach($errors->all() as $error)
                    <li>{!! $error !!}</li>
                @endforeach
            </ul>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <form action="{{ route('shares_reductions.store') }}" method="POST" id="shareReductionForm" autocomplete="off">
        @csrf

        <div class="row">

            <div class="col-md-8">

                <div class="card o-hidden mb-4">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="w-50 float-start card-title m-0">Reduce Member Shares / Deposits</h3>
                        <div class="dropdown dropleft text-end w-50 float-end">
                            <button class="btn bg-gray-100" id="dropdownMenuButton_share_reduction_form" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <i class="nav-icon i-Gear-2"></i>
                            </button>
                            <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_share_reduction_form">
                                <a class="dropdown-item" href="{{ route('shares_reductions.index') }}">View All Share Reductions</a>
                                <a class="dropdown-item" href="{{ route('shares_reductions.create') }}">Open Fresh Form</a>
                            </div>
                        </div>
                    </div>

                    <div class="card-body">
                        <div class="card-title mb-3">Reduction Details</div>

                        <div class="mb-3">
                            <a href="{{ route('shares_reductions.index') }}" class="btn btn-sm btn-outline-secondary me-2">
                                Back to Listing
                            </a>
                            <a href="{{ route('shares_reductions.create') }}" class="btn btn-sm btn-outline-primary">
                                Fresh Form
                            </a>
                        </div>

                        <div class="row">
                            <div class="col-md-4 form-group mb-3">
                                <label for="period">Period (YYYYMM)</label>
                                <input
                                    class="form-control"
                                    id="period"
                                    type="text"
                                    name="period"
                                    maxlength="6"
                                    value="{{ old('period', $defaultPeriod ?? date('Ym')) }}"
                                    placeholder="202604"
                                >
                                <small class="text-muted">Defaults to current active period.</small>
                            </div>

                            <div class="col-md-4 form-group mb-3">
                                <label for="date_paid">Reduction Date</label>
                                <input
                                    class="form-control"
                                    id="date_paid"
                                    type="date"
                                    name="date_paid"
                                    value="{{ old('date_paid', $defaultDate ?? date('Y-m-d')) }}"
                                    required
                                >
                            </div>

                            <div class="col-md-4 form-group mb-3">
                                <label for="amount">Amount Per Member</label>
                                <input
                                    class="form-control"
                                    id="amount"
                                    type="number"
                                    name="amount"
                                    step="0.01"
                                    min="0.01"
                                    value="{{ old('amount') }}"
                                    placeholder="0.00"
                                    required
                                >
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="document_no">Document Number</label>
                                <input
                                    class="form-control"
                                    id="document_no"
                                    type="text"
                                    name="document_no"
                                    maxlength="100"
                                    value="{{ old('document_no') }}"
                                    placeholder="JV-001 / ADJ-001 / REF-001"
                                    required
                                >
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="reference_code">Reference Code</label>
                                <input
                                    class="form-control"
                                    id="reference_code"
                                    type="text"
                                    name="reference_code"
                                    maxlength="50"
                                    value="{{ old('reference_code') }}"
                                    placeholder="Optional short code"
                                >
                            </div>

                            <div class="col-md-12 form-group mb-3">
                                <label for="description">Description</label>
                                <textarea
                                    class="form-control"
                                    id="description"
                                    name="description"
                                    rows="3"
                                    maxlength="255"
                                    placeholder="Reason for reducing member shares/deposits"
                                    required
                                >{{ old('description') }}</textarea>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card o-hidden mb-4">
                    <div class="card-body">
                        <div class="card-title mb-3">Safety Controls</div>

                        <div class="row">
                            <div class="col-md-12 mb-3">
                                <input type="hidden" name="prevent_negative_balances" value="0">
                                <label class="checkbox checkbox-primary">
                                    <input
                                        type="checkbox"
                                        name="prevent_negative_balances"
                                        id="prevent_negative_balances"
                                        value="1"
                                        {{ old('prevent_negative_balances', '1') == '1' ? 'checked' : '' }}
                                    >
                                    <span>Prevent deductions that would make a member's share balance go below zero</span>
                                    <span class="checkmark"></span>
                                </label>
                                <div class="small text-muted ms-4">
                                    Checked by default. If checked, no deduction should be done for any member whose <code>member_total_share</code> is less than the amount being deducted.
                                </div>
                            </div>

                            <div class="col-md-12 mb-0">
                                <input type="hidden" name="only_members_active_in_selected_period" value="0">
                                <label class="checkbox checkbox-primary">
                                    <input
                                        type="checkbox"
                                        name="only_members_active_in_selected_period"
                                        id="only_members_active_in_selected_period"
                                        value="1"
                                        {{ old('only_members_active_in_selected_period', '1') == '1' ? 'checked' : '' }}
                                    >
                                    <span>Only deduct members who were active in the selected period</span>
                                    <span class="checkmark"></span>
                                </label>
                                <div class="small text-muted ms-4">
                                    Checked by default. Example: if period is <code>202602</code>, members who joined in <code>202603</code> should not be deducted.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card o-hidden mb-4">
                    <div class="card-body">
                        <div class="card-title mb-3">Debit Account Selection</div>

                        <div class="row">
                            <div class="col-md-12 form-group mb-3 position-relative">
                                <label for="sub_account_name">Search Account</label>
                                <input
                                    class="form-control"
                                    id="sub_account_name"
                                    type="text"
                                    name="sub_account_name"
                                    value="{{ old('sub_account_name') }}"
                                    placeholder="Search by main account or sub account"
                                >
                                <input type="hidden" name="sub_account_id" id="sub_account_id" value="{{ old('sub_account_id') }}">
                                <small class="text-muted">
                                    Format: Sub Account Name - MainAccountCode/SubAccountCode
                                </small>

                                <div id="accountResults" class="live-search-results d-none"></div>
                            </div>

                            <div class="col-md-12">
                                <div class="selected-box" id="selectedAccountBox">
                                    <div class="small text-muted mb-1">Selected account</div>
                                    <div id="selectedAccountText" class="fw-bold">
                                        {{ old('sub_account_name') ?: 'No account selected yet.' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card o-hidden mb-4">
                    <div class="card-body">
                        <div class="card-title mb-3">Members to Affect</div>

                        <div class="mb-3">
                            <input type="hidden" name="apply_to_all" value="0">
                            <label class="switch switch-primary me-3">
                                <span>Apply to all active, non-deleted members</span>
                                <input
                                    type="checkbox"
                                    id="apply_to_all"
                                    name="apply_to_all"
                                    value="1"
                                    {{ old('apply_to_all', '1') == '1' ? 'checked' : '' }}
                                >
                                <span class="slider"></span>
                            </label>
                        </div>

                        <div id="memberSelectionArea" class="{{ old('apply_to_all', '1') == '1' ? 'd-none' : '' }}">
                            <div class="row">
                                <div class="col-md-12 form-group mb-3 position-relative">
                                    <label for="member_search">Search Member</label>
                                    <input
                                        class="form-control"
                                        id="member_search"
                                        type="text"
                                        placeholder="Search by member name, SACCO ID, phone, ID number or email"
                                    >
                                    <small class="text-muted">
                                        Select the members you want to reduce. If hidden, the reduction will affect all active members, subject to the safety controls above.
                                    </small>

                                    <div id="memberResults" class="live-search-results d-none"></div>
                                </div>

                                <div class="col-md-12">
                                    <div class="selected-box">
                                        <div class="small text-muted mb-2">Selected members</div>
                                        <div id="selectedMembersWrap" class="d-flex flex-wrap gap-2"></div>
                                        <div id="selectedMembersEmpty" class="text-muted">No members selected yet.</div>
                                        <div id="selectedMembersInputs"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="col-md-4">

                <div class="card o-hidden mb-4">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="w-50 float-start card-title m-0">Posting Summary</h3>
                        <div class="text-end w-50 float-end">
                            <span class="badge bg-danger">Reduction</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="summary-item">
                            <span>Shares table entry</span>
                            <strong>Negative amount</strong>
                        </div>
                        <div class="summary-item">
                            <span>Member cumulative shares</span>
                            <strong>Deduct</strong>
                        </div>
                        <div class="summary-item">
                            <span>Default share account</span>
                            <strong>Credit</strong>
                        </div>
                        <div class="summary-item mb-0">
                            <span>Selected account</span>
                            <strong>Debit</strong>
                        </div>
                    </div>
                </div>

                <div class="card o-hidden mb-4">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="w-50 float-start card-title m-0">Navigation</h3>
                        <div class="text-end w-50 float-end">
                            <span class="badge bg-primary">Links</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <a href="{{ route('shares_reductions.index') }}" class="btn btn-outline-secondary btn-sm">
                                View All Share Reductions
                            </a>
                            <a href="{{ route('shares_reductions.create') }}" class="btn btn-outline-primary btn-sm">
                                Reload Blank Form
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card o-hidden mb-4">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="w-50 float-start card-title m-0">Notes</h3>
                        <div class="text-end w-50 float-end">
                            <span class="badge bg-info">Info</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="small text-muted">
                            <p class="mb-2">
                                This form posts a negative transaction to <code>sacco_shares</code> and reduces <code>member_total_share</code>.
                            </p>
                            <p class="mb-2">
                                The selected account is debited while the default member shares account is credited.
                            </p>
                            <p class="mb-2">
                                The two safety controls above are checked by default.
                            </p>
                            <p class="mb-0">
                                If you uncheck them, the controller must also allow that behavior.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="card o-hidden mb-4">
                    <div class="card-body">
                        <button type="submit" class="btn btn-danger w-100" id="submitBtn">
                            Save Share Reduction
                        </button>
                    </div>
                </div>

            </div>

        </div>
    </form>
</div>

 
<style>
    .live-search-results {
        position: absolute;
        top: calc(100% + 4px);
        left: 0;
        right: 0;
        z-index: 1050;
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: .5rem;
        box-shadow: 0 8px 20px rgba(0,0,0,.08);
        max-height: 260px;
        overflow-y: auto;
    }

    .live-search-item {
        padding: .75rem .9rem;
        border-bottom: 1px solid #f1f1f1;
        cursor: pointer;
        font-size: 13px;
        line-height: 1.35;
    }

    .live-search-item:last-child {
        border-bottom: 0;
    }

    .live-search-item:hover {
        background: #f8f9fa;
    }

    .selected-box {
        background: #fafafa;
        border: 1px solid #e9ecef;
        border-radius: .5rem;
        padding: .85rem;
    }

    .member-chip {
        display: inline-flex;
        align-items: center;
        gap: .45rem;
        background: #f1f3f5;
        border: 1px solid #dee2e6;
        border-radius: 999px;
        padding: .35rem .75rem;
        font-size: 12px;
    }

    .member-chip button {
        border: 0;
        background: transparent;
        padding: 0;
        line-height: 1;
        color: #dc3545;
        font-weight: 700;
        cursor: pointer;
    }

    .summary-item {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        padding: .7rem 0;
        border-bottom: 1px dashed #e9ecef;
        font-size: 13px;
    }

    @media (max-width: 767.98px) {
        .summary-item {
            flex-direction: column;
            gap: .15rem;
        }
    }
</style>
 
<script>
document.addEventListener('DOMContentLoaded', function () {
    const applyToAll = document.getElementById('apply_to_all');
    const memberSelectionArea = document.getElementById('memberSelectionArea');
    const memberSearch = document.getElementById('member_search');
    const memberResults = document.getElementById('memberResults');
    const selectedMembersWrap = document.getElementById('selectedMembersWrap');
    const selectedMembersInputs = document.getElementById('selectedMembersInputs');
    const selectedMembersEmpty = document.getElementById('selectedMembersEmpty');

    const accountSearch = document.getElementById('sub_account_name');
    const accountResults = document.getElementById('accountResults');
    const subAccountId = document.getElementById('sub_account_id');
    const selectedAccountText = document.getElementById('selectedAccountText');

    const form = document.getElementById('shareReductionForm');

    let memberTimer = null;
    let accountTimer = null;
    let selectedMembers = [];

    function toggleMemberArea() {
        if (applyToAll.checked) {
            memberSelectionArea.classList.add('d-none');
        } else {
            memberSelectionArea.classList.remove('d-none');
        }
    }

    applyToAll.addEventListener('change', toggleMemberArea);
    toggleMemberArea();

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    function hideResults(box) {
        box.classList.add('d-none');
        box.innerHTML = '';
    }

    function renderSelectedMembers() {
        selectedMembersWrap.innerHTML = '';
        selectedMembersInputs.innerHTML = '';

        if (selectedMembers.length === 0) {
            selectedMembersEmpty.classList.remove('d-none');
            return;
        }

        selectedMembersEmpty.classList.add('d-none');

        selectedMembers.forEach((member) => {
            const chip = document.createElement('div');
            chip.className = 'member-chip';
            chip.innerHTML = `
                <span>${escapeHtml(member.label)}</span>
                <button type="button" data-id="${member.id}" title="Remove">&times;</button>
            `;
            selectedMembersWrap.appendChild(chip);

            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'member_ids[]';
            input.value = member.id;
            selectedMembersInputs.appendChild(input);
        });

        selectedMembersWrap.querySelectorAll('button').forEach((btn) => {
            btn.addEventListener('click', function () {
                const id = parseInt(this.getAttribute('data-id'));
                selectedMembers = selectedMembers.filter(m => parseInt(m.id) !== id);
                renderSelectedMembers();
            });
        });
    }

    async function fetchMembers(query) {
        const url = `{{ route('shares_reductions.search_members') }}?query=${encodeURIComponent(query)}`;
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        return await response.json();
    }

    async function fetchAccounts(query) {
        const url = `{{ route('shares_reductions.search_accounts') }}?query=${encodeURIComponent(query)}`;
        const response = await fetch(url, {
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        });
        return await response.json();
    }

    memberSearch?.addEventListener('input', function () {
        const query = this.value.trim();

        clearTimeout(memberTimer);

        if (query.length < 2) {
            hideResults(memberResults);
            return;
        }

        memberTimer = setTimeout(async () => {
            try {
                const rows = await fetchMembers(query);

                if (!Array.isArray(rows) || rows.length === 0) {
                    memberResults.innerHTML = `<div class="live-search-item text-muted">No members found.</div>`;
                    memberResults.classList.remove('d-none');
                    return;
                }

                memberResults.innerHTML = rows.map(row => `
                    <div class="live-search-item"
                         data-id="${row.id}"
                         data-label="${escapeHtml(row.label)}">
                        <div class="fw-bold">${escapeHtml(row.label)}</div>
                        <div class="text-muted small">Available shares: ${Number(row.shares || 0).toLocaleString()}</div>
                    </div>
                `).join('');

                memberResults.classList.remove('d-none');

                memberResults.querySelectorAll('.live-search-item').forEach(item => {
                    item.addEventListener('click', function () {
                        const id = parseInt(this.getAttribute('data-id'));
                        const label = this.getAttribute('data-label');

                        if (!selectedMembers.some(m => parseInt(m.id) === id)) {
                            selectedMembers.push({ id, label });
                            renderSelectedMembers();
                        }

                        memberSearch.value = '';
                        hideResults(memberResults);
                    });
                });
            } catch (e) {
                hideResults(memberResults);
            }
        }, 250);
    });

    accountSearch?.addEventListener('input', function () {
        const query = this.value.trim();
        subAccountId.value = '';
        selectedAccountText.textContent = 'No account selected yet.';

        clearTimeout(accountTimer);

        if (query.length < 2) {
            hideResults(accountResults);
            return;
        }

        accountTimer = setTimeout(async () => {
            try {
                const rows = await fetchAccounts(query);

                if (!Array.isArray(rows) || rows.length === 0) {
                    accountResults.innerHTML = `<div class="live-search-item text-muted">No accounts found.</div>`;
                    accountResults.classList.remove('d-none');
                    return;
                }

                accountResults.innerHTML = rows.map(row => `
                    <div class="live-search-item"
                         data-id="${row.id}"
                         data-label="${escapeHtml(row.label)}">
                        <div class="fw-bold">${escapeHtml(row.label)}</div>
                        <div class="text-muted small">${escapeHtml(row.main_account_name || '')}</div>
                    </div>
                `).join('');

                accountResults.classList.remove('d-none');

                accountResults.querySelectorAll('.live-search-item').forEach(item => {
                    item.addEventListener('click', function () {
                        const id = this.getAttribute('data-id');
                        const label = this.getAttribute('data-label');

                        subAccountId.value = id;
                        accountSearch.value = label;
                        selectedAccountText.textContent = label;

                        hideResults(accountResults);
                    });
                });
            } catch (e) {
                hideResults(accountResults);
            }
        }, 250);
    });

    document.addEventListener('click', function (e) {
        if (memberResults && !memberResults.contains(e.target) && e.target !== memberSearch) {
            hideResults(memberResults);
        }

        if (accountResults && !accountResults.contains(e.target) && e.target !== accountSearch) {
            hideResults(accountResults);
        }
    });

    form.addEventListener('submit', function (e) {
        if (!subAccountId.value) {
            e.preventDefault();
            alert('Please select a valid debit account from the search results.');
            accountSearch.focus();
            return;
        }

        if (!applyToAll.checked && selectedMembers.length === 0) {
            e.preventDefault();
            alert('Select at least one member, or switch on "Apply to all active, non-deleted members".');
            memberSearch.focus();
            return;
        }
    });

    renderSelectedMembers();
});
</script>
@endsection