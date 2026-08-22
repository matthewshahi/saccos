@extends('layouts.app')

@section('title', 'Member Financial Position')

@section('styles')
<style>
    /* ✅ Card overflow fix: o-hidden clips scrollbars */
    .mfp-card { overflow: visible !important; }

    /* ✅ Main table viewport (vertical + horizontal scroll) */
    .mfp-table-wrap{
        width: 100%;
        max-width: 100%;
        overflow-x: auto;   /* horizontal */
        overflow-y: auto;   /* vertical */
        max-height: 70vh;
        -webkit-overflow-scrolling: touch;

        /* keep gutter so the scrollbar area doesn’t “jump” */
        scrollbar-gutter: stable both-edges;
    }

    /* ✅ Force table to grow wide so horizontal scroll exists */
    .mfp-table{
        width: max-content;
        min-width: 100%;
    }

    .mfp-table th,
    .mfp-table td{
        white-space: nowrap;
        vertical-align: middle;
    }

    .mfp-table thead th{
        position: sticky;
        top: 0;
        z-index: 2;
        background: #fff;
    }

    .mfp-table tfoot th,
    .mfp-table tfoot td{
        position: sticky;
        bottom: 0;
        z-index: 2;
        background: #fff;
        border-top: 2px solid #dee2e6;
        font-weight: 700;
    }

    /* ✅ Dedicated horizontal scroller (always visible under the table) */
    .mfp-hscroll{
        width: 100%;
        max-width: 100%;
        overflow-x: auto;
        overflow-y: hidden;
        height: 18px;                 /* a bit easier to grab than 16px */
        margin-top: 10px;
        background: #fff;
        border: 1px solid #dee2e6;
        border-radius: 6px;

        scrollbar-gutter: stable both-edges;
    }

    .mfp-hscroll-inner{
        height: 1px; /* only to create scrollable width */
    }

    .mfp-report-meta{
        font-size: .875rem;
        line-height: 1.5;
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

            <div class="card mb-4 mfp-card">

                {{-- HEADER --}}
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h3 class="card-title mb-0">Member Financial Position (As At)</h3>

                    <a href="#" id="exportBtn" class="btn btn-sm btn-outline-success disabled" aria-disabled="true">
                        Export CSV
                    </a>
                </div>

                <div class="card-body">

                    {{-- FILTER BAR --}}
                    <form id="filterForm" class="row gx-3 gy-2 align-items-center" onsubmit="return false;">

                        <div class="col-md-2">
                            <label class="form-label mb-1 fw-semibold">Period (YYYYMM)</label>
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

                        <div class="col-md-5">
                            <label class="form-label mb-1 fw-semibold">Search</label>
                            <input
                                type="text"
                                id="pms_srch"
                                name="pms_srch"
                                class="form-control"
                                placeholder="Name, Sacco ID, National ID, Email, Phone, Company, Department, Position..."
                                autocomplete="off">
                        </div>

                        <div class="col-md-5 d-flex justify-content-end align-items-center">
                            <button type="button" id="loadReport" class="btn btn-primary">Load Report</button>
                            <button type="button" id="clearBtn" class="btn btn-outline-secondary ms-2">Clear</button>
                        </div>

                    </form>

                    <hr class="my-4">

                    {{-- REPORT AUDIT CONTEXT --}}
                    <div id="reportMeta" class="alert alert-light border mfp-report-meta d-none"></div>

                    {{-- STATES --}}
                    <div id="loading" class="text-center text-muted d-none">Loading report, please wait…</div>
                    <div id="emptyState" class="text-center text-muted d-none">No data found for the selected period.</div>

                    {{-- TABLE --}}
                    <div id="tableWrapper" class="mfp-table-wrap d-none">
                        <table class="table table-hover table-bordered text-center mfp-table mb-0">
                            <thead id="reportHead"></thead>
                            <tbody id="reportBody"></tbody>
                            <tfoot id="reportFoot"></tfoot>
                        </table>
                    </div>

                    {{-- ✅ ALWAYS-VISIBLE HORIZONTAL SCROLLER --}}
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
    const loadBtn    = document.getElementById('loadReport');
    const clearBtn   = document.getElementById('clearBtn');
    const periodInp  = document.getElementById('period');
    const searchInp  = document.getElementById('pms_srch');

    const loading    = document.getElementById('loading');
    const tableWrap  = document.getElementById('tableWrapper');
    const emptyState = document.getElementById('emptyState');
    const reportMeta = document.getElementById('reportMeta');

    const thead      = document.getElementById('reportHead');
    const tbody      = document.getElementById('reportBody');
    const tfoot      = document.getElementById('reportFoot');

    const exportBtn  = document.getElementById('exportBtn');

    const hScroll      = document.getElementById('hScroll');
    const hScrollInner = document.getElementById('hScrollInner');

    const PAGE_SIZE = 5000;

    let syncing = false;
    let ro = null;
    let activeAbortController = null;

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
        return x.toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function periodLabel(period) {
        if (!/^\d{6}$/.test(period)) return period;

        const year = Number(period.substring(0, 4));
        const month = Number(period.substring(4, 6));

        if (month < 1 || month > 12) return period;

        return new Intl.DateTimeFormat(undefined, {
            month: 'long',
            year: 'numeric'
        }).format(new Date(year, month - 1, 1));
    }

    function resetUI() {
        loading.classList.add('d-none');
        loading.textContent = 'Loading report, please wait…';

        tableWrap.classList.add('d-none');
        emptyState.classList.add('d-none');
        reportMeta.classList.add('d-none');
        reportMeta.innerHTML = '';

        thead.innerHTML = '';
        tbody.innerHTML = '';
        tfoot.innerHTML = '';

        exportBtn.classList.add('disabled');
        exportBtn.setAttribute('aria-disabled', 'true');
        exportBtn.href = '#';

        hScroll.classList.add('d-none');
        hScroll.scrollLeft = 0;
        tableWrap.scrollLeft = 0;
        hScrollInner.style.width = '0px';
    }

    function syncScroll(from, to) {
        if (syncing) return;
        syncing = true;
        to.scrollLeft = from.scrollLeft;
        requestAnimationFrame(() => { syncing = false; });
    }

    function setupHorizontalScroller() {
        const setWidth = () => {
            const w = Math.max(tableWrap.scrollWidth || 0, tableWrap.clientWidth || 0);
            hScrollInner.style.width = w + 'px';
        };

        setWidth();
        hScroll.classList.remove('d-none');

        if (!tableWrap.dataset.hsync) {
            tableWrap.dataset.hsync = '1';

            tableWrap.addEventListener('scroll', function () {
                syncScroll(tableWrap, hScroll);
            }, { passive: true });

            hScroll.addEventListener('scroll', function () {
                syncScroll(hScroll, tableWrap);
            }, { passive: true });

            window.addEventListener('resize', setWidth);

            if (window.ResizeObserver) {
                ro = new ResizeObserver(() => setWidth());
                ro.observe(tableWrap);
                const table = tableWrap.querySelector('table');
                if (table) ro.observe(table);
            }
        }

        requestAnimationFrame(setWidth);
        setTimeout(setWidth, 60);
        setTimeout(setWidth, 180);
    }

    function buildDataUrl(period, pms_srch, page) {
        const url = new URL(`{{ route('reports.members.financial_position.data') }}`, window.location.origin);
        url.searchParams.set('period', period);
        url.searchParams.set('page', String(page));
        url.searchParams.set('per_page', String(PAGE_SIZE));

        if (pms_srch.length) {
            url.searchParams.set('pms_srch', pms_srch);
        }

        return url;
    }

    async function fetchPage(period, pms_srch, page, signal) {
        const res = await fetch(buildDataUrl(period, pms_srch, page).toString(), {
            headers: { 'Accept': 'application/json' },
            signal
        });

        let resp = null;
        try {
            resp = await res.json();
        } catch (e) {
            throw new Error(`Server returned HTTP ${res.status}`);
        }

        if (!res.ok) {
            throw new Error(resp?.error || `Server returned HTTP ${res.status}`);
        }

        return resp;
    }

    function renderReport(data, period, pms_srch, meta) {
        if (!Array.isArray(data) || data.length === 0) {
            emptyState.classList.remove('d-none');
            return;
        }

        const loanCols = (data[0].loans || []).map(l => ({
            name: l.loan_type_name
        }));

        let head = `<tr>
            <th>#</th>
            <th class="text-start">Names</th>
            <th>Sacco ID</th>
            <th>National ID</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Gender</th>
            <th>Active</th>
            <th>Company</th>
            <th>Department</th>
            <th>Position</th>
            <th class="text-end">Savings</th>
            <th class="text-end">FOSA</th>
            <th class="text-end">Capital</th>
            <th class="text-end">Overall Exposure</th>
        `;

        loanCols.forEach(c => {
            head += `<th class="text-end">${escHtml(c.name)} Taken</th>`;
            head += `<th class="text-end">${escHtml(c.name)} Bal</th>`;
        });

        head += `</tr>`;
        thead.innerHTML = head;

        let rows = '';
        let totalSavings = 0;
        let totalFosa = 0;
        let totalCapital = 0;
        let totalExposure = 0;

        const totalLoanTaken = new Array(loanCols.length).fill(0);
        const totalLoanBal   = new Array(loanCols.length).fill(0);

        data.forEach((row, i) => {
            const savings  = Number(row.savings || 0);
            const fosa     = Number(row.fosa || 0);
            const capital  = Number(row.capital || 0);
            const exposure = Number(row.overall_exposure || 0);

            totalSavings += savings;
            totalFosa += fosa;
            totalCapital += capital;
            totalExposure += exposure;

            rows += `<tr>
                <td>${i + 1}</td>
                <td class="text-start">${escHtml(row.member_name)}</td>
                <td>${escHtml(row.member_sacco_id)}</td>
                <td>${escHtml(row.member_national_id)}</td>
                <td class="text-start">${escHtml(row.member_email)}</td>
                <td>${escHtml(row.member_phone_no)}</td>
                <td>${escHtml(row.member_gender)}</td>
                <td>${escHtml(row.member_active)}</td>
                <td class="text-start">${escHtml(row.company)}</td>
                <td class="text-start">${escHtml(row.department)}</td>
                <td class="text-start">${escHtml(row.position)}</td>
                <td class="text-end">${money(savings)}</td>
                <td class="text-end">${money(fosa)}</td>
                <td class="text-end">${money(capital)}</td>
                <td class="text-end fw-semibold">${money(exposure)}</td>
            `;

            loanCols.forEach((c, idx) => {
                const loan = (row.loans || [])[idx] || {};
                const taken = Number(loan.taken || 0);
                const bal   = Number(loan.balance || 0);

                totalLoanTaken[idx] += taken;
                totalLoanBal[idx] += bal;

                rows += `<td class="text-end">${money(taken)}</td>`;
                rows += `<td class="text-end">${money(bal)}</td>`;
            });

            rows += `</tr>`;
        });

        tbody.innerHTML = rows;

        let foot = `<tr>
            <th colspan="11" class="text-end">TOTALS</th>
            <th class="text-end">${money(totalSavings)}</th>
            <th class="text-end">${money(totalFosa)}</th>
            <th class="text-end">${money(totalCapital)}</th>
            <th class="text-end">${money(totalExposure)}</th>
        `;

        loanCols.forEach((c, idx) => {
            foot += `<th class="text-end">${money(totalLoanTaken[idx] || 0)}</th>`;
            foot += `<th class="text-end">${money(totalLoanBal[idx] || 0)}</th>`;
        });

        foot += `</tr>`;
        tfoot.innerHTML = foot;

        const searchLabel = pms_srch.length ? pms_srch : 'All members';
        reportMeta.innerHTML = `
            <strong>As At:</strong> ${escHtml(period)} (${escHtml(periodLabel(period))})
            &nbsp; | &nbsp;
            <strong>Generated:</strong> ${escHtml(meta?.generated_at || '')}
            &nbsp; | &nbsp;
            <strong>Generated By:</strong> ${escHtml(meta?.generated_by || '')}
            &nbsp; | &nbsp;
            <strong>Records:</strong> ${Number(data.length).toLocaleString()}
            &nbsp; | &nbsp;
            <strong>Search:</strong> ${escHtml(searchLabel)}
        `;
        reportMeta.classList.remove('d-none');

        tableWrap.classList.remove('d-none');
        setupHorizontalScroller();

        const exportUrl = new URL(`{{ route('reports.members.financial_position.export') }}`, window.location.origin);
        exportUrl.searchParams.set('period', period);
        if (pms_srch.length) exportUrl.searchParams.set('pms_srch', pms_srch);

        exportBtn.href = exportUrl.toString();
        exportBtn.classList.remove('disabled');
        exportBtn.setAttribute('aria-disabled', 'false');
    }

    clearBtn.addEventListener('click', function () {
        if (activeAbortController) {
            activeAbortController.abort();
            activeAbortController = null;
        }

        periodInp.value = '{{ $periodValue }}';
        searchInp.value = '';
        loadBtn.disabled = false;
        resetUI();
    });

    [periodInp, searchInp].forEach(el => {
        el.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                loadBtn.click();
            }
        });
    });

    loadBtn.addEventListener('click', async function () {
        const period = periodInp.value.trim();
        const pms_srch = searchInp.value.trim();

        if (!/^\d{6}$/.test(period)) {
            alert('Invalid period. Use YYYYMM format (e.g., 202601).');
            return;
        }

        if (activeAbortController) {
            activeAbortController.abort();
        }

        activeAbortController = new AbortController();
        const signal = activeAbortController.signal;

        resetUI();
        loadBtn.disabled = true;
        loading.classList.remove('d-none');

        try {
            const allData = [];
            let page = 1;
            let firstMeta = null;
            let expectedTotal = null;

            while (true) {
                const resp = await fetchPage(period, pms_srch, page, signal);

                if (!resp || !Array.isArray(resp.data)) {
                    throw new Error('Invalid report response');
                }

                if (!firstMeta) {
                    firstMeta = resp.meta || {};
                }

                expectedTotal = Number(resp.meta?.total || 0);
                allData.push(...resp.data);

                loading.textContent = expectedTotal > 0
                    ? `Loading report… ${allData.length.toLocaleString()} of ${expectedTotal.toLocaleString()} members loaded.`
                    : 'Loading report…';

                if (!resp.meta?.has_more) {
                    break;
                }

                page += 1;
            }

            loading.classList.add('d-none');

            if (allData.length === 0) {
                emptyState.classList.remove('d-none');
                return;
            }

            renderReport(allData, period, pms_srch, firstMeta || {});

        } catch (err) {
            loading.classList.add('d-none');

            if (err?.name === 'AbortError') {
                return;
            }

            console.error(err);
            alert(err?.message || 'Failed to load report');
        } finally {
            loadBtn.disabled = false;
            activeAbortController = null;
        }
    });
})();
</script>
@endsection