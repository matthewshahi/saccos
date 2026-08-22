@extends('layouts.app')

@section('title', 'Member Financial Position')

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

{{--
    Keep these styles inside the rendered content intentionally.
    The current layouts.app does not render @section('styles'), so putting
    this CSS here guarantees that the report layout and scrolling controls
    are actually applied on desktop and mobile.
--}}
<style id="mfp-page-styles">
    #mfpReportPage,
    #mfpReportPage * {
        box-sizing: border-box;
    }

    #mfpReportPage {
        width: 100%;
        max-width: 100%;
        min-width: 0;
    }

    #mfpReportPage .mfp-card {
        width: 100%;
        max-width: 100%;
        overflow: hidden !important;
    }

    #mfpReportPage .mfp-card-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: .75rem;
    }

    #mfpReportPage .mfp-card-title {
        margin: 0;
        min-width: 0;
        font-size: 1.2rem;
        line-height: 1.3;
    }

    /* ---------------------------------------------------------
     * Filter form
     * --------------------------------------------------------- */
    #mfpReportPage .mfp-filter-grid {
        display: grid;
        grid-template-columns: minmax(150px, 210px) minmax(280px, 1fr) auto;
        align-items: end;
        gap: .85rem;
        width: 100%;
        margin: 0;
    }

    #mfpReportPage .mfp-field {
        min-width: 0;
    }

    #mfpReportPage .mfp-field label {
        display: block;
        margin: 0 0 .35rem 0;
        font-weight: 600;
        line-height: 1.25;
    }

    #mfpReportPage .mfp-field .form-control {
        width: 100%;
        min-width: 0;
        height: 38px;
    }

    #mfpReportPage .mfp-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: .5rem;
        white-space: nowrap;
    }

    #mfpReportPage .mfp-actions .btn {
        min-height: 38px;
    }

    #mfpReportPage .mfp-report-meta {
        margin-bottom: .8rem;
        font-size: .875rem;
        line-height: 1.55;
        overflow-wrap: anywhere;
    }

    /* ---------------------------------------------------------
     * Highly visible horizontal navigation
     * --------------------------------------------------------- */
    #mfpReportPage .mfp-scroll-panel {
        width: 100%;
        max-width: 100%;
        padding: .5rem .6rem;
        background: #f8f9fa;
        border: 1px solid #d8dde3;
        border-radius: .45rem;
    }

    #mfpReportPage .mfp-scroll-panel-top {
        margin-bottom: .55rem;
    }

    #mfpReportPage .mfp-scroll-panel-bottom {
        margin-top: .55rem;
    }

    #mfpReportPage .mfp-scroll-caption {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: .75rem;
        margin-bottom: .35rem;
        color: #555;
        font-size: .78rem;
        line-height: 1.25;
    }

    #mfpReportPage .mfp-scroll-caption strong {
        color: #333;
    }

    #mfpReportPage .mfp-scroll-controls {
        display: grid;
        grid-template-columns: 44px minmax(120px, 1fr) 44px;
        align-items: center;
        gap: .55rem;
        width: 100%;
    }

    #mfpReportPage .mfp-scroll-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 44px;
        min-width: 44px;
        height: 38px;
        padding: 0;
        font-size: 1.15rem;
        font-weight: 700;
        line-height: 1;
        border: 1px solid #c9ced4;
        border-radius: .4rem;
        background: #fff;
        color: #333;
        cursor: pointer;
        user-select: none;
        touch-action: manipulation;
    }

    #mfpReportPage .mfp-scroll-btn:hover,
    #mfpReportPage .mfp-scroll-btn:focus {
        border-color: #663399;
        color: #663399;
        outline: none;
        box-shadow: 0 0 0 .12rem rgba(102, 51, 153, .12);
    }

    #mfpReportPage .mfp-scroll-range {
        display: block;
        width: 100%;
        min-width: 0;
        height: 30px;
        margin: 0;
        cursor: ew-resize;
        accent-color: #663399;
        touch-action: manipulation;
    }

    #mfpReportPage .mfp-scroll-panel.is-disabled {
        opacity: .65;
    }

    #mfpReportPage .mfp-scroll-panel.is-disabled .mfp-scroll-btn,
    #mfpReportPage .mfp-scroll-panel.is-disabled .mfp-scroll-range {
        cursor: default;
    }

    /* ---------------------------------------------------------
     * Table viewport
     * --------------------------------------------------------- */
    #mfpReportPage .mfp-table-wrap {
        position: relative;
        width: 100%;
        max-width: 100%;
        min-width: 0;
        max-height: 68vh;
        overflow: auto;
        border: 1px solid #dee2e6;
        border-radius: .35rem;
        background: #fff;
        -webkit-overflow-scrolling: touch;
        overscroll-behavior: contain;
        scrollbar-gutter: stable both-edges;
        scrollbar-width: auto;
        scrollbar-color: #777 #eceff2;
    }

    /* Make the browser scrollbar easier to see/grab on desktop WebKit/Chromium. */
    #mfpReportPage .mfp-table-wrap::-webkit-scrollbar {
        width: 14px;
        height: 16px;
    }

    #mfpReportPage .mfp-table-wrap::-webkit-scrollbar-track {
        background: #eceff2;
        border-radius: 10px;
    }

    #mfpReportPage .mfp-table-wrap::-webkit-scrollbar-thumb {
        background: #777;
        border: 3px solid #eceff2;
        border-radius: 10px;
    }

    #mfpReportPage .mfp-table-wrap::-webkit-scrollbar-thumb:hover {
        background: #555;
    }

    #mfpReportPage .mfp-table {
        width: max-content;
        min-width: 100%;
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
        font-size: 13px;
    }

    #mfpReportPage .mfp-table th,
    #mfpReportPage .mfp-table td {
        padding: .55rem .65rem;
        white-space: nowrap;
        vertical-align: middle;
        background-clip: padding-box;
    }

    #mfpReportPage .mfp-table thead th {
        position: sticky;
        top: 0;
        z-index: 4;
        background: #fff;
        border-bottom: 2px solid #dfe3e7;
    }

    #mfpReportPage .mfp-table tfoot th,
    #mfpReportPage .mfp-table tfoot td {
        position: sticky;
        bottom: 0;
        z-index: 4;
        background: #fff;
        border-top: 2px solid #cfd4da;
        font-weight: 700;
    }

    /* Freeze row number + member name so users do not lose context horizontally. */
    #mfpReportPage .mfp-table thead th:nth-child(1),
    #mfpReportPage .mfp-table tbody td:nth-child(1) {
        position: sticky;
        left: 0;
        z-index: 3;
        width: 52px;
        min-width: 52px;
        max-width: 52px;
        background: #fff;
    }

    #mfpReportPage .mfp-table thead th:nth-child(2),
    #mfpReportPage .mfp-table tbody td:nth-child(2) {
        position: sticky;
        left: 52px;
        z-index: 3;
        min-width: 210px;
        background: #fff;
        box-shadow: 2px 0 0 rgba(0, 0, 0, .06);
    }

    #mfpReportPage .mfp-table thead th:nth-child(1),
    #mfpReportPage .mfp-table thead th:nth-child(2) {
        z-index: 7;
    }

    #mfpReportPage .mfp-mobile-scroll-hint {
        display: none;
    }

    @media (max-width: 991.98px) {
        #mfpReportPage .mfp-filter-grid {
            grid-template-columns: minmax(140px, 190px) minmax(220px, 1fr);
        }

        #mfpReportPage .mfp-actions {
            grid-column: 1 / -1;
            justify-content: flex-start;
        }
    }

    @media (max-width: 767.98px) {
        #mfpReportPage .card-body {
            padding-left: .75rem;
            padding-right: .75rem;
        }

        #mfpReportPage .mfp-card-header {
            align-items: flex-start;
        }

        #mfpReportPage .mfp-card-title {
            flex: 1 1 100%;
            font-size: 1.05rem;
        }

        #mfpReportPage #exportBtn {
            width: 100%;
        }

        #mfpReportPage .mfp-filter-grid {
            grid-template-columns: 1fr;
            gap: .7rem;
        }

        #mfpReportPage .mfp-actions {
            grid-column: auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            width: 100%;
        }

        #mfpReportPage .mfp-actions .btn {
            width: 100%;
            min-height: 42px;
        }

        #mfpReportPage .mfp-scroll-panel {
            padding: .55rem;
        }

        #mfpReportPage .mfp-scroll-caption {
            display: block;
            margin-bottom: .45rem;
        }

        #mfpReportPage .mfp-mobile-scroll-hint {
            display: inline;
        }

        #mfpReportPage .mfp-scroll-controls {
            grid-template-columns: 46px minmax(80px, 1fr) 46px;
            gap: .4rem;
        }

        #mfpReportPage .mfp-scroll-btn {
            width: 46px;
            min-width: 46px;
            height: 44px;
        }

        #mfpReportPage .mfp-scroll-range {
            height: 38px;
        }

        #mfpReportPage .mfp-table-wrap {
            max-height: 62vh;
        }

        #mfpReportPage .mfp-table {
            font-size: 12px;
        }

        #mfpReportPage .mfp-table th,
        #mfpReportPage .mfp-table td {
            padding: .5rem .55rem;
        }

        #mfpReportPage .mfp-table thead th:nth-child(2),
        #mfpReportPage .mfp-table tbody td:nth-child(2) {
            min-width: 170px;
        }
    }
</style>

<div class="container-fluid" id="mfpReportPage">
    <div class="row">
        <div class="col-12">
            <div class="card mb-4 mfp-card">

                <div class="card-header mfp-card-header">
                    <h3 class="card-title mfp-card-title">Member Financial Position (As At)</h3>

                    <a href="#" id="exportBtn" class="btn btn-sm btn-outline-success disabled" aria-disabled="true">
                        Export CSV
                    </a>
                </div>

                <div class="card-body">

                    <form id="filterForm" class="mfp-filter-grid" onsubmit="return false;">
                        <div class="mfp-field">
                            <label for="period">Period (YYYYMM)</label>
                            <input
                                type="text"
                                id="period"
                                name="period"
                                class="form-control"
                                value="{{ $periodValue }}"
                                placeholder="e.g. 202601"
                                maxlength="6"
                                inputmode="numeric"
                                autocomplete="off">
                        </div>

                        <div class="mfp-field">
                            <label for="pms_srch">Search members</label>
                            <input
                                type="search"
                                id="pms_srch"
                                name="pms_srch"
                                class="form-control"
                                placeholder="Name, Sacco ID, National ID, email, phone, company, department or position"
                                autocomplete="off">
                        </div>

                        <div class="mfp-actions">
                            <button type="button" id="loadReport" class="btn btn-primary">Load Report</button>
                            <button type="button" id="clearBtn" class="btn btn-outline-secondary">Clear</button>
                        </div>
                    </form>

                    <hr class="my-4">

                    <div id="reportMeta" class="alert alert-light border mfp-report-meta d-none"></div>

                    <div id="loading" class="text-center text-muted d-none">Loading report, please wait…</div>
                    <div id="emptyState" class="text-center text-muted d-none">No data found for the selected period.</div>

                    {{-- Visible horizontal navigator above the table. --}}
                    <div id="topScrollPanel" class="mfp-scroll-panel mfp-scroll-panel-top d-none" aria-label="Horizontal table navigation">
                        <div class="mfp-scroll-caption">
                            <span><strong>Horizontal navigation</strong> — drag the bar or use the arrows.</span>
                            <span class="mfp-mobile-scroll-hint">You can also swipe the table left/right.</span>
                        </div>
                        <div class="mfp-scroll-controls">
                            <button type="button" class="mfp-scroll-btn" data-scroll-dir="-1" aria-label="Scroll table left">&#8592;</button>
                            <input id="topScrollRange" class="mfp-scroll-range" type="range" min="0" max="0" value="0" step="1" aria-label="Horizontal table position">
                            <button type="button" class="mfp-scroll-btn" data-scroll-dir="1" aria-label="Scroll table right">&#8594;</button>
                        </div>
                    </div>

                    <div id="tableWrapper" class="mfp-table-wrap d-none" tabindex="0" aria-label="Member Financial Position table. Scroll horizontally and vertically to view all columns and records.">
                        <table class="table table-hover table-bordered text-center mfp-table mb-0">
                            <thead id="reportHead"></thead>
                            <tbody id="reportBody"></tbody>
                            <tfoot id="reportFoot"></tfoot>
                        </table>
                    </div>

                    {{-- Same navigator below the table so it is always easy to reach. --}}
                    <div id="bottomScrollPanel" class="mfp-scroll-panel mfp-scroll-panel-bottom d-none" aria-label="Horizontal table navigation">
                        <div class="mfp-scroll-caption">
                            <span><strong>Horizontal navigation</strong> — drag the bar or use the arrows.</span>
                            <span class="mfp-mobile-scroll-hint">You can also swipe the table left/right.</span>
                        </div>
                        <div class="mfp-scroll-controls">
                            <button type="button" class="mfp-scroll-btn" data-scroll-dir="-1" aria-label="Scroll table left">&#8592;</button>
                            <input id="bottomScrollRange" class="mfp-scroll-range" type="range" min="0" max="0" value="0" step="1" aria-label="Horizontal table position">
                            <button type="button" class="mfp-scroll-btn" data-scroll-dir="1" aria-label="Scroll table right">&#8594;</button>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

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

    const topScrollPanel    = document.getElementById('topScrollPanel');
    const bottomScrollPanel = document.getElementById('bottomScrollPanel');
    const topScrollRange    = document.getElementById('topScrollRange');
    const bottomScrollRange = document.getElementById('bottomScrollRange');
    const scrollRanges      = [topScrollRange, bottomScrollRange];
    const scrollPanels      = [topScrollPanel, bottomScrollPanel];
    const scrollButtons     = Array.from(document.querySelectorAll('.mfp-scroll-btn'));

    const PAGE_SIZE = 5000;

    let scrollUiBound = false;
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

    function resetHorizontalNavigation() {
        scrollPanels.forEach(panel => {
            panel.classList.add('d-none');
            panel.classList.remove('is-disabled');
        });

        scrollRanges.forEach(range => {
            range.min = '0';
            range.max = '0';
            range.value = '0';
            range.disabled = true;
        });

        tableWrap.scrollLeft = 0;
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

        resetHorizontalNavigation();
    }

    function maxHorizontalScroll() {
        return Math.max(0, Math.round(tableWrap.scrollWidth - tableWrap.clientWidth));
    }

    function syncRangesFromTable() {
        const max = maxHorizontalScroll();
        const current = Math.max(0, Math.min(max, Math.round(tableWrap.scrollLeft)));

        scrollRanges.forEach(range => {
            range.max = String(max);
            range.value = String(current);
            range.disabled = max <= 0;
        });

        scrollPanels.forEach(panel => {
            panel.classList.toggle('is-disabled', max <= 0);
        });
    }

    function refreshHorizontalNavigation() {
        scrollPanels.forEach(panel => panel.classList.remove('d-none'));
        syncRangesFromTable();
    }

    function setupHorizontalNavigation() {
        refreshHorizontalNavigation();

        if (!scrollUiBound) {
            scrollUiBound = true;

            tableWrap.addEventListener('scroll', syncRangesFromTable, { passive: true });

            scrollRanges.forEach(range => {
                range.addEventListener('input', function () {
                    tableWrap.scrollLeft = Number(range.value || 0);
                    syncRangesFromTable();
                });
            });

            scrollButtons.forEach(button => {
                button.addEventListener('click', function () {
                    const direction = Number(button.dataset.scrollDir || 0);
                    const max = maxHorizontalScroll();

                    if (!direction || max <= 0) return;

                    const amount = Math.max(260, Math.round(tableWrap.clientWidth * 0.72));

                    tableWrap.scrollBy({
                        left: direction * amount,
                        behavior: 'smooth'
                    });
                });
            });

            window.addEventListener('resize', refreshHorizontalNavigation);

            if (window.ResizeObserver) {
                ro = new ResizeObserver(refreshHorizontalNavigation);
                ro.observe(tableWrap);

                const table = tableWrap.querySelector('table');
                if (table) ro.observe(table);
            }
        }

        requestAnimationFrame(refreshHorizontalNavigation);
        setTimeout(refreshHorizontalNavigation, 60);
        setTimeout(refreshHorizontalNavigation, 180);
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

        const otherSavingsCols = Array.isArray(meta?.other_savings_types)
            ? meta.other_savings_types
            : (data[0].other_savings || []).map(item => ({
                type_id: item.type_id,
                type_name: item.type_name,
                type_prefix: item.type_prefix
            }));

        const showGeneralOtherSavings = data.some(row =>
            Math.abs(Number(row.general_other_savings || 0)) > 0.000001
        );

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
            <th class="text-end">Capital</th>
            <th class="text-end">Savings</th>
            <th class="text-end">Special Savings</th>
        `;

        otherSavingsCols.forEach(c => {
            head += `<th class="text-end">Other Savings - ${escHtml(c.type_name)}</th>`;
        });

        if (showGeneralOtherSavings) {
            head += `<th class="text-end">General Other Savings</th>`;
        }

        head += `<th class="text-end">Overall Loan Exposure</th>`;

        loanCols.forEach(c => {
            head += `<th class="text-end">${escHtml(c.name)} Taken</th>`;
            head += `<th class="text-end">${escHtml(c.name)} Bal</th>`;
        });

        head += `</tr>`;
        thead.innerHTML = head;

        let rows = '';
        let totalCapital = 0;
        let totalSavings = 0;
        let totalSpecialSavings = 0;
        let totalGeneralOtherSavings = 0;
        let totalExposure = 0;

        const totalOtherSavings = new Array(otherSavingsCols.length).fill(0);
        const totalLoanTaken = new Array(loanCols.length).fill(0);
        const totalLoanBal   = new Array(loanCols.length).fill(0);

        data.forEach((row, i) => {
            const capital        = Number(row.capital || 0);
            const savings        = Number(row.savings || 0);
            const specialSavings = Number(row.special_savings || 0);
            const generalOtherSavings = Number(row.general_other_savings || 0);
            const exposure       = Number(row.overall_exposure || 0);

            totalCapital += capital;
            totalSavings += savings;
            totalSpecialSavings += specialSavings;
            totalGeneralOtherSavings += generalOtherSavings;
            totalExposure += exposure;

            const rowOtherSavings = new Map(
                (row.other_savings || []).map(item => [Number(item.type_id), Number(item.amount || 0)])
            );

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
                <td class="text-end">${money(capital)}</td>
                <td class="text-end">${money(savings)}</td>
                <td class="text-end">${money(specialSavings)}</td>
            `;

            otherSavingsCols.forEach((c, idx) => {
                const amount = Number(rowOtherSavings.get(Number(c.type_id)) || 0);
                totalOtherSavings[idx] += amount;
                rows += `<td class="text-end">${money(amount)}</td>`;
            });

            if (showGeneralOtherSavings) {
                rows += `<td class="text-end">${money(generalOtherSavings)}</td>`;
            }

            rows += `<td class="text-end fw-semibold">${money(exposure)}</td>`;

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
            <th class="text-end">${money(totalCapital)}</th>
            <th class="text-end">${money(totalSavings)}</th>
            <th class="text-end">${money(totalSpecialSavings)}</th>
        `;

        otherSavingsCols.forEach((c, idx) => {
            foot += `<th class="text-end">${money(totalOtherSavings[idx] || 0)}</th>`;
        });

        if (showGeneralOtherSavings) {
            foot += `<th class="text-end">${money(totalGeneralOtherSavings)}</th>`;
        }

        foot += `<th class="text-end">${money(totalExposure)}</th>`;

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
        setupHorizontalNavigation();

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
