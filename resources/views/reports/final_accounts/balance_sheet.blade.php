@extends('layouts.app')

@section('content')
<div class="row">

  {{-- FILTER CARD --}}
  <div class="col-md-12">
    <div class="card o-hidden mb-4">
      <div class="card-header d-flex align-items-center">
        <h3 class="w-50 float-start card-title m-0">Balance Sheet</h3>

        <div class="dropdown dropleft text-end w-50 float-end">
          <button class="btn bg-gray-100" id="dropdownMenuButton_bs" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="nav-icon i-Gear-2"></i>
          </button>
          <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_bs">
            <a class="dropdown-item" href="{{ route('reports.final_accounts.balance_sheet.pdf', request()->query()) }}">Download PDF</a>
            <a class="dropdown-item" href="{{ route('reports.final_accounts.balance_sheet.excel', request()->query()) }}">Download Excel</a>
          </div>
        </div>
      </div>

      <div class="card-body">
        @if (!empty($notices))
          <div class="alert alert-warning">
            <ul class="mb-0">
              @foreach ($notices as $n)
                <li>{{ $n }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <form method="GET" action="{{ route('reports.final_accounts.balance_sheet') }}">
          <div class="row">

            <div class="col-md-3">
              <label class="form-label fw-bold">Mode</label>
              <select name="mode" class="form-control">
                <option value="period" {{ (request('mode','period')=='period') ? 'selected' : '' }}>As at Period (YYYYMM)</option>
                <option value="date" {{ (request('mode')=='date') ? 'selected' : '' }}>As at Date</option>
              </select>
            </div>

            <div class="col-md-3">
              <label class="form-label fw-bold">As at Period</label>
              <input type="text" name="as_at_period" value="{{ request('as_at_period', $ctx['as_at_period'] ?? '') }}" class="form-control" placeholder="YYYYMM">
              <small class="text-muted">Used when mode=period.</small>
            </div>

            <div class="col-md-3">
              <label class="form-label fw-bold">As at Date</label>
              <input type="date" name="as_at_date" value="{{ request('as_at_date', isset($ctx['as_at_date']) && $ctx['as_at_date'] ? $ctx['as_at_date']->format('Y-m-d') : '') }}" class="form-control">
              <small class="text-muted">Used when mode=date. End-of-day is applied.</small>
            </div>

            <div class="col-md-3 d-flex align-items-end">
              <button class="btn btn-primary w-100" type="submit">
                <i class="nav-icon i-Search-People me-1"></i> Run Report
              </button>
            </div>

          </div>
        </form>

        <hr>

        <div class="row">
          <div class="col-md-3">
            <div class="p-3 bg-light rounded">
              <div class="text-muted">Total Assets</div>
              <div class="fw-bold">{{ number_format($totals['total_assets'] ?? 0, 2) }}</div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="p-3 bg-light rounded">
              <div class="text-muted">Total Liabilities</div>
              <div class="fw-bold">{{ number_format($totals['total_liabilities'] ?? 0, 2) }}</div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="p-3 bg-light rounded">
              <div class="text-muted">Total Capital</div>
              <div class="fw-bold">{{ number_format($totals['total_capital'] ?? 0, 2) }}</div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="p-3 bg-light rounded">
              <div class="text-muted">Balance Check (A - (L+C))</div>
              <div class="fw-bold {{ (($totals['diff'] ?? 0) == 0) ? 'text-success' : 'text-danger' }}">
                {{ number_format($totals['diff'] ?? 0, 2) }}
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>


  {{-- TABLE CARD --}}
  <div class="col-md-12">
    <div class="card o-hidden mb-4">
      <div class="card-header d-flex align-items-center">
        <h3 class="w-50 float-start card-title m-0">Balance Sheet Lines</h3>
        <div class="dropdown dropleft text-end w-50 float-end">
          <button class="btn bg-gray-100" id="dropdownMenuButton_bs2" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="nav-icon i-Gear-2"></i>
          </button>
          <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_bs2">
            <a class="dropdown-item" href="{{ route('reports.final_accounts.balance_sheet.pdf', request()->query()) }}">Download PDF</a>
            <a class="dropdown-item" href="{{ route('reports.final_accounts.balance_sheet.excel', request()->query()) }}">Download Excel</a>
          </div>
        </div>
      </div>

      <div class="card-body">
        <div class="table-responsive">
          <table class="table text-center table-sm">
            <thead>
              <tr>
                <th>#</th>
                <th>Group</th>
                <th class="text-start">Main Account</th>
                <th class="text-end">Debit</th>
                <th class="text-end">Credit</th>
                <th class="text-end">Balance (Dr - Cr)</th>
              </tr>
            </thead>
            <tbody>
              @php $i=1; @endphp
              @forelse($rows as $r)
                <tr>
                  <td>{{ $i++ }}</td>
                  <td>
                    <span class="badge
                      {{ ($r->main_group=='ASSET') ? 'bg-info' : (($r->main_group=='LIABILITY') ? 'bg-warning' : 'bg-dark') }}">
                      {{ $r->main_group }}
                    </span>
                  </td>
                  <td class="text-start fw-bold">{{ $r->main_account_name }}</td>
                  <td class="text-end">{{ number_format($r->debit ?? 0, 2) }}</td>
                  <td class="text-end">{{ number_format($r->credit ?? 0, 2) }}</td>
                  <td class="text-end">{{ number_format($r->balance ?? 0, 2) }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="text-muted">No records found for the selected cutoff.</td>
                </tr>
              @endforelse
            </tbody>

            @if(!empty($rows) && count($rows) > 0)
            <tfoot>
              <tr class="fw-bold">
                <td colspan="5" class="text-end">TOTAL ASSETS</td>
                <td class="text-end">{{ number_format($totals['total_assets'] ?? 0, 2) }}</td>
              </tr>
              <tr class="fw-bold">
                <td colspan="5" class="text-end">TOTAL LIABILITIES</td>
                <td class="text-end">{{ number_format($totals['total_liabilities'] ?? 0, 2) }}</td>
              </tr>
              <tr class="fw-bold">
                <td colspan="5" class="text-end">TOTAL CAPITAL</td>
                <td class="text-end">{{ number_format($totals['total_capital'] ?? 0, 2) }}</td>
              </tr>
              <tr class="fw-bold">
                <td colspan="5" class="text-end">LIABILITIES + CAPITAL</td>
                <td class="text-end">{{ number_format($totals['liabilities_plus_capital'] ?? 0, 2) }}</td>
              </tr>
              <tr class="fw-bold">
                <td colspan="5" class="text-end">BALANCE CHECK (A - (L+C))</td>
                <td class="text-end {{ (($totals['diff'] ?? 0) == 0) ? 'text-success' : 'text-danger' }}">
                  {{ number_format($totals['diff'] ?? 0, 2) }}
                </td>
              </tr>
            </tfoot>
            @endif

          </table>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection
