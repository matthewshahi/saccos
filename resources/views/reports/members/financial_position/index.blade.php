@extends('layouts.app')

@section('title', 'Member Financial Position')

@section('styles')
<style>
    /* ✅ Card overflow fix: o-hidden clips scrollbars */
    .mfp-card { overflow: visible !important; }

    /* ✅ Main table viewport (vertical + horizontal scroll) */
    .mfp-table-wrap {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;   /* horizontal */
        overflow-y: auto;   /* vertical */
        max-height: 70vh;
        -webkit-overflow-scrolling: touch;
    }

    /* ✅ Force table to grow wide so horizontal scroll exists */
    .mfp-table {
        width: max-content;
        min-width: 100%;
    }

    .mfp-table th,
    .mfp-table td {
        white-space: nowrap;
        vertical-align: middle;
    }

    .mfp-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #fff;
    }

    .mfp-table tfoot th,
    .mfp-table tfoot td {
        position: sticky;
        bottom: 0;
        z-index: 2;
        background: #fff;
        border-top: 2px solid #dee2e6;
        font-weight: 700;
    }

    /* ✅ Dedicated horizontal scroller (always visible under the table) */
    .mfp-hscroll {
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        height: 16px;                /* scrollbar track height */
        margin-top: 8px;
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 6px;
    }
    .mfp-hscroll-inner {
        height: 1px;                 /* just to create scrollable width */
    }
</style>
@endsection

@section('content')
@php
    $periodValue = $currentPeriod ?? date('Ym');

    if (is_object($periodValue)) {
        $periodValue = $periodValue->period
            ?? $periodValue->value
            ?? $periodValue->currentPeriod
            ?? date('Ym');
    }

    $periodValue = (string) $periodValue;
@endphp

<div class="container-fluid">

    <div class="row">
        <div class="col-12">

            {{-- ✅ removed "o-hidden" and added "mfp-card" --}}
            <div class="card mb-4 mfp-card">

                {{-- HEADER --}}
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h3 class="card-title mb-0">
                        Member Financial Position (As At)
                    </h3>

                    <a
                        href="#"
                        id="exportBtn"
                        class="btn btn-sm btn-outline-success disabled"
                        aria-disabled="true">
                        Export CSV
                    </a>
                </div>

                <div class="card-body">

                    {{-- FILTER BAR --}}
                    <form id="filterForm" class="row gx-3 gy-2 align-items-center" onsubmit="return false;">

                        <!-- PERIOD -->
                        <div class="col-md-2">
                            <label class="form-label mb-1 fw-semibold">
                                Period (YYYYMM)
                            </label>

                            <input
                                type="text"
                                id="period"
                                name="period"
                                class="form-control"
                                value="{{ $periodValue }}"
                                placeholder="YYYYMM"
                                maxlength="6"
                                autocomplete="off">

                            <small class="text-muted">Example: 202601</small>
                        </div>

                        <!-- SEARCH -->
                        <div class="col-md-5">
                            <label class="form-label mb-1 fw-semibold">
                                Search
                            </label>

                            <input
                                type="text"
                                id="pms_srch"
                                name="pms_srch"
                                class="form-control"
                                placeholder="Name, Sacco ID, National ID, Email, Company, Department..."
                                autocomplete="off">
                        </div>

                        <!-- ACTIONS -->
                        <div class="col-md-5 d-flex justify-content-end align-items-center">
                            <button type="button" id="loadReport" class="btn btn-primary">
                                Load Report
                            </button>

                            <button type="button" id="clearBtn" class="btn btn-outline-secondary ms-2">
                                Clear
                            </button>
                        </div>

                    </form>

                    <hr class="my-4">

                    {{-- STATES --}}
                    <div id="loading" class="text-center text-muted d-none">
                        Loading report, please wait…
                    </div>

                    <div id="emptyState" class="text-center text-muted d-none">
                        No data found for the selected period.
                    </div>

                    {{-- TABLE --}}
                    <div id="tableWrapper" class="mfp-table-wrap d-none">
                        <table class="table table-hover table-bordered text-center mfp-table mb-0">
                            <thead id="reportHead"></thead>
                            <tbody id="reportBody"></tbody>
                            <tfoot id="reportFoot"></tfoot>
                        </table>
                    </div>

                    {{-- ✅ ALWAYS-VISIBLE HORIZONTAL SCROLLER (synced with tableWrapper) --}}
                    <div id="hScroll" class="mfp-hscroll d-none" aria-label="Horizontal scroller">
                        <div id="hScrollInner" class="mfp-hscroll-inner"></div>
                    </div>

                </div>
            </div>

        </div>
    </div>

</div>
@endsection


@section('scripts')
<script>
(function () {
    const loadBtn   = document.getElementById('loadReport');
    const clearBtn  = document.getElementById('clearBtn');
    const periodInp = document.getElementById('period');
    const searchInp = document.getElementById('pms_srch');

    const loading   = document.getElementById('loading');
    const tableWrap = document.getElementById('tableWrapper');
    const emptyState= document.getElementById('emptyState');

    const thead     = document.getElementById('reportHead');
    const tbody     = document.getElementById('reportBody');
    const tfoot     = document.getElementById('reportFoot');

    const exportBtn = document.getElementById('exportBtn');

    // ✅ horizontal scroller elements
    const hScroll      = document.getElementById('hScroll');
    const hScrollInner = document.getElementById('hScrollInner');

    function escHtml(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function money(n) {
        const x = Number(n || 0);
        return x.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function resetUI() {
        loading.classList.add('d-none');
        tableWrap.classList.add('d-none');
        emptyState.classList.add('d-none');

        thead.innerHTML = '';
        tbody.innerHTML = '';
        tfoot.innerHTML = '';

        exportBtn.classList.add('disabled');
        exportBtn.setAttribute('aria-disabled', 'true');
        exportBtn.href = '#';

        // hide scroller
        hScroll.classList.add('d-none');
        hScroll.scrollLeft = 0;
        tableWrap.scrollLeft = 0;
        hScrollInner.style.width = '0px';
    }

    function setupHorizontalScroller() {
        const table = tableWrap.querySelector('table');
        if (!table) return;

        // set scroller width to table scrollWidth (so scrollbar exists)
        const setWidth = () => {
            // scrollWidth can change after DOM paint; read from table itself
            const w = table.scrollWidth || 0;
            hScrollInner.style.width = w + 'px';
        };

        setWidth();
        hScroll.classList.remove('d-none');

        // bind once
        if (!tableWrap.dataset.hsync) {
            tableWrap.dataset.hsync = '1';

            // when table scrolls, scroller follows
            tableWrap.addEventListener('scroll', function () {
                hScroll.scrollLeft = tableWrap.scrollLeft;
            }, { passive: true });

            // when scroller scrolls, table follows
            hScroll.addEventListener('scroll', function () {
                tableWrap.scrollLeft = hScroll.scrollLeft;
            }, { passive: true });

            // recompute on resize
            window.addEventListener('resize', function () {
                setWidth();
            });
        }

        // also recompute after a tick (helps when fonts/layout settle)
        setTimeout(setWidth, 0);
        setTimeout(setWidth, 80);
    }

    clearBtn.addEventListener('click', function () {
        periodInp.value = '{{ $periodValue }}';
        searchInp.value = '';
        resetUI();
    });

    // Optional: Enter key triggers load
    [periodInp, searchInp].forEach(el => {
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                loadBtn.click();
            }
        });
    });

    loadBtn.addEventListener('click', function () {
        const period = periodInp.value.trim();
        const pms_srch = searchInp.value.trim();

        if (!/^\d{6}$/.test(period)) {
            alert('Invalid period. Use YYYYMM format (e.g., 202601).');
            return;
        }

        resetUI();
        loading.classList.remove('d-none');

        const url = new URL(`{{ route('reports.members.financial_position.data') }}`, window.location.origin);
        url.searchParams.set('period', period);
        if (pms_srch.length) url.searchParams.set('pms_srch', pms_srch);

        fetch(url.toString(), { headers: { 'Accept': 'application/json' } })
            .then(res => res.json())
            .then(resp => {
                loading.classList.add('d-none');

                if (!resp || !Array.isArray(resp.data) || resp.data.length === 0) {
                    emptyState.classList.remove('d-none');
                    return;
                }

                const data = resp.data;
                const loanCols = (data[0].loans || []).map(l => ({
                    name: l.loan_type_name
                }));

                // =========================
                // TABLE HEADER
                // =========================
                let head = `<tr>
                    <th>#</th>
                    <th class="text-start">Names</th>
                    <th>Sacco ID</th>
                    <th>National ID</th>
                    <th>Gender</th>
                    <th>Active</th>
                    <th class="text-end">Savings</th>
                    <th class="text-end">FOSA</th>
                    <th class="text-end">CAPITAL</th>
                `;

                loanCols.forEach(c => {
                    head += `<th class="text-end">${escHtml(c.name)} Taken</th>`;
                    head += `<th class="text-end">${escHtml(c.name)} Bal</th>`;
                });

                head += `</tr>`;
                thead.innerHTML = head;

                // =========================
                // TABLE BODY + TOTALS
                // =========================
                let rows = '';

                let totalSavings = 0, totalFosa = 0, totalCapital = 0;
                const totalLoanTaken = new Array(loanCols.length).fill(0);
                const totalLoanBal   = new Array(loanCols.length).fill(0);

                data.forEach((row, i) => {
                    const savings = Number(row.savings || 0);
                    const fosa    = Number(row.fosa || 0);
                    const capital = Number(row.capital || 0);

                    totalSavings += savings;
                    totalFosa    += fosa;
                    totalCapital += capital;

                    rows += `<tr>
                        <td>${i + 1}</td>
                        <td class="text-start">${escHtml(row.member_name)}</td>
                        <td>${escHtml(row.member_sacco_id)}</td>
                        <td>${escHtml(row.member_national_id)}</td>
                        <td>${escHtml(row.member_gender)}</td>
                        <td>${escHtml(row.member_active)}</td>
                        <td class="text-end">${money(savings)}</td>
                        <td class="text-end">${money(fosa)}</td>
                        <td class="text-end">${money(capital)}</td>
                    `;

                    (row.loans || []).forEach((l, idx) => {
                        const taken = Number(l.taken || 0);
                        const bal   = Number(l.balance || 0);

                        if (typeof totalLoanTaken[idx] !== 'undefined') totalLoanTaken[idx] += taken;
                        if (typeof totalLoanBal[idx] !== 'undefined')   totalLoanBal[idx]   += bal;

                        rows += `<td class="text-end">${money(taken)}</td>`;
                        rows += `<td class="text-end">${money(bal)}</td>`;
                    });

                    rows += `</tr>`;
                });

                tbody.innerHTML = rows;

                // =========================
                // TOTALS FOOTER
                // =========================
                let foot = `<tr>
                    <th colspan="6" class="text-end">TOTALS</th>
                    <th class="text-end">${money(totalSavings)}</th>
                    <th class="text-end">${money(totalFosa)}</th>
                    <th class="text-end">${money(totalCapital)}</th>
                `;

                loanCols.forEach((c, idx) => {
                    foot += `<th class="text-end">${money(totalLoanTaken[idx] || 0)}</th>`;
                    foot += `<th class="text-end">${money(totalLoanBal[idx] || 0)}</th>`;
                });

                foot += `</tr>`;
                tfoot.innerHTML = foot;

                tableWrap.classList.remove('d-none');

                // ✅ show and sync the always-visible horizontal scroller
                setupHorizontalScroller();

                // =========================
                // EXPORT LINK
                // =========================
                const exportUrl = new URL(`{{ route('reports.members.financial_position.export') }}`, window.location.origin);
                exportUrl.searchParams.set('period', period);
                if (pms_srch.length) exportUrl.searchParams.set('pms_srch', pms_srch);

                exportBtn.href = exportUrl.toString();
                exportBtn.classList.remove('disabled');
                exportBtn.setAttribute('aria-disabled', 'false');
            })
            .catch(err => {
                loading.classList.add('d-none');
                console.error(err);
                alert('Failed to load report');
            });
    });
})();
</script>
@endsection
