@extends('layouts.app')

@section('content')
<style>
  /* ====== TB page polish (scoped) ====== */
  .tb-wrap{ --tb-primary:#5b2aa3; --tb-soft:#f6f3ff; --tb-ink:#111827; --tb-muted:#6b7280; --tb-border:#e5e7eb; }
  .tb-card{ border:1px solid var(--tb-border); border-radius:14px; box-shadow:0 10px 24px rgba(17,24,39,.06); }
  .tb-card .card-header{ background:linear-gradient(180deg, #ffffff, #fbfbff); border-bottom:1px solid var(--tb-border); border-top-left-radius:14px; border-top-right-radius:14px; }
  .tb-title{ font-weight:800; letter-spacing:.2px; color:var(--tb-ink); }
  .tb-subtle{ color:var(--tb-muted); font-size:12px; white-space:nowrap; }
  .tb-label{ font-weight:800; color:var(--tb-ink); white-space:nowrap; }
  .tb-input, .tb-select{ border-radius:12px; border:1px solid var(--tb-border); }
  .tb-input:focus, .tb-select:focus{ border-color:rgba(91,42,163,.45); box-shadow:0 0 0 .2rem rgba(91,42,163,.12); }
  .tb-chip{ display:inline-flex; align-items:center; gap:.45rem; background:var(--tb-soft); border:1px solid rgba(91,42,163,.18); color:var(--tb-primary); padding:.35rem .7rem; border-radius:999px; font-weight:800; font-size:12px; white-space:nowrap; }
  .tb-btn{ border-radius:12px; font-weight:900; letter-spacing:.2px; padding:.85rem 1rem; }
  .tb-btn-primary{ background:var(--tb-primary); border-color:var(--tb-primary); }
  .tb-btn-primary:hover{ filter:brightness(.95); }
  .tb-kpi{ border-radius:14px; border:1px solid var(--tb-border); background:#fff; padding:14px 16px; }
  .tb-kpi .k{ font-size:12px; color:var(--tb-muted); font-weight:800; white-space:nowrap; }
  .tb-kpi .v{ font-size:18px; font-weight:900; color:var(--tb-ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .tb-kpi.good .v{ color:#065f46; }
  .tb-kpi.bad .v{ color:#b91c1c; }
  .tb-table thead th{ white-space:nowrap; }
  .tb-table td{ vertical-align:middle; }
  .tb-main, .tb-sub{ white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:380px; }
  .tb-badge{ font-weight:900; letter-spacing:.2px; border-radius:999px; padding:.35rem .6rem; white-space:nowrap; }
</style>

<div class="row tb-wrap">

  {{-- HEADER + FILTERS --}}
  <div class="col-12">
    <div class="card tb-card mb-4">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <h3 class="card-title m-0 tb-title">Trial Balance</h3>
          <span class="tb-chip">
            <i class="nav-icon i-Financial"></i>
            {{ (($ctx['mode'] ?? '') === 'period') ? ($ctx['as_at_period'] ?? '') : (isset($ctx['as_at_date']) ? $ctx['as_at_date']->format('Y-m-d') : '') }}
          </span>
        </div>

        <div class="dropdown dropleft text-end">
          <button class="btn bg-gray-100" id="dropdownMenuButton_tb" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="nav-icon i-Gear-2"></i>
          </button>
          <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_tb">
            <a class="dropdown-item" href="{{ route('reports.final_accounts.trial_balance.pdf', request()->query()) }}">Download PDF</a>
            <a class="dropdown-item" href="{{ route('reports.final_accounts.trial_balance.excel', request()->query()) }}">Download Excel</a>
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

        <form method="GET" action="{{ route('reports.final_accounts.trial_balance') }}">
          <div class="row g-3 align-items-end">

            {{-- MODE --}}
            <div class="col-12 col-md-3">
              <label class="form-label tb-label mb-1">Mode</label>
              <select name="mode" class="form-control tb-select">
                <option value="period" {{ request('mode', $ctx['mode'] ?? 'period') == 'period' ? 'selected' : '' }}>As at Period</option>
                <option value="date" {{ request('mode', $ctx['mode'] ?? '') == 'date' ? 'selected' : '' }}>As at Date</option>
              </select>
              <div class="tb-subtle mt-1">Use Period for month-end.</div>
            </div>

            {{-- PERIOD --}}
            <div class="col-12 col-md-3">
              <label class="form-label tb-label mb-1">As at Period</label>
              <div class="input-group">
                <span class="input-group-text" style="border-radius:12px 0 0 12px; border:1px solid var(--tb-border); background:#fff; white-space:nowrap;">YYYYMM</span>
                <input
                  type="text"
                  name="as_at_period"
                  value="{{ request('as_at_period', $ctx['as_at_period'] ?? '') }}"
                  class="form-control tb-input"
                  inputmode="numeric"
                  maxlength="6"
                  pattern="\d{6}"
                  placeholder="202602"
                  oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,6)"
                  style="border-radius:0 12px 12px 0;"
                >
              </div>
              <div class="tb-subtle mt-1">Exactly 6 digits.</div>
            </div>

            {{-- DATE --}}
            <div class="col-12 col-md-3">
              <label class="form-label tb-label mb-1">As at Date</label>
              <div class="input-group">
                <span class="input-group-text" style="border-radius:12px 0 0 12px; border:1px solid var(--tb-border); background:#fff; white-space:nowrap;">
                  <i class="nav-icon i-Calendar-4"></i>
                </span>
                <input
                  type="date"
                  name="as_at_date"
                  value="{{ request('as_at_date', isset($ctx['as_at_date']) && $ctx['as_at_date'] ? $ctx['as_at_date']->format('Y-m-d') : '') }}"
                  class="form-control tb-input"
                  style="border-radius:0 12px 12px 0;"
                >
              </div>
              <div class="tb-subtle mt-1">End-of-day applied.</div>
            </div>

            {{-- RUN --}}
            <div class="col-12 col-md-3">
              <button class="btn tb-btn tb-btn-primary w-100 text-white" type="submit">
                <i class="nav-icon i-Search-People me-2"></i> Run Report
              </button>
              <div class="tb-subtle mt-1 text-center">Totals + lines.</div>
            </div>

          </div>
        </form>

        <hr class="my-4">

        {{-- KPI SUMMARY --}}
        @php
          $diff = (float) ($totals['diff'] ?? 0);
        @endphp

        <div class="row g-3">
          <div class="col-12 col-md-3">
            <div class="tb-kpi">
              <div class="k">Total Debit</div>
              <div class="v">{{ number_format($totals['debit'] ?? 0, 2) }}</div>
            </div>
          </div>
          <div class="col-12 col-md-3">
            <div class="tb-kpi">
              <div class="k">Total Credit</div>
              <div class="v">{{ number_format($totals['credit'] ?? 0, 2) }}</div>
            </div>
          </div>
          <div class="col-12 col-md-3">
            <div class="tb-kpi {{ ($diff == 0.0) ? 'good' : 'bad' }}">
              <div class="k">Difference</div>
              <div class="v">{{ number_format($diff, 2) }}</div>
            </div>
          </div>
          <div class="col-12 col-md-3">
            <div class="tb-kpi">
              <div class="k">Cutoff</div>
              <div class="v">
                {{ (($ctx['mode'] ?? '') === 'period') ? ($ctx['as_at_period'] ?? '') : (isset($ctx['as_at_date']) ? $ctx['as_at_date']->format('Y-m-d') : '') }}
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  {{-- TABLE --}}
  <div class="col-12">
    <div class="card tb-card mb-4">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title m-0 tb-title">Trial Balance Lines</h3>

        <div class="dropdown dropleft text-end">
          <button class="btn bg-gray-100" id="dropdownMenuButton_tb2" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="nav-icon i-Gear-2"></i>
          </button>
          <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_tb2">
            <a class="dropdown-item" href="{{ route('reports.final_accounts.trial_balance.pdf', request()->query()) }}">Download PDF</a>
            <a class="dropdown-item" href="{{ route('reports.final_accounts.trial_balance.excel', request()->query()) }}">Download Excel</a>
          </div>
        </div>
      </div>

      @php
        $groupOf = function ($t) {
          $t = strtoupper(trim((string) $t));
          if (str_starts_with($t, 'ASSET')) return 'ASSET';
          if (str_starts_with($t, 'LIABILIT')) return 'LIABILITY';
          if (str_starts_with($t, 'CAPITAL')) return 'CAPITAL';
          if (str_starts_with($t, 'INCOME')) return 'INCOME';
          if (str_starts_with($t, 'EXPENSE')) return 'EXPENSE';
          return 'OTHER';
        };

        $badgeClass = function ($g) {
          return match ($g) {
            'ASSET' => 'bg-info',
            'LIABILITY' => 'bg-warning',
            'CAPITAL' => 'bg-dark',
            'INCOME' => 'bg-success',
            'EXPENSE' => 'bg-danger',
            default => 'bg-secondary',
          };
        };
      @endphp

      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm tb-table text-center">
            <thead>
              <tr>
                <th>#</th>
                <th>Group</th>
                <th class="text-start">Main</th>
                <th>Main Type</th>
                <th class="text-start">Sub</th>
                <th class="text-end">Debit</th>
                <th class="text-end">Credit</th>
              </tr>
            </thead>

            <tbody>
              @php $i=1; @endphp

              @forelse(($rows ?? collect()) as $r)
                @php $g = $groupOf($r->main_account_type); @endphp
                <tr>
                  <td>{{ $i++ }}</td>
                  <td><span class="badge tb-badge {{ $badgeClass($g) }}">{{ $g }}</span></td>

                  <td class="text-start">
                    <div class="tb-main fw-bold">{{ $r->main_account_code }} - {{ $r->main_account_name }}</div>
                  </td>

                  <td><span style="white-space:nowrap;">{{ $r->main_account_type }}</span></td>

                  <td class="text-start">
                    <div class="tb-sub fw-bold">{{ $r->sub_account_code }} - {{ $r->sub_account_name }}</div>
                  </td>

                  <td class="text-end" style="white-space:nowrap;">{{ number_format($r->tb_debit ?? 0, 2) }}</td>
                  <td class="text-end" style="white-space:nowrap;">{{ number_format($r->tb_credit ?? 0, 2) }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="text-muted">No records found for the selected cutoff.</td>
                </tr>
              @endforelse
            </tbody>

            @if(isset($rows) && ($rows instanceof \Illuminate\Support\Collection ? $rows->count() > 0 : (is_countable($rows) && count($rows) > 0)))
              <tfoot>
                <tr class="fw-bold table-secondary">
                  <td colspan="5" class="text-end">GRAND TOTALS</td>
                  <td class="text-end" style="white-space:nowrap;">{{ number_format($totals['debit'] ?? 0, 2) }}</td>
                  <td class="text-end" style="white-space:nowrap;">{{ number_format($totals['credit'] ?? 0, 2) }}</td>
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
