@extends('layouts.app')

@section('title', 'Member Financial Position')

@section('content')
<div class="container-fluid">

    {{-- ============================
         PAGE HEADER
    ============================ --}}
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">
                Member Financial Position (As At)
            </h5>
        </div>

        <div class="card-body">
            <form id="filterForm" class="form-inline" onsubmit="return false;">

                {{-- PERIOD INPUT (YYYYMM – LEGACY) --}}
                <div class="form-group mr-3">
                    <label for="period" class="mr-2"><strong>Period</strong></label>
                    @php
    // Defensive: enforce legacy scalar behaviour
    $periodValue = is_scalar($currentPeriod ?? null)
        ? $currentPeriod
        : (property_exists($currentPeriod, 'period')
            ? $currentPeriod->period
            : date('Ym'));
@endphp

<input
    type="text"
    id="period"
    class="form-control"
    value="{{ $periodValue }}"
    placeholder="YYYYMM"
    maxlength="6"
    style="width:120px"
/>

                </div>

                <button type="button" id="loadReport" class="btn btn-primary mr-2">
                    Load Report
                </button>

                <a href="#" id="exportBtn" class="btn btn-outline-success disabled">
                    Export CSV
                </a>

            </form>
        </div>
    </div>

    {{-- ============================
         REPORT TABLE
    ============================ --}}
    <div class="card">
        <div class="card-body">

            <div id="loading" class="text-center text-muted d-none">
                Loading report, please wait…
            </div>

            <div id="tableWrapper" class="table-responsive d-none">
                <table class="table table-bordered table-striped table-sm">
                    <thead id="reportHead"></thead>
                    <tbody id="reportBody"></tbody>
                </table>
            </div>

        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
(function () {

    const loadBtn   = document.getElementById('loadReport');
    const periodInp = document.getElementById('period');
    const loading   = document.getElementById('loading');
    const tableWrap = document.getElementById('tableWrapper');
    const thead     = document.getElementById('reportHead');
    const tbody     = document.getElementById('reportBody');
    const exportBtn = document.getElementById('exportBtn');

    loadBtn.addEventListener('click', function () {

        const period = periodInp.value.trim();

        if (!/^\d{6}$/.test(period)) {
            alert('Invalid period. Use YYYYMM format.');
            return;
        }

        loading.classList.remove('d-none');
        tableWrap.classList.add('d-none');
        thead.innerHTML = '';
        tbody.innerHTML = '';
        exportBtn.classList.add('disabled');

        fetch(`{{ route('reports.members.financial_position.data') }}?period=${period}`)
            .then(res => res.json())
            .then(resp => {

                loading.classList.add('d-none');

                if (!resp.data || resp.data.length === 0) {
                    alert('No data found for this period');
                    return;
                }

                const data = resp.data;

                /* =========================
                   TABLE HEADER
                ========================= */
                let head = `
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Sacco ID</th>
                        <th>National ID</th>
                        <th>Gender</th>
                        <th>Company</th>
                        <th>Department</th>
                        <th class="text-right">Savings</th>
                        <th class="text-right">FOSA</th>
                        <th class="text-right">Capital</th>
                `;

                data[0].loans.forEach(function (l) {
                    head += `
                        <th class="text-right">${l.loan_type_name} Taken</th>
                        <th class="text-right">${l.loan_type_name} Balance</th>
                    `;
                });

                head += '</tr>';
                thead.innerHTML = head;

                /* =========================
                   TABLE BODY
                ========================= */
                let rows = '';

                data.forEach(function (row, i) {

                    rows += `
                        <tr>
                            <td>${i + 1}</td>
                            <td>${row.member_name}</td>
                            <td>${row.member_sacco_id}</td>
                            <td>${row.member_national_id}</td>
                            <td>${row.member_gender}</td>
                            <td>${row.company}</td>
                            <td>${row.department}</td>
                            <td class="text-right">${Number(row.savings).toLocaleString()}</td>
                            <td class="text-right">${Number(row.fosa).toLocaleString()}</td>
                            <td class="text-right">${Number(row.capital).toLocaleString()}</td>
                    `;

                    row.loans.forEach(function (l) {
                        rows += `
                            <td class="text-right">${Number(l.taken).toLocaleString()}</td>
                            <td class="text-right">${Number(l.balance).toLocaleString()}</td>
                        `;
                    });

                    rows += '</tr>';
                });

                tbody.innerHTML = rows;
                tableWrap.classList.remove('d-none');

                /* =========================
                   EXPORT
                ========================= */
                exportBtn.href = `{{ route('reports.members.financial_position.export') }}?period=${period}`;
                exportBtn.classList.remove('disabled');
            })
            .catch(function () {
                loading.classList.add('d-none');
                alert('Failed to load report');
            });
    });

})();
</script>
@endsection
