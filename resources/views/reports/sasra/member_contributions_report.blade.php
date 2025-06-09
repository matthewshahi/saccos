@extends('layouts.app')

@section('content')
<style>
  .no-contribution {
    background-color: #f5f2fc !important; /* Soft lavender */
    color: #6b4c9a !important;            /* Theme-matching muted purple */
    font-style: italic;
  }
</style>

<div class="container">
  <div class="card o-hidden mb-4">
    <div class="card-header d-flex align-items-center">
      <h3 class="w-50 float-start card-title m-0">SASRA Member Contributions Report</h3>
      <div class="dropdown dropleft text-end w-50 float-end">
        <button class="btn bg-gray-100" id="dropdownMenuButton_table2" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <i class="nav-icon i-Gear-2"></i>
        </button>
        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_table2">
  <a class="dropdown-item" href="#" onclick="downloadDivAsCsv('reportTableWrapper', 'member_contributions.csv')">
    Download CSV
  </a>
</div>
      </div>
    </div>

    <div class="card-body">
      <form method="GET" class="row mb-4">
        <div class="col-md-3">
          <input type="text" name="search" class="form-control" placeholder="Search name, ID, phone, company" value="{{ request('search') }}">
        </div>
        <div class="col-md-2">
          <input type="text" name="start_period" class="form-control" placeholder="Start Period (e.g. 202301)" value="{{ request('start_period', $start_period) }}">
        </div>
        <div class="col-md-2">
          <input type="text" name="end_period" class="form-control" placeholder="End Period (e.g. 202312)" value="{{ request('end_period', $end_period) }}">
        </div>
        <div class="col-md-2">
          <select name="status" class="form-select">
            <option value="">All Members</option>
            <option value="y" {{ request('status') == 'y' ? 'selected' : '' }}>Active Members</option>
            <option value="n" {{ request('status') == 'n' ? 'selected' : '' }}>Inactive Members</option>
          </select>
        </div>
        <div class="col-md-2">
          <select name="only_blank" class="form-select">
            <option value="0">All</option>
            <option value="1" {{ request('only_blank') == '1' ? 'selected' : '' }}>No Contributions</option>
          </select>
        </div>
        <div class="col-md-1">
          <button class="btn btn-primary w-100">Apply</button>
        </div>
      </form>
<div id="reportTableWrapper">
      <div class="table-responsive">
        <table class="table table-bordered table-sm text-center" style="white-space: nowrap;">
          <thead class="table-light">
            <tr>
              <th class="text-start">NAME</th>
              <th class="text-start">ID NUMBER</th>
              <th class="text-start">PHONE</th>
              <th class="text-start">COMPANY</th>
              @foreach ($periods as $period)
                <th class="text-end">{{ \Carbon\Carbon::createFromFormat('Ym', $period)->format('M Y') }}</th>
              @endforeach
              <th class="text-end text-success">TOTAL</th>
            </tr>
          </thead>
          <tbody>
            @php
              $columnTotals = array_fill_keys($periods, 0);
              $grandTotal = 0;
            @endphp

            @forelse ($rows as $row)
              @php
                $memberTotal = $row['total'] ?? array_sum(array_intersect_key($row, array_flip($periods)));
              @endphp
              <tr class="{{ $memberTotal <= 0 ? 'no-contribution' : '' }}">
                <td class="text-start text-uppercase">{{ $row['member_name'] }}</td>
                <td class="text-start text-uppercase">{{ $row['member_id_number'] }}</td>
                <td class="text-start text-uppercase">{{ $row['member_phone'] }}</td>
                <td class="text-start text-uppercase">{{ $row['company_name'] }}</td>
                @foreach ($periods as $period)
                  @php
                    $amount = $row[$period];
                    $columnTotals[$period] += $amount;
                    $grandTotal += $amount;
                  @endphp
                  <td class="text-end">{{ number_format($amount, 2) }}</td>
                @endforeach
                <td class="text-end fw-bold text-success">{{ number_format($memberTotal, 2) }}</td>
              </tr>
            @empty
              <tr>
                <td colspan="{{ 4 + count($periods) + 1 }}" class="text-center text-muted">No records found.</td>
              </tr>
            @endforelse
          </tbody>

          @if(count($rows))
          <tfoot>
            <tr class="fw-bold bg-light">
              <td colspan="4" class="text-end text-uppercase">TOTAL</td>
              @foreach ($periods as $period)
                <td class="text-end">{{ number_format($columnTotals[$period], 2) }}</td>
              @endforeach
              <td class="text-end text-success">{{ number_format($grandTotal, 2) }}</td>
            </tr>
          </tfoot>
          @endif
        </table>
      </div>
</div>
    </div>
  </div>
</div>

<script>
function downloadDivAsCsv(divId, filename) {
    const rows = [];
    const table = document.querySelector(`#${divId} table`);
    if (!table) return alert('Table not found inside div');

    for (const row of table.rows) {
        const cells = Array.from(row.cells).map(cell => {
            const text = cell.textContent.replace(/\s+/g, ' ').trim();
            // Escape quotes
            return `"${text.replace(/"/g, '""')}"`;
        });
        rows.push(cells.join(','));
    }

    const csvContent = rows.join('\n');
    const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');

    link.setAttribute('href', URL.createObjectURL(blob));
    link.setAttribute('download', filename);
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>
@endsection