@extends('layouts.app')

@section('content')
<style>
  /* ====== P&L page polish (scoped) ====== */
  .pl-wrap{ --pl-primary:#5b2aa3; --pl-soft:#f6f3ff; --pl-ink:#111827; --pl-muted:#6b7280; --pl-border:#e5e7eb; }
  .pl-card{ border:1px solid var(--pl-border); border-radius:14px; box-shadow:0 10px 24px rgba(17,24,39,.06); }
  .pl-card .card-header{ background:linear-gradient(180deg, #ffffff, #fbfbff); border-bottom:1px solid var(--pl-border); border-top-left-radius:14px; border-top-right-radius:14px; }
  .pl-title{ font-weight:800; letter-spacing:.2px; color:var(--pl-ink); }
  .pl-subtle{ color:var(--pl-muted); font-size:12px; white-space:nowrap; }
  .pl-label{ font-weight:800; color:var(--pl-ink); white-space:nowrap; }
  .pl-input, .pl-select{ border-radius:12px; border:1px solid var(--pl-border); }
  .pl-input:focus, .pl-select:focus{ border-color:rgba(91,42,163,.45); box-shadow:0 0 0 .2rem rgba(91,42,163,.12); }
  .pl-chip{ display:inline-flex; align-items:center; gap:.45rem; background:var(--pl-soft); border:1px solid rgba(91,42,163,.18); color:var(--pl-primary); padding:.35rem .7rem; border-radius:999px; font-weight:800; font-size:12px; white-space:nowrap; }
  .pl-btn{ border-radius:12px; font-weight:900; letter-spacing:.2px; padding:.85rem 1rem; }
  .pl-btn-primary{ background:var(--pl-primary); border-color:var(--pl-primary); }
  .pl-btn-primary:hover{ filter:brightness(.95); }
  .pl-kpi{ border-radius:14px; border:1px solid var(--pl-border); background:#fff; padding:14px 16px; }
  .pl-kpi .k{ font-size:12px; color:var(--pl-muted); font-weight:800; white-space:nowrap; }
  .pl-kpi .v{ font-size:18px; font-weight:900; color:var(--pl-ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .pl-kpi.good .v{ color:#065f46; }
  .pl-kpi.bad .v{ color:#b91c1c; }
  .pl-table thead th{ white-space:nowrap; }
  .pl-table td{ vertical-align:middle; }
  .pl-main{ white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:520px; }
  .pl-badge{ font-weight:900; letter-spacing:.2px; border-radius:999px; padding:.35rem .6rem; white-space:nowrap; }
</style>

@php
  $rows = $rows ?? collect();

  // ---------------------------
  // Range label (chip)
  // ---------------------------
  $rangeLabel = '';
  if (($ctx['mode'] ?? '') === 'period') {
      $rangeLabel = ($ctx['period_from'] ?? '') . ' → ' . ($ctx['period_to'] ?? '');
  } else {
      $rangeLabel =
          (isset($ctx['date_from']) && $ctx['date_from'] ? $ctx['date_from']->format('Y-m-d') : '') .
          ' → ' .
          (isset($ctx['date_to']) && $ctx['date_to'] ? $ctx['date_to']->format('Y-m-d') : '');
  }

  // ---------------------------
  // Badge class helper
  // ---------------------------
  $badgeClass = function ($g) {
      $g = strtoupper(trim((string) $g));
      return match ($g) {
          'INCOME'  => 'bg-success',
          'EXPENSE' => 'bg-danger',
          default   => 'bg-secondary',
      };
  };

  // ---------------------------
  // IMPORTANT FIX:
  // Totals derived from what we display: net_effect (Cr - Dr)
  // This prevents "totals don't match rows" and handles reversals properly.
  // ---------------------------
  $incomeTotal  = 0.0; // positive
  $expenseTotal = 0.0; // positive
  $otherCount   = 0;

  foreach ($rows as $rr) {
      $g = strtoupper((string) ($rr->main_group ?? ''));
      $g = $g ?: 'OTHER';

      $net = (float) ($rr->net_effect ?? 0); // SIGNED (Cr - Dr)

      if ($g === 'INCOME') {
          $incomeTotal += $net;           // reversals reduce income
      } elseif ($g === 'EXPENSE') {
          $expenseTotal += (-1 * $net);   // flip sign => positive expenses
      } else {
          $otherCount++;
      }
  }

  // Round for currency presentation
  $incomeTotal  = round($incomeTotal, 2);
  $expenseTotal = round($expenseTotal, 2);
  $netSurplus   = round($incomeTotal - $expenseTotal, 2);
@endphp

<div class="row pl-wrap">

  {{-- FILTER CARD --}}
  <div class="col-12">
    <div class="card pl-card mb-4">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <h3 class="card-title m-0 pl-title">Profit &amp; Loss</h3>
          <span class="pl-chip">
            <i class="nav-icon i-Financial"></i>
            {{ $rangeLabel }}
          </span>
        </div>

        <div class="dropdown dropleft text-end">
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
          <div class="alert alert-warning mb-3">
            <ul class="mb-0">
              @foreach ($notices as $n)
                <li>{{ $n }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        @if($otherCount > 0)
          <div class="alert alert-warning mb-3">
            {{ $otherCount }} line(s) are classified as <strong>OTHER</strong> and are excluded from Income/Expense totals.
            Fix your <code>main_account_type</code> mapping so P&amp;L rows are only INCOME/EXPENSE.
          </div>
        @endif

        <form method="GET" action="{{ route('reports.final_accounts.profit_loss') }}">
          <div class="row g-3 align-items-end">

            {{-- MODE --}}
            <div class="col-12 col-md-3">
              <label class="form-label pl-label mb-1">Mode</label>
              <select name="mode" class="form-control pl-select">
                <option value="period" {{ (request('mode', $ctx['mode'] ?? 'period')=='period') ? 'selected' : '' }}>Period Range</option>
                <option value="date" {{ (request('mode', $ctx['mode'] ?? '')=='date') ? 'selected' : '' }}>Date Range</option>
              </select>
              <div class="pl-subtle mt-1">P&amp;L is activity within a range.</div>
            </div>

            {{-- PERIOD FROM --}}
            <div class="col-12 col-md-3">
              <label class="form-label pl-label mb-1">Period From</label>
              <div class="input-group">
                <span class="input-group-text" style="border-radius:12px 0 0 12px; border:1px solid var(--pl-border); background:#fff; white-space:nowrap;">YYYYMM</span>
                <input
                  type="text"
                  name="period_from"
                  value="{{ request('period_from', $ctx['period_from'] ?? '') }}"
                  class="form-control pl-input"
                  inputmode="numeric"
                  maxlength="6"
                  pattern="\d{6}"
                  placeholder="202601"
                  oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,6)"
                  style="border-radius:0 12px 12px 0;"
                >
              </div>
              <div class="pl-subtle mt-1">Used when mode=period.</div>
            </div>

            {{-- PERIOD TO --}}
            <div class="col-12 col-md-3">
              <label class="form-label pl-label mb-1">Period To</label>
              <div class="input-group">
                <span class="input-group-text" style="border-radius:12px 0 0 12px; border:1px solid var(--pl-border); background:#fff; white-space:nowrap;">YYYYMM</span>
                <input
                  type="text"
                  name="period_to"
                  value="{{ request('period_to', $ctx['period_to'] ?? '') }}"
                  class="form-control pl-input"
                  inputmode="numeric"
                  maxlength="6"
                  pattern="\d{6}"
                  placeholder="202602"
                  oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,6)"
                  style="border-radius:0 12px 12px 0;"
                >
              </div>
              <div class="pl-subtle mt-1">Used when mode=period.</div>
            </div>

            {{-- RUN --}}
            <div class="col-12 col-md-3">
              <button class="btn pl-btn pl-btn-primary w-100 text-white" type="submit">
                <i class="nav-icon i-Search-People me-2"></i> Run Report
              </button>
              <div class="pl-subtle mt-1 text-center">Totals + breakdown.</div>
            </div>

            {{-- DATE FROM --}}
            <div class="col-12 col-md-3">
              <label class="form-label pl-label mb-1">Date From</label>
              <div class="input-group">
                <span class="input-group-text" style="border-radius:12px 0 0 12px; border:1px solid var(--pl-border); background:#fff; white-space:nowrap;">
                  <i class="nav-icon i-Calendar-4"></i>
                </span>
                <input
                  type="date"
                  name="date_from"
                  value="{{ request('date_from', isset($ctx['date_from']) && $ctx['date_from'] ? $ctx['date_from']->format('Y-m-d') : '') }}"
                  class="form-control pl-input"
                  style="border-radius:0 12px 12px 0;"
                >
              </div>
              <div class="pl-subtle mt-1">Used when mode=date.</div>
            </div>

            {{-- DATE TO --}}
            <div class="col-12 col-md-3">
              <label class="form-label pl-label mb-1">Date To</label>
              <div class="input-group">
                <span class="input-group-text" style="border-radius:12px 0 0 12px; border:1px solid var(--pl-border); background:#fff; white-space:nowrap;">
                  <i class="nav-icon i-Calendar-4"></i>
                </span>
                <input
                  type="date"
                  name="date_to"
                  value="{{ request('date_to', isset($ctx['date_to']) && $ctx['date_to'] ? $ctx['date_to']->format('Y-m-d') : '') }}"
                  class="form-control pl-input"
                  style="border-radius:0 12px 12px 0;"
                >
              </div>
              <div class="pl-subtle mt-1">End-of-day applied.</div>
            </div>

          </div>
        </form>

        <hr class="my-4">

        {{-- KPI SUMMARY --}}
        <div class="row g-3">
          <div class="col-12 col-md-4">
            <div class="pl-kpi good">
              <div class="k">Total Income</div>
              <div class="v">{{ number_format($incomeTotal, 2) }}</div>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <div class="pl-kpi bad">
              <div class="k">Total Expenses</div>
              <div class="v">{{ number_format($expenseTotal, 2) }}</div>
            </div>
          </div>
          <div class="col-12 col-md-4">
            <div class="pl-kpi {{ $netSurplus >= 0 ? 'good' : 'bad' }}">
              <div class="k">Net Surplus / (Deficit)</div>
              <div class="v">{{ number_format($netSurplus, 2) }}</div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  {{-- TABLE CARD --}}
  <div class="col-12">
    <div class="card pl-card mb-4">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title m-0 pl-title">Profit &amp; Loss Breakdown</h3>

        <div class="dropdown dropleft text-end">
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
          <table class="table table-sm pl-table text-center">
            <thead>
              <tr>
                <th>#</th>
                <th>Type</th>
                <th class="text-start">Sub Account</th>
                <th class="text-end">Debit</th>
                <th class="text-end">Credit</th>
                <th class="text-end">Net (Cr - Dr)</th>
              </tr>
            </thead>

            <tbody>
              @php $i=1; @endphp

              @forelse($rows as $r)
                @php
                  $g = strtoupper((string) ($r->main_group ?? ''));
                  $g = $g ?: 'OTHER';

                  $net = (float) ($r->net_effect ?? 0);
                @endphp

                <tr>
                  <td>{{ $i++ }}</td>

                  <td>
                    <span class="badge pl-badge {{ $badgeClass($g) }}">{{ $g }}</span>
                  </td>

                  <td class="text-start">
                    <div class="fw-bold pl-main">
                      {{ $r->sub_account_code ?? '' }} - {{ $r->sub_account_name ?? '' }}
                    </div>
                    <div class="pl-subtle">
                      {{ $r->main_account_code ?? '' }} - {{ $r->main_account_name ?? '' }}
                    </div>
                  </td>

                  <td class="text-end" style="white-space:nowrap;">{{ number_format($r->debit ?? 0, 2) }}</td>
                  <td class="text-end" style="white-space:nowrap;">{{ number_format($r->credit ?? 0, 2) }}</td>

                  <td class="text-end {{ $net >= 0 ? 'text-success' : 'text-danger' }}" style="white-space:nowrap;">
                    {{ number_format($net, 2) }}
                  </td>
                </tr>

              @empty
                <tr>
                  <td colspan="6" class="text-muted">No records found for the selected range.</td>
                </tr>
              @endforelse
            </tbody>

            @if($rows->count() > 0)
              <tfoot>
                <tr class="fw-bold table-secondary">
                  <td colspan="5" class="text-end">TOTAL INCOME</td>
                  <td class="text-end text-success" style="white-space:nowrap;">{{ number_format($incomeTotal, 2) }}</td>
                </tr>
                <tr class="fw-bold table-secondary">
                  <td colspan="5" class="text-end">TOTAL EXPENSES</td>
                  <td class="text-end text-danger" style="white-space:nowrap;">{{ number_format($expenseTotal, 2) }}</td>
                </tr>
                <tr class="fw-bold table-secondary">
                  <td colspan="5" class="text-end">NET SURPLUS / (DEFICIT)</td>
                  <td class="text-end {{ $netSurplus >= 0 ? 'text-success' : 'text-danger' }}" style="white-space:nowrap;">
                    {{ number_format($netSurplus, 2) }}
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
