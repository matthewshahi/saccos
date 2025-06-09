@extends('layouts.app')

@section('content')
@php
  use Carbon\Carbon;
  $startText = Carbon::createFromFormat('Ym', $start_period)->format('M Y');
  $endText = Carbon::createFromFormat('Ym', $end_period)->format('M Y');
@endphp

<div class="container">
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <div>
        <h4 class="card-title mb-0">Top Shareholding Members</h4>
        <small class="text-muted">
          Showing top 50 members based on total share contributions from
          <strong>{{ $startText }}</strong> to <strong>{{ $endText }}</strong>
          @if (!empty($filter_company))
            for company <strong>{{ strtoupper($filter_company) }}</strong>
          @endif.
        </small>
      </div>
      <button class="btn btn-outline-primary btn-sm" onclick="downloadDivAsCsv('topMembersWrapper', 'top_shareholders.csv')">
        Download CSV
      </button>
    </div>

    <div class="card-body">
      <div class="table-responsive" id="topMembersWrapper">
        <table class="table table-sm table-bordered text-center">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th class="text-start">NAME</th>
              <th class="text-start">ID</th>
              <th class="text-start">PHONE</th>
              <th class="text-start">COMPANY</th>
              <th>TOTAL SHARES (KES)</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($members as $row)
              <tr>
                <td>{{ $loop->iteration }}</td>
                <td class="text-start">{{ $row->member_name }}</td>
                <td class="text-start">{{ $row->member_national_id }}</td>
                <td class="text-start">{{ $row->member_phone_no }}</td>
                <td class="text-start">{{ $row->company_name ?? '-' }}</td>
                <td>{{ number_format($row->total_amount, 2) }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="6" class="text-muted text-center">No data found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
function downloadDivAsCsv(divId, filename = 'export.csv') {
  const div = document.getElementById(divId);
  if (!div) return alert('Div not found');
  const table = div.querySelector('table');
  if (!table) return alert('Table not found inside div');

  const rows = [];
  const trs = table.querySelectorAll('tr');

  trs.forEach(tr => {
    const cells = tr.querySelectorAll('th, td');
    const row = [];
    cells.forEach(cell => {
      let text = cell.innerText || cell.textContent;
      text = text.replace(/"/g, '""').replace(/\n/g, ' ').trim();
      row.push(`"${text}"`);
    });
    rows.push(row.join(','));
  });

  const csvContent = rows.join('\n');
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const link = document.createElement('a');
  link.href = URL.createObjectURL(blob);
  link.setAttribute('download', filename);
  link.style.display = 'none';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
}
</script>
@endsection