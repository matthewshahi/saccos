@extends('layouts.app')

@section('title', 'Member Financial Position')

@section('content')
<div class="container-fluid">

<div class="card mb-3">
    <div class="card-header">
        <strong>Loan / Savings / FOSA / Capital Balances</strong>
    </div>

    <div class="card-body">
        <div class="form-inline">
            <label class="mr-2"><strong>Period</strong></label>
            <input type="text"
                   id="period"
                   class="form-control mr-2"
                   value="{{ $currentPeriod }}"
                   maxlength="6"
                   style="width:120px">

            <button id="loadReport" class="btn btn-primary mr-2">
                Load
            </button>

            <a id="exportBtn" class="btn btn-success disabled" href="#">
                Export CSV
            </a>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-body">

        <div id="loading" class="text-muted d-none">
            Loading…
        </div>

        <div id="tableWrap" class="table-responsive d-none">
            <table class="table table-bordered table-sm">
                <thead id="thead"></thead>
                <tbody id="tbody"></tbody>
            </table>
        </div>

    </div>
</div>

</div>
@endsection

@section('scripts')
<script>
document.getElementById('loadReport').onclick = function () {

    const period = document.getElementById('period').value.trim();

    if (!/^\d{6}$/.test(period)) {
        alert('Use YYYYMM');
        return;
    }

    document.getElementById('loading').classList.remove('d-none');
    document.getElementById('tableWrap').classList.add('d-none');

    fetch(`{{ route('reports.members.financial_position.data') }}?period=${period}`)
        .then(r => r.json())
        .then(resp => {

            document.getElementById('loading').classList.add('d-none');

            const data = resp.data;
            if (!data || data.length === 0) {
                alert('No data');
                return;
            }

            let head = `<tr>
                <th>#</th>
                <th>Name</th>
                <th>Sacco ID</th>
                <th>National ID</th>
                <th>Gender</th>
                <th>Savings</th>
                <th>FOSA</th>
                <th>Capital</th>`;

            data[0].loans.forEach(l => {
                head += `<th>${l.loan_type_name} Taken</th>
                         <th>${l.loan_type_name} Bal</th>`;
            });

            head += `</tr>`;
            document.getElementById('thead').innerHTML = head;

            let rows = '';
            data.forEach((r,i) => {

                rows += `<tr>
                    <td>${i+1}</td>
                    <td>${r.member_name}</td>
                    <td>${r.member_sacco_id}</td>
                    <td>${r.member_national_id}</td>
                    <td>${r.member_gender}</td>
                    <td align="right">${r.savings.toLocaleString()}</td>
                    <td align="right">${r.fosa.toLocaleString()}</td>
                    <td align="right">${r.capital.toLocaleString()}</td>`;

                r.loans.forEach(l => {
                    rows += `<td align="right">${l.taken.toLocaleString()}</td>
                             <td align="right">${l.balance.toLocaleString()}</td>`;
                });

                rows += `</tr>`;
            });

            document.getElementById('tbody').innerHTML = rows;
            document.getElementById('tableWrap').classList.remove('d-none');

            const exp = document.getElementById('exportBtn');
            exp.href = `{{ route('reports.members.financial_position.export') }}?period=${period}`;
            exp.classList.remove('disabled');
        });
};
</script>
@endsection
