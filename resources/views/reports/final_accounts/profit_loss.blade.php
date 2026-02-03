@extends('layouts.app')

@section('content')
<div class="row">

  {{-- FILTER CARD --}}
  <div class="col-md-12">
    <div class="card o-hidden mb-4">
      <div class="card-header d-flex align-items-center">
        <h3 class="w-50 float-start card-title m-0">Profit & Loss (Income & Expenditure)</h3>

        <div class="dropdown dropleft text-end w-50 float-end">
          <button class="btn bg-gray-100" id="dropdownMenuButton_pl" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="nav-icon i-Gear-2"></i>
          </button>
          <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_pl">
            <a class="dropdown-item" href="{{ route('reports.final_accounts.profit_loss.pdf', request()->query()) }}">Download PDF</a>
            <a class="dropdown-item" href="{{ route('reports.final_accounts.profit_loss.excel', request()->query()) }}">Download Excel</a>
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

        <form method="GET" action="{{ route('reports.final_accounts.profit_loss') }}">
          <div class="row">

            <div class="col-md-3">
              <label class="form-label fw-bold">Mode</label>
              <select name="mode" class="form-control">
                <option value="period" {{ (request('mode','period')=='period') ? 'selected' : '' }}>Period Range (YYYYMM)</option>
                <option value="date" {{ (request('mode')=='date') ? 'selected' : '' }}>Date Range</option>
              </select>
              <small class="text-muted">P&L is always movement within range.</small>
            </div>

            <div class="col-md-3">
              <label class="form-label fw-bold">Period From</label>
              <input type="text" name="period_from" value="{{ request('period_from', $ctx['period_from'] ?? '') }}" class="form-control" placeholder="YYYYMM">
            </div>

            <div class="col-md-3">
              <label class="form-label fw-bold">Period To</label>
              <input type="text" name="period_to" value="{{ request('period_to', $ctx['period_to'] ?? '') }}" class="form-control" placeholder="YYYYMM">
            </div>

            <div class="col-md-3 d-flex align-items-end">
              <button class="btn btn-primary w-100" type="submit">
                <i class="nav-icon i-Search-People me-1"></i> Run Report
              </button>
            </div>

            <div class="col-md-3 mt-3">
              <label class="form-label fw-bold">Date From</label>
              <input type="date" name="date_from" value="{{ request('date_from', isset($ctx['date_from']) && $ctx['date_from'] ? $ctx['date_from']->format('Y-m-d') : '') }}" class="form-control">
              <small class="text-muted">Used when mode=date.</small>
            </div>

            <div class="col-md-3 mt-3">
              <label class="form-label fw-bold">Date To</label>
              <input type="date" name="date_to" value="{{ request('date_to', isset($ctx['date_to']) && $ctx['date_to'] ? $ctx['date_to']->format('Y-m-d') : '') }}" class="form-control">
              <small class="text-muted">End-of-day applied.</small>
            </div>

          </div>
        </form>

        <hr>

        <div class="row">
          <div class="col-md-4">
            <div class="p-3 bg-light rounded">
              <div class="text-muted">Total Income</div>
              <div class="fw-bold text-success">{{ number_format($totals['income_total'] ?? 0, 2) }}</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="p-3 bg-light rounded">
              <div class="text-muted">Total Expenses</div>
              <div class="fw-bold text-danger">{{ number_format(abs($totals['expense_total'] ?? 0), 2) }}</div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="p-3 bg-light rounded">
              <div class="text-muted">Net Surplus / (Deficit)</div>
              <div class="fw-bold {{ (($totals['net_surplus'] ?? 0) >= 0) ? 'text-success' : 'text-danger' }}">
                {{ number_format($totals['net_surplus'] ?? 0, 2) }}
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
        <h3 class="w-50 float-start card-title m-0">Profit & Loss Lines</h3>
        <div class="dropdown dropleft text-end w-50 float-end">
          <button class="btn bg-gray-100" id="dropdownMenuButton_pl2" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="nav-icon i-Gear-2"></i>
          </button>
          <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_pl2">
            <a class="dropdown-item" href="{{ route('reports.final_accounts.profit_loss.pdf', request()->query()) }}">Download PDF</a>
            <a class="dropdown-item" href="{{ route('reports.final_accounts.profit_loss.excel', request()->query()) }}">Download Excel</a>
          </div>
        </div>
      </div>

      <div class="card-body">
        <div class="table-responsive">
          <table class="table text-center table-sm">
            <thead>
              <tr>
                <th>#</th>
                <th>Type</th>
                <th class="text-start">Main Account</th>
                <th class="text-end">Debit</th>
                <th class="text-end">Credit</th>
                <th class="text-end">P&L Effect (Cr - Dr)</th>
              </tr>
            </thead>
            <tbody>
              @php $i=1; @endphp
              @forelse($rows as $r)
                <tr>
                  <td>{{ $i++ }}</td>
                  <td>
                    <span class="badge {{ ($r->main_group=='INCOME') ? 'bg-success' : 'bg-danger' }}">
                      {{ $r->main_group }}
                    </span>
                  </td>
                  <td class="text-start fw-bold">{{ $r->main_account_name }}</td>
                  <td class="text-end">{{ number_format($r->debit ?? 0, 2) }}</td>
                  <td class="text-end">{{ number_format($r->credit ?? 0, 2) }}</td>
                  <td class="text-end {{ (($r->pnl_effect ?? 0) >= 0) ? 'text-success' : 'text-danger' }}">
                    {{ number_format($r->pnl_effect ?? 0, 2) }}
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="6" class="text-muted">No records found for the selected range.</td>
                </tr>
              @endforelse
            </tbody>

            @if(!empty($rows) && count($rows) > 0)
            <tfoot>
              <tr class="fw-bold">
                <td colspan="5" class="text-end">NET SURPLUS / (DEFICIT)</td>
                <td class="text-end {{ (($totals['net_surplus'] ?? 0) >= 0) ? 'text-success' : 'text-danger' }}">
                  {{ number_format($totals['net_surplus'] ?? 0, 2) }}
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
