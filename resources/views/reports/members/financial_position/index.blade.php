@extends('layouts.app')

@section('title', 'Member Financial Position')

@section('styles')
<style>
    .mfp-table-wrap {
        overflow-x: auto;
        overflow-y: auto;
        max-height: 70vh;
        -webkit-overflow-scrolling: touch;
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
        background: #ffffff;
    }

    .mfp-table tfoot th,
    .mfp-table tfoot td {
        position: sticky;
        bottom: 0;
        z-index: 2;
        background: #ffffff;
        border-top: 2px solid #dee2e6;
        font-weight: 700;
    }
</style>
@endsection

@section('content')

<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">

            <div class="card o-hidden mb-4">

                {{-- HEADER --}}
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title m-0 w-50">
                        Member Financial Position (As At)
                    </h3>

                    <div class="dropdown dropleft text-end w-50">
                        <button
                            class="btn bg-gray-100"
                            type="button"
                            data-bs-toggle="dropdown">
                            <i class="nav-icon i-Gear-2"></i>
                        </button>

                        <div class="dropdown-menu">
                            <a
                                href="#"
                                id="exportBtn"
                                class="dropdown-item disabled"
                                aria-disabled="true">
                                Export CSV
                            </a>
                        </div>
                    </div>
                </div>

                {{-- BODY --}}
                <div class="card-body">

                    {{-- FILTER FORM --}}
                    <form class="row g-3 align-items-end" onsubmit="return false;">

                        <div class="col-md-2">
                            <label class="form-label"><strong>Period (YYYYMM)</strong></label>
                            <input
                                type="text"
                                class="form-control"
                                id="period"
                                value="{{ isset($currentPeriod) ? $currentPeriod : date('Ym') }}"
                                maxlength="6"
                                placeholder="YYYYMM">
                            <small class="text-muted">Example: 202601</small>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label"><strong>Search</strong></label>
                            <input
                                type="text"
                                class="form-control"
                                id="pms_srch"
                                placeholder="Name, Sacco ID, National ID, Email, Company, Department">
                        </div>

                        <div class="col-md-6 text-end">
                            <button
                                type="button"
                                id="loadReport"
                                class="btn btn-primary">
                                Load Report
                            </button>

                            <button
                                type="button"
                                id="clearBtn"
                                class="btn btn-outline-secondary ms-2">
                                Clear
                            </button>
                        </div>
                    </form>

                    <hr class="my-3">

                    {{-- STATES --}}
                    <div id="loading" class="text-center text-muted d-none">
                        Loading report, please wait…
                    </div>

                    <div id="emptyState" class="text-center text-muted d-none">
                        No data found for the selected period.
                    </div>

                    {{-- TABLE --}}
                    <div id="tableWrapper" class="mfp-table-wrap d-none">
                        <table class="table table-bordered table-hover text-center mfp-table mb-0">
                            <thead id="reportHead"></thead>
                            <tbody id="reportBody"></tbody>
                            <tfoot id="reportFoot"></tfoot>
                        </table>
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
    const empty     = document.getElementById('emptyState');

    const thead = document.getElementById('reportHead');
    const tbody = document.getElementById('reportBody');
    const tfoot = document.getElementById('reportFoot');

    const exportBtn = document.getElementById('exportBtn');

    function resetUI() {
        loading.classList.add('d-none');
        tableWrap.classList.add('d-none');
        empty.classList.add('d-none');
        thead.innerHTML = '';
        tbody.innerHTML = '';
        tfoot.innerHTML = '';
        exportBtn.classList.add('disabled');
        exportBtn.href = '#';
    }

    function money(n) {
        return Number(n || 0).toLocaleString(undefined, {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    clearBtn.onclick = () => {
        periodInp.value = '{{ isset($currentPeriod) ? $currentPeriod : date('Ym') }}';
        searchInp.value = '';
        resetUI();
    };

    loadBtn.onclick = () => {

        const period = periodInp.value.trim();
        const search = searchInp.value.trim();

        if (!/^\d{6}$/.test(period)) {
            alert('Invalid period. Use YYYYMM.');
            return;
        }

        resetUI();
        loading.classList.remove('d-none');

        const url = new URL(`{{ route('reports.members.financial_position.data') }}`, window.location.origin);
        url.searchParams.set('period', period);
        if (search) url.searchParams.set('pms_srch', search);

        fetch(url)
            .then(r => r.json())
            .then(resp => {

                loading.classList.add('d-none');

                if (!resp.data || !resp.data.length) {
                    empty.classList.remove('d-none');
                    return;
                }

                const data = resp.data;
                const loans = data[0].loans || [];

                let head = `
                    <tr>
                        <th>#</th>
                        <th class="text-start">Names</th>
                        <th>Sacco ID</th>
                        <th>National ID</th>
                        <th>Gender</th>
                        <th class="text-end">Savings</th>
                        <th class="text-end">FOSA</th>
                        <th class="text-end">Capital</th>
                `;
                loans.forEach(l => {
                    head += `<th class="text-end">${l.loan_type_name} Taken</th>`;
                    head += `<th class="text-end">${l.loan_type_name} Bal</th>`;
                });
                head += `</tr>`;
                thead.innerHTML = head;

                let totalSavings = 0, totalFosa = 0, totalCapital = 0;
                const loanTaken = new Array(loans.length).fill(0);
                const loanBal   = new Array(loans.length).fill(0);

                tbody.innerHTML = data.map((r, i) => {

                    totalSavings += r.savings;
                    totalFosa    += r.fosa;
                    totalCapital += r.capital;

                    let row = `
                        <tr>
                            <td>${i + 1}</td>
                            <td class="text-start">${r.member_name}</td>
                            <td>${r.member_sacco_id}</td>
                            <td>${r.member_national_id}</td>
                            <td>${r.member_gender}</td>
                            <td class="text-end">${money(r.savings)}</td>
                            <td class="text-end">${money(r.fosa)}</td>
                            <td class="text-end">${money(r.capital)}</td>
                    `;

                    r.loans.forEach((l, x) => {
                        loanTaken[x] += l.taken;
                        loanBal[x]   += l.balance;
                        row += `<td class="text-end">${money(l.taken)}</td>`;
                        row += `<td class="text-end">${money(l.balance)}</td>`;
                    });

                    return row + `</tr>`;
                }).join('');

                let foot = `
                    <tr>
                        <th colspan="5" class="text-end">TOTALS</th>
                        <th class="text-end">${money(totalSavings)}</th>
                        <th class="text-end">${money(totalFosa)}</th>
                        <th class="text-end">${money(totalCapital)}</th>
                `;
                loanTaken.forEach((_, i) => {
                    foot += `<th class="text-end">${money(loanTaken[i])}</th>`;
                    foot += `<th class="text-end">${money(loanBal[i])}</th>`;
                });
                foot += `</tr>`;
                tfoot.innerHTML = foot;

                tableWrap.classList.remove('d-none');

                const exportUrl = new URL(`{{ route('reports.members.financial_position.export') }}`, window.location.origin);
                exportUrl.searchParams.set('period', period);
                if (search) exportUrl.searchParams.set('pms_srch', search);

                exportBtn.href = exportUrl;
                exportBtn.classList.remove('disabled');
            })
            .catch(() => {
                loading.classList.add('d-none');
                alert('Failed to load report');
            });
    };

})();
</script>
@endsection
