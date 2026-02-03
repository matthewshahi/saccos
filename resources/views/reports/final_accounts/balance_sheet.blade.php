@extends('layouts.app')

@section('content')
<style>
  /* ====== Balance Sheet page polish (scoped) ====== */
  .bs-wrap{
    --bs-primary:#5b2aa3;
    --bs-soft:#f6f3ff;
    --bs-ink:#111827;
    --bs-muted:#6b7280;
    --bs-border:#e5e7eb;
  }
  .bs-card{
    border:1px solid var(--bs-border);
    border-radius:14px;
    box-shadow:0 10px 24px rgba(17,24,39,.06);
  }
  .bs-card .card-header{
    background:linear-gradient(180deg, #ffffff, #fbfbff);
    border-bottom:1px solid var(--bs-border);
    border-top-left-radius:14px;
    border-top-right-radius:14px;
  }
  .bs-title{ font-weight:800; letter-spacing:.2px; color:var(--bs-ink); }
  .bs-subtle{ color:var(--bs-muted); font-size:12px; white-space:nowrap; }
  .bs-label{ font-weight:800; color:var(--bs-ink); white-space:nowrap; }

  .bs-input, .bs-select{
    border-radius:12px;
    border:1px solid var(--bs-border);
  }
  .bs-input:focus, .bs-select:focus{
    border-color:rgba(91,42,163,.45);
    box-shadow:0 0 0 .2rem rgba(91,42,163,.12);
  }

  .bs-chip{
    display:inline-flex;
    align-items:center;
    gap:.45rem;
    background:var(--bs-soft);
    border:1px solid rgba(91,42,163,.18);
    color:var(--bs-primary);
    padding:.35rem .7rem;
    border-radius:999px;
    font-weight:800;
    font-size:12px;
    white-space:nowrap;
  }

  .bs-btn{
    border-radius:12px;
    font-weight:900;
    letter-spacing:.2px;
    padding:.85rem 1rem;
  }
  .bs-btn-primary{ background:var(--bs-primary); border-color:var(--bs-primary); }
  .bs-btn-primary:hover{ filter:brightness(.95); }

  .bs-kpi{
    border-radius:14px;
    border:1px solid var(--bs-border);
    background:#fff;
    padding:14px 16px;
  }
  .bs-kpi .k{ font-size:12px; color:var(--bs-muted); font-weight:800; white-space:nowrap; }
  .bs-kpi .v{ font-size:18px; font-weight:900; color:var(--bs-ink); white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  .bs-kpi.good .v{ color:#065f46; }
  .bs-kpi.bad .v{ color:#b91c1c; }

  .bs-table thead th{ white-space:nowrap; }
  .bs-table td{ vertical-align:middle; }
  .bs-main{
    white-space:nowrap;
    overflow:hidden;
    text-overflow:ellipsis;
    max-width:520px;
    display:block;
  }
  .bs-badge{
    font-weight:900;
    letter-spacing:.2px;
    border-radius:999px;
    padding:.35rem .6rem;
    white-space:nowrap;
  }
</style>

<div class="row bs-wrap">

  {{-- FILTER CARD --}}
  <div class="col-12">
    <div class="card bs-card mb-4">
      <div class="card-header d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2">
          <h3 class="card-title m-0 bs-title">Balance Sheet</h3>
          <span class="bs-chip">
            <i class="nav-icon i-Financial"></i>
            {{ (($ctx['mode'] ?? '') === 'period')
                ? ($ctx['as_at_period'] ?? '')
                : (isset($ctx['as_at_date']) ? $ctx['as_at_date']->format('Y-m-d') : '') }}
          </span>
        </div>

        <div class="dropdown dropleft text-end">
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
          <div class="alert alert-warning mb-3">
            <ul class="mb-0">
              @foreach ($notices as $n)
                <li>{{ $n }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <form method="GET" action="{{ route('reports.final_accounts.balance_sheet') }}">
          <div class="row g-3 align-items-end">

            {{-- MODE --}}
            <div class="col-12 col-md-3">
              <label class="form-label bs-label mb-1">Mode</label>
              <select name="mode" class="form-control bs-select">
                <option value="period" {{ request('mode', $ctx['mode'] ?? 'period') == 'period' ? 'selected' : '' }}>As at Period</option>
                <option value="date" {{ request('mode', $ctx['mode'] ?? '') == 'date' ? 'selected' : '' }}>As at Date</option>
              </select>
              <div class="bs-subtle mt-1">Use Period for month-end.</div>
            </div>

            {{-- PERIOD --}}
            <div class="col-12 col-md-3">
              <label class="form-label bs-label mb-1">As at Period</label>
              <div class="input-group">
                <span class="input-group-text" style="border-radius:12px 0 0 12px; border:1px solid var(--bs-border); background:#fff; white-space:nowrap;">YYYYMM</span>
                <input
                  type="text"
                  name="as_at_period"
                  value="{{ request('as_at_period', $ctx['as_at_period'] ?? '') }}"
                  class="form-control bs-input"
                  inputmode="numeric"
                  maxlength="6"
                  pattern="\d{6}"
                  placeholder="202602"
                  oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,6)"
                  style="border-radius:0 12px 12px 0;"
                >
              </div>
              <div class="bs-subtle mt-1">Exactly 6 digits.</div>
            </div>

            {{-- DATE --}}
            <div class="col-12 col-md-3">
              <label class="form-label bs-label mb-1">As at Date</label>
              <div class="input-group">
                <span class="input-group-text" style="border-radius:12px 0 0 12px; border:1px solid var(--bs-border); background:#fff; white-space:nowrap;">
                  <i class="nav-icon i-Calendar-4"></i>
                </span>
                <input
                  type="date"
                  name="as_at_date"
                  value="{{ request('as_at_date', isset($ctx['as_at_date']) && $ctx['as_at_date'] ? $ctx['as_at_date']->format('Y-m-d') : '') }}"
                  class="form-control bs-input"
                  style="border-radius:0 12px 12px 0;"
                >
              </div>
              <div class="bs-subtle mt-1">End-of-day applied.</div>
            </div>

            {{-- RUN --}}
            <div class="col-12 col-md-3">
              <button class="btn bs-btn bs-btn-primary w-100 text-white" type="submit">
                <i class="nav-icon i-Search-People me-2"></i> Run Report
              </button>
              <div class="bs-subtle mt-1 text-center">Totals + lines.</div>
            </div>

          </div>
        </form>

        <hr class="my-4">

        @php
          $diff = (float) ($totals['diff'] ?? 0);
          $diffOk = (abs($diff) < 0.005);
        @endphp

        {{-- KPI SUMMARY --}}
        <div class="row g-3">
          <div class="col-12 col-md-3">
            <div class="bs-kpi">
              <div class="k">Total Assets</div>
              <div class="v">{{ number_format($totals['total_assets'] ?? 0, 2) }}</div>
            </div>
          </div>
          <div class="col-12 col-md-3">
            <div class="bs-kpi">
              <div class="k">Total Liabilities</div>
              <div class="v">{{ number_format($totals['total_liabilities'] ?? 0, 2) }}</div>
            </div>
          </div>
          <div class="col-12 col-md-3">
            <div class="bs-kpi">
              <div class="k">Total Capital</div>
              <div class="v">{{ number_format($totals['total_capital'] ?? 0, 2) }}</div>
            </div>
          </div>
          <div class="col-12 col-md-3">
            <div class="bs-kpi {{ $diffOk ? 'good' : 'bad' }}">
              <div class="k">Balance Check</div>
              <div class="v">{{ number_format($diff, 2) }}</div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>

  {{-- TABLE CARD --}}
  <div class="col-12">
    <div class="card bs-card mb-4">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title m-0 bs-title">Balance Sheet Lines</h3>

        <div class="dropdown dropleft text-end">
          <button class="btn bg-gray-100" id="dropdownMenuButton_bs2" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="nav-icon i-Gear-2"></i>
          </button>
          <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_bs2">
            <a class="dropdown-item" href="{{ route('reports.final_accounts.balance_sheet.pdf', request()->query()) }}">Download PDF</a>
            <a class="dropdown-item" href="{{ route('reports.final_accounts.balance_sheet.excel', request()->query()) }}">Download Excel</a>
          </div>
        </div>
      </div>

      @php
        $rows = $rows ?? collect();

        $badgeClass = function ($g) {
          $g = strtoupper(trim((string) $g));
          return match ($g) {
            'ASSET' => 'bg-info',
            'LIABILITY' => 'bg-warning',
            'CAPITAL' => 'bg-dark',
            default => 'bg-secondary',
          };
        };
      @endphp

      <div class="card-body">
        <div class="table-responsive">
          <table class="table table-sm bs-table text-center">
            <thead>
              <tr>
                <th>#</th>
                <th>Group</th>
                <th class="text-start">Account</th>
                <th class="text-end">Debit</th>
                <th class="text-end">Credit</th>
                <th class="text-end">Balance</th>
              </tr>
            </thead>

            <tbody>
              @php $i=1; @endphp

              @forelse($rows as $r)
                @php
                  $g = strtoupper((string) ($r->main_group ?? ''));
                  $g = $g ?: 'OTHER';

                  $bal = (float) ($r->balance ?? 0);
                  $balSide = $bal >= 0 ? 'Dr' : 'Cr';
                @endphp

                <tr>
                  <td>{{ $i++ }}</td>

                  <td>
                    <span class="badge bs-badge {{ $badgeClass($g) }}">{{ $g }}</span>
                  </td>

                  {{-- Sub account (preferred) with fallback to main account --}}
                  <td class="text-start">
                    <div class="fw-bold bs-main">
                      @if(!empty($r->sub_account_name))
                       {{ $r->sub_account_name ?? '' }}
                      @else
                        {{ $r->main_account_name ?? '' }}
                      @endif
                    </div>

                    @if(!empty($r->sub_account_name))
                      <div class="bs-subtle">
                         {{ $r->main_account_name ?? '' }}
                      </div>
                    @endif
                  </td>

                  <td class="text-end" style="white-space:nowrap;">{{ number_format($r->debit ?? 0, 2) }}</td>
                  <td class="text-end" style="white-space:nowrap;">{{ number_format($r->credit ?? 0, 2) }}</td>

                  <td class="text-end" style="white-space:nowrap;">
                    {{ number_format(abs($bal), 2) }}
                    <span class="text-muted" style="font-size:12px;">{{ $balSide }}</span>
                  </td>
                </tr>

              @empty
                <tr>
                  <td colspan="6" class="text-muted">No records found for the selected cutoff.</td>
                </tr>
              @endforelse
            </tbody>

            @if($rows->count() > 0)
              <tfoot>
                <tr class="fw-bold table-secondary">
                  <td colspan="5" class="text-end">TOTAL ASSETS</td>
                  <td class="text-end" style="white-space:nowrap;">{{ number_format($totals['total_assets'] ?? 0, 2) }}</td>
                </tr>
                <tr class="fw-bold table-secondary">
                  <td colspan="5" class="text-end">TOTAL LIABILITIES</td>
                  <td class="text-end" style="white-space:nowrap;">{{ number_format($totals['total_liabilities'] ?? 0, 2) }}</td>
                </tr>
                <tr class="fw-bold table-secondary">
                  <td colspan="5" class="text-end">TOTAL CAPITAL</td>
                  <td class="text-end" style="white-space:nowrap;">{{ number_format($totals['total_capital'] ?? 0, 2) }}</td>
                </tr>
                <tr class="fw-bold table-secondary">
                  <td colspan="5" class="text-end">LIABILITIES + CAPITAL</td>
                  <td class="text-end" style="white-space:nowrap;">{{ number_format($totals['liabilities_plus_capital'] ?? 0, 2) }}</td>
                </tr>
                <tr class="fw-bold table-secondary">
                  <td colspan="5" class="text-end">BALANCE CHECK</td>
                  <td class="text-end {{ $diffOk ? 'text-success' : 'text-danger' }}" style="white-space:nowrap;">
                    {{ number_format($diff, 2) }}
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
