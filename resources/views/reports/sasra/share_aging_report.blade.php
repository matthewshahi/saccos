@extends('layouts.app')

@section('content')
<div class="container">
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <h4 class="card-title">Share Aging Report</h4>
      <button class="btn btn-outline-primary btn-sm" onclick="downloadDivAsCsv('agingReportWrapper', 'share_aging_report.csv')">
        Download CSV
      </button>
    </div>

    <div class="card-body">
      <p class="text-muted">Aging buckets: {{ implode(', ', $buckets) }} (based on last share contribution date)</p>

      {{-- Legend --}}
      <div class="mb-3">
        <strong>Legend:</strong>
        <span class="badge bg-success">0-3</span>
        <span class="badge bg-info text-dark">4-6</span>
        <span class="badge bg-warning text-dark">7-12</span>
        <span class="badge bg-secondary">13-24</span>
        <span class="badge bg-danger">25-36</span>
        <span class="badge bg-dark">37+</span>
        <span class="badge bg-light text-dark">Unknown</span>
      </div>

      <form method="GET" action="{{ route('reports.shares.aging') }}" class="mb-3">
        <div class="row g-2">
          <div class="col-md-6">
            <input type="text" name="search" value="{{ old('search', $search ?? '') }}" class="form-control" placeholder="Search by name, ID, phone or company...">
          </div>
          <div class="col-md-3">
            <select name="status" class="form-select">
              <option value="all" {{ ($status ?? 'all') === 'all' ? 'selected' : '' }}>All</option>
              <option value="active" {{ ($status ?? '') === 'active' ? 'selected' : '' }}>Active</option>
              <option value="inactive" {{ ($status ?? '') === 'inactive' ? 'selected' : '' }}>Inactive</option>
            </select>
          </div>
          <div class="col-md-3">
            <button type="submit" class="btn btn-outline-primary w-100">Filter</button>
          </div>
        </div>
      </form>

      <div class="table-responsive" id="agingReportWrapper">
        <table class="table table-bordered table-sm text-center">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th class="text-start">NAME</th>
              <th class="text-start">ID NUMBER</th>
              <th class="text-start">PHONE</th>
              <th class="text-start">COMPANY</th>
              <th>LAST PAID PERIOD</th>
              <th>MONTHS AGO</th>
              <th>BUCKET</th>
              <th>TOTAL SHARES (KES)</th>
            </tr>
          </thead>
          <tbody>
            @php
              $bucketColors = [
                '0-3' => 'table-success',
                '4-6' => 'table-info',
                '7-12' => 'table-warning',
                '13-24' => 'table-secondary',
                '25-36' => 'table-danger',
                '37+' => 'table-dark',
                'Unknown' => 'table-light',
              ];
            @endphp

            @forelse ($rows as $row)
              @php
                $colorClass = $bucketColors[$row['bucket']] ?? '';
              @endphp
              <tr class="{{ $colorClass }}">
                <td>{{ $loop->iteration }}</td>
                <td class="text-start">{{ $row['member_name'] }}</td>
                <td class="text-start">{{ $row['id_number'] }}</td>
                <td class="text-start">{{ $row['phone'] }}</td>
                <td class="text-start">{{ $row['company_name'] }}</td>
                <td>{{ $row['last_period'] }}</td>
                <td>{{ $row['months_ago'] ?? '-' }}</td>
                <td><span class="badge bg-secondary">{{ $row['bucket'] }}</span></td>
                <td>{{ number_format($row['total_shares'], 2) }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="9" class="text-center text-muted">No share data found.</td>
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