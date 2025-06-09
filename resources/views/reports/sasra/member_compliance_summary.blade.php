@extends('layouts.app')

@section('content')
<style>
  #complianceTableWrapper td,
  #complianceTableWrapper th {
    white-space: nowrap;
  }
</style>
<div class="container">
  <div class="card mb-4">
    <div class="card-header">
      <h4 class="card-title d-flex justify-content-between align-items-center">
        <span>Monthly Share Deposit Compliance Summary</span>
        <button class="btn btn-outline-primary btn-sm" onclick="downloadDivAsCsv('complianceTableWrapper', 'share_compliance_summary.csv')">
          Download CSV
        </button>
      </h4>
    </div>

    <div class="card-body">
      <form method="GET" class="row g-2 mb-3">
        <div class="col-md-2">
          <input type="text" name="start_period" class="form-control" placeholder="Start (e.g. 202301)" value="{{ $start_period }}">
        </div>
        <div class="col-md-2">
          <input type="text" name="end_period" class="form-control" placeholder="End (e.g. 202312)" value="{{ $end_period }}">
        </div>
        <div class="col-md-2">
          <input type="text" name="search_name" class="form-control" placeholder="Member Name" value="{{ $search_name }}">
        </div>
        <div class="col-md-2">
          <input type="text" name="search_phone" class="form-control" placeholder="Phone" value="{{ $search_phone }}">
        </div>
        <div class="col-md-2">
          <input type="text" name="search_company" class="form-control" placeholder="Company" value="{{ $search_company }}">
        </div>
        <div class="col-md-2">
          <select name="status" class="form-select">
            <option value="">All Members</option>
            <option value="Y" {{ $status == 'Y' ? 'selected' : '' }}>Active Members</option>
            <option value="N" {{ $status == 'N' ? 'selected' : '' }}>Inactive Members</option>
          </select>
        </div>
        <div class="col-md-12 text-end">
          <button class="btn btn-primary">Filter</button>
        </div>
      </form>

      <div class="table-responsive" id="complianceTableWrapper">
        <table class="table table-bordered table-sm text-center">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th class="text-start">NAME</th>
              <th class="text-start">ID</th>
              <th class="text-start">PHONE</th>
              <th class="text-start">COMPANY</th>
              <th>FIRST PAY</th>
              <th>LAST PAY</th>
              <th>MONTHS PAID</th>
              <th>MONTHS MISSED</th>
              <th>% COMPLIANT</th>
              <th>STATUS</th>
            </tr>
          </thead>
          <tbody>
            @forelse ($rows as $index => $row)
              <tr>
                <td>{{ $index + 1 }}</td>
                <td class="text-start">{{ $row['member_name'] }}</td>
                <td class="text-start">{{ $row['member_id_number'] }}</td>
                <td class="text-start">{{ $row['member_phone'] }}</td>
                <td class="text-start">{{ $row['company_name'] ?? '-' }}</td>
                <td>{{ $row['first_period'] }}</td>
                <td>{{ $row['last_period'] }}</td>
                <td>{{ $row['months_paid'] }}</td>
                <td>{{ $row['months_missed'] }}</td>
                <td>{{ $row['compliance_percent'] }}%</td>
                <td>
                  @if($row['compliance_percent'] >= 90)
                    <span class="badge bg-success">✅ Good</span>
                  @elseif($row['compliance_percent'] >= 50)
                    <span class="badge bg-warning text-dark">⚠️ Fair</span>
                  @else
                    <span class="badge bg-danger">❌ Poor</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="11" class="text-center text-muted">No data found.</td>
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