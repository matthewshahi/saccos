@extends('layouts.app')

@section('content')
<div class="container-fluid">

    {{-- FLASH / VALIDATION MESSAGES --}}
    @if(session('error'))
        <div class="alert alert-danger">
            <strong>Error:</strong> {{ session('error') }}
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success">
            <strong>Success:</strong> {{ session('success') }}
        </div>
    @endif

    {{-- SEARCH CARD --}}
    <div class="card mb-4">
    <div class="card-header">
        <h4 class="mb-0">FOSA Transfers – Search Transactions</h4>
    </div>

    <div class="card-body">
        <div class="row g-3">

            {{-- Search --}}
            <div class="col-md-5">
                <label class="form-label fw-semibold">Search</label>
                <input type="text"
                       id="searchQuery"
                       class="form-control"
                       placeholder="Member name, phone, doc no, description"
                       autocomplete="off">
                <small class="text-muted">
                    Tip: search by doc no or description to quickly find MPesa references.
                </small>
            </div>

            {{-- FOSA Type --}}
            <div class="col-md-3">
                <label class="form-label fw-semibold">FOSA Type</label>
                <select id="fosaType" class="form-select">
                    <option value="">-- All Types --</option>
                    @foreach($types as $type)
                        <option value="{{ $type->type_id }}">{{ $type->type_name }}</option>
                    @endforeach
                </select>
                <small class="text-muted">
                    Phase 1 control: you can only post one type per transfer batch.
                </small>
            </div>

            {{-- Actions --}}
            <div class="col-md-4">
                <label class="form-label d-none d-md-block">&nbsp;</label>
                <div class="d-flex gap-2 align-items-end">
                    <button type="button"
                            class="btn btn-primary px-4"
                            onclick="loadFosa()">
                        Search
                    </button>

                    <button type="button"
                            class="btn btn-outline-secondary px-4"
                            onclick="resetSearch()">
                        Reset
                    </button>
                </div>
            </div>

        </div>
    </div>
</div>


    {{-- TRANSFER FORM --}}
    <form method="POST" action="{{ route('fosa.transfers.post') }}" id="transferForm">
        @csrf

        {{-- RESULTS TABLE --}}
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center">
                <h4 class="mb-0">Available FOSA Transactions</h4>
                <div class="ms-auto">
                    <span class="badge bg-light text-dark me-2" id="selectedCount">Selected: 0</span>
                    <span class="badge bg-light text-dark" id="selectedTotal">Total: 0</span>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive" style="max-height: 520px;">
                    <table class="table table-bordered table-hover mb-0">
                        <thead class="table-light" style="position: sticky; top: 0; z-index: 2;">
                            <tr>
                                <th width="40" class="text-center">
                                    <input type="checkbox" id="checkAll">
                                </th>
                                <th>Member</th>
                                <th>Phone</th>
                                <th>Type</th>
                                <th>Doc No</th>
                                <th>Description</th>
                                <th>Date Paid</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody id="fosaResults">
                            <tr>
                                <td colspan="8" class="text-center text-muted p-4">
                                    Use the search above to load transactions.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="p-3 border-top">
                    <small class="text-muted">
                        Controls: Only positive, not end-month processed, and not previously transferred transactions are shown.
                        Transfers create a new negative FOSA row and balanced ledger entries.
                    </small>
                </div>
            </div>
        </div>

        {{-- DESTINATION LEDGER --}}
        <div class="card">
    <div class="card-header">
        <h4 class="mb-0">Destination Ledger Account</h4>
    </div>

    <div class="card-body">
        <div class="row g-3">

            {{-- Ledger Search --}}
            <div class="col-md-7">
                <label class="form-label fw-semibold">Search Ledger Sub-Account</label>
                <input type="text"
                       id="ledgerSearchInput"
                       class="form-control"
                       placeholder="Type at least 2 characters (e.g., deposits, fees, loans, cash)"
                       autocomplete="off">
                <small class="text-muted">
                    You must select one account from the results list below.
                </small>

                {{-- Hidden field that actually posts --}}
                <input type="hidden"
                       name="destination_sub_account"
                       id="destination_sub_account"
                       value="">

                {{-- Results dropdown --}}
                <div class="border rounded mt-2 d-none"
                     id="ledgerResultsWrap"
                     style="max-height: 240px; overflow:auto;">
                    <div class="list-group list-group-flush" id="ledgerResults"></div>
                </div>

                <div class="mt-2">
                    <span class="badge bg-light text-dark" id="ledgerSelectedBadge">
                        No ledger selected
                    </span>
                </div>
            </div>

            {{-- Actions --}}
            <div class="col-md-5">
                <label class="form-label d-none d-md-block">&nbsp;</label>
                <div class="d-flex gap-2 align-items-end">

                    <button type="submit"
                            class="btn btn-danger px-4"
                            onclick="return confirmTransfer();">
                        Post Transfer
                    </button>

                    <button type="button"
                            class="btn btn-outline-secondary px-4"
                            onclick="clearLedgerSelection();">
                        Clear Ledger
                    </button>

                </div>
            </div>

        </div>

        <div class="mt-3">
            <div class="alert alert-warning mb-0">
                <strong>Important:</strong>
                This action reduces member FOSA balances and posts accounting entries.
                It is not reversible via edit; reversals should be done as a new accounting transaction.
            </div>
        </div>
    </div>
</div>


    </form>

</div>



<script>
let fosaDataCache = [];

function resetSearch() {
    document.getElementById('searchQuery').value = '';
    document.getElementById('fosaType').value = '';
    document.getElementById('fosaResults').innerHTML = `
        <tr>
            <td colspan="8" class="text-center text-muted p-4">
                Use the search above to load transactions.
            </td>
        </tr>`;
    document.getElementById('checkAll').checked = false;
    updateSelectedStats();
}

function loadFosa() {
    const q = document.getElementById('searchQuery').value || '';
    const type = document.getElementById('fosaType').value || '';

    fetch(`{{ route('fosa.transfers.search') }}?q=${encodeURIComponent(q)}&fosa_type_id=${encodeURIComponent(type)}`)
        .then(r => r.json())
        .then(data => {
            fosaDataCache = data || [];

            const tbody = document.getElementById('fosaResults');
            tbody.innerHTML = '';

            if (!fosaDataCache.length) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="8" class="text-center text-muted p-4">
                            No matching transactions found.
                        </td>
                    </tr>`;
                updateSelectedStats();
                return;
            }

            fosaDataCache.forEach(row => {
                const safeName = row.member_name ?? '';
                const safePhone = row.member_phone_no ?? '';
                const safeType = row.type_name ?? '';
                const safeDoc = row.fosa_doc_no ?? '';
                const safeDesc = row.fosa_description ?? '';
                const safeDate = row.fosa_date_paid ?? '';
                const amt = Number(row.fosa_amount_paying || 0);

                tbody.insertAdjacentHTML('beforeend', `
                    <tr>
                        <td class="text-center">
                            <input type="checkbox"
                                   class="fosaCheck"
                                   name="fosa_ids[]"
                                   value="${row.fosa_id}"
                                   data-amount="${amt}">
                        </td>
                        <td>${escapeHtml(safeName)}</td>
                        <td>${escapeHtml(safePhone)}</td>
                        <td>${escapeHtml(safeType)}</td>
                        <td>${escapeHtml(safeDoc)}</td>
                        <td>${escapeHtml(safeDesc)}</td>
                        <td>${escapeHtml(safeDate)}</td>
                        <td class="text-end">${amt.toLocaleString()}</td>
                    </tr>
                `);
            });

            wireCheckboxEvents();
            updateSelectedStats();
        })
        .catch(() => {
            alert('Failed to load transactions. Please try again.');
        });
}

function wireCheckboxEvents() {
    document.querySelectorAll('.fosaCheck').forEach(cb => {
        cb.addEventListener('change', updateSelectedStats);
    });
}

document.getElementById('checkAll').addEventListener('change', function () {
    document.querySelectorAll('.fosaCheck').forEach(cb => {
        cb.checked = this.checked;
    });
    updateSelectedStats();
});

function updateSelectedStats() {
    const checked = document.querySelectorAll('.fosaCheck:checked');
    let total = 0;

    checked.forEach(cb => {
        total += Number(cb.getAttribute('data-amount') || 0);
    });

    document.getElementById('selectedCount').innerText = `Selected: ${checked.length}`;
    document.getElementById('selectedTotal').innerText = `Total: ${total.toLocaleString()}`;
}

function confirmTransfer() {
    const checked = document.querySelectorAll('.fosaCheck:checked').length;
    const dest = document.getElementById('destination_sub_account').value;

    if (!checked) {
        alert('Please select at least one FOSA transaction.');
        return false;
    }
    if (!dest) {
        alert('Please select a destination ledger account.');
        return false;
    }

    return confirm('This will permanently reduce FOSA balances and post ledger entries.\n\nContinue?');
}

/* ------------------------
   Ledger AJAX Search (reliable)
------------------------- */
const ledgerInput = document.getElementById('ledgerSearchInput');
const ledgerWrap  = document.getElementById('ledgerResultsWrap');
const ledgerList  = document.getElementById('ledgerResults');
const ledgerHidden = document.getElementById('destination_sub_account');
const ledgerBadge = document.getElementById('ledgerSelectedBadge');

let ledgerTimer = null;

ledgerInput.addEventListener('input', function () {
    const q = (this.value || '').trim();

    // Reset selection if user edits text
    ledgerHidden.value = '';
    ledgerBadge.innerText = 'No ledger selected';

    if (ledgerTimer) clearTimeout(ledgerTimer);

    if (q.length < 2) {
        ledgerWrap.classList.add('d-none');
        ledgerList.innerHTML = '';
        return;
    }

    ledgerTimer = setTimeout(() => {
        fetch(`{{ route('fosa.transfers.ledger_search') }}?q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(data => {
                ledgerList.innerHTML = '';

                if (!data || !data.length) {
                    ledgerWrap.classList.remove('d-none');
                    ledgerList.innerHTML = `
                        <div class="list-group-item text-muted">
                            No ledger accounts found.
                        </div>`;
                    return;
                }

                ledgerWrap.classList.remove('d-none');

                data.forEach(acc => {
                    ledgerList.insertAdjacentHTML('beforeend', `
                        <button type="button"
                                class="list-group-item list-group-item-action"
                                data-id="${acc.id}"
                                data-text="${escapeHtml(acc.text)}">
                            ${escapeHtml(acc.text)}
                        </button>
                    `);
                });

                ledgerList.querySelectorAll('button').forEach(btn => {
                    btn.addEventListener('click', function () {
                        const id = this.getAttribute('data-id');
                        const text = this.getAttribute('data-text');

                        ledgerHidden.value = id;
                        ledgerInput.value = text;
                        ledgerBadge.innerText = `Selected: ${text}`;
                        ledgerWrap.classList.add('d-none');
                    });
                });
            })
            .catch(() => {
                ledgerWrap.classList.remove('d-none');
                ledgerList.innerHTML = `
                    <div class="list-group-item text-danger">
                        Failed to load ledger accounts. Please try again.
                    </div>`;
            });
    }, 250);
});

function clearLedgerSelection() {
    ledgerInput.value = '';
    ledgerHidden.value = '';
    ledgerBadge.innerText = 'No ledger selected';
    ledgerWrap.classList.add('d-none');
    ledgerList.innerHTML = '';
}

/* ------------------------
   Basic HTML escaping
------------------------- */
function escapeHtml(str) {
    return String(str)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}
</script>
@endsection