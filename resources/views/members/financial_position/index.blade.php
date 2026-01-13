@extends('layouts.app')

@section('title', 'Member Financial Position')

@section('content')
<div class="container-fluid">

    {{-- =========================
         PAGE HEADER
    ========================== --}}
    <div class="card mb-3">
        <div class="card-header">
            <h5 class="mb-0">
                Member Financial Position (As At)
            </h5>
        </div>

        <div class="card-body">
            <form id="filterForm" class="form-inline gap-3">

                {{-- Period selector --}}
                <div class="form-group mr-3">
                    <label for="period" class="mr-2">Accounting Period</label>
                    <select name="period" id="period" class="form-control" required>
                        <option value="">-- Select Period --</option>
                        @foreach($periods as $p)
                            <option value="{{ $p->period_end }}">
                                {{ $p->period_name }} ({{ $p->period_end }})
                            </option>
                        @endforeach
                    </select>
                </div>

                {{-- Load button --}}
                <button type="button" id="loadReport" class="btn btn-primary mr-2">
                    Load Report
                </button>

                {{-- Export --}}
                <a href="#" id="exportBtn" class="btn btn-outline-success disabled">
                    Export CSV
                </a>

            </form>
        </div>
    </div>

    {{-- =========================
         REPORT TABLE
    ========================== --}}
    <div class="card">
        <div class="card-body">

            <div id="loading" class="text-center text-muted d-none">
                Loading report…
            </div>

            <div class="table-responsive d-none" id="tableWrapper">
                <table class="table table-bordered table-striped table-sm" id="reportTable">
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
document.getElementById('loadReport').addEventListener('click', function () {

    const period = document.getElementById('period').value;
    if (!period) {
        alert('Please select an accounting period');
        return;
    }

    const loading   = document.getElementById('loading');
    const tableWrap = document.getElementById('tableWrapper');
    const thead     = document.getElementById('reportHead');
    const tbody     = document.getElementById('reportBody');
    const exportBtn = document.getElementById('exportBtn');

    loading.classList.remove('d-none');
    tableWrap.classList.add('d-none');
    thead.innerHTML = '';
    tbody.innerHTML = '';

    fetch(`{{ route('reports.members.financial_position.data') }}?period=${period}`)
        .then(res => res.json())
        .then(resp => {

            loading.classList.add('d-none');

            if (!resp.data || resp.data.length === 0) {
                alert('No data found for selected period');
                return;
            }

            const data = resp.data;

            /* ==========================
               BUILD TABLE HEADER
            ========================== */
            let headerRow = `
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

            // Loan type headers (dynamic)
            data[0].loans.forEach(loan => {
                headerRow += `
                    <th class="text-right">${loan.loan_type_name} Taken</th>
                    <th class="text-right">${loan.loan_type_name} Balance</th>
                `;
            });

            headerRow += '</tr>';
            thead.innerHTML = headerRow;

            /* ==========================
               BUILD TABLE BODY
            ========================== */
            let rows = '';
            data.forEach((row, idx) => {

                rows += `
                    <tr>
                        <td>${idx + 1}</td>
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

                row.loans.forEach(loan => {
                    rows += `
                        <td class="text-right">${Number(loan.taken).toLocaleString()}</td>
                        <td class="text-right">${Number(loan.balance).toLocaleString()}</td>
                    `;
                });

                rows += '</tr>';
            });

            tbody.innerHTML = rows;
            tableWrap.classList.remove('d-none');

            /* ==========================
               EXPORT LINK
            ========================== */
            exportBtn.href = `{{ route('reports.members.financial_position.export') }}?period=${period}`;
            exportBtn.classList.remove('disabled');
        })
        .catch(err => {
            loading.classList.add('d-none');
            console.error(err);
            alert('Failed to load report');
        });
});
</script>
@endsection
