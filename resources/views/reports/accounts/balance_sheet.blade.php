@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-md-12">
    <div class="card o-hidden mb-4 shadow-sm">

      {{-- ================= HEADER ================= --}}
      <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="m-0">Balance Sheet</h3>
      </div>

      <div class="card-body">

        {{-- ================= FLASH MESSAGES ================= --}}
        @if(session('error'))
          <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        @endif

        @if(session('success'))
          <div class="alert alert-success alert-dismissible fade show">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        @endif

        @include('includes.accounts_nav')

        {{-- ================= FILTERS ================= --}}
        <form method="GET" action="{{ route('reports.accounts.balance-sheet') }}" class="row g-3 mb-4">
          <div class="col-md-3">
            <label class="form-label">Period (YYYYMM)</label>
            <input type="text"
                   name="period"
                   class="form-control"
                   value="{{ $period ?? '' }}"
                   maxlength="6"
                   placeholder="e.g. 202601">
          </div>

          <div class="col-md-3">
            <label class="form-label">From Date</label>
            <input type="date"
                   name="date_from"
                   class="form-control"
                   value="{{ $dateFrom }}">
          </div>

          <div class="col-md-3">
            <label class="form-label">To Date</label>
            <input type="date"
                   name="date_to"
                   class="form-control"
                   value="{{ $dateTo }}">
          </div>

          <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-primary w-100">Generate</button>
          </div>
        </form>

        {{-- ================= EXPORTS ================= --}}
        <div class="d-flex gap-2 mb-3">
          <a href="{{ route('reports.accounts.balance-sheet.excel', request()->query()) }}"
             class="btn btn-success btn-sm">Export Excel</a>

          <a href="{{ route('reports.accounts.balance-sheet.pdf', request()->query()) }}"
             class="btn btn-danger btn-sm">Export PDF</a>
        </div>

        {{-- ================= PRESENTATION NOTICE ================= --}}
        <div class="alert alert-secondary small">
          <strong>Presentation Note:</strong><br>
          This Balance Sheet assumes that the selected reporting period represents the
          <strong>entire accounting universe</strong>.
          No opening balances, closing balances, or brought-forward figures are implied.
          All values are derived strictly from Trial Balance data recorded within the selected period.
        </div>

        {{-- ================= PERIOD LABEL ================= --}}
        <p class="text-muted small mb-3">
          <strong>Period:</strong>
          {{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }}
          –
          {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}
        </p>

        {{-- ================= BALANCE SHEET TABLE ================= --}}
        <div class="table-responsive">
          <table class="table table-bordered align-middle">

            <thead class="table-light text-center">
              <tr>
                <th width="45%">ASSETS</th>
                <th width="15%">KES</th>
                <th width="30%">LIABILITIES, CAPITAL &amp; PERIOD RESULT</th>
                <th width="10%">KES</th>
              </tr>
            </thead>

            <tbody>
              @php
                $rightSide = $liabilities->concat($capital)->values();
                $maxRows = max($assets->count(), $rightSide->count());
              @endphp

              @for($i = 0; $i < $maxRows; $i++)
                <tr>
                  {{-- ASSETS --}}
                  <td>
                    {{ $assets[$i]->sub_account_name ?? '' }}
                    @if(!empty($assets[$i]->sub_account_code ?? null))
                      <small class="text-muted">
                        ({{ $assets[$i]->main_account_code }}/{{ $assets[$i]->sub_account_code }})
                      </small>
                    @endif
                  </td>

                  <td class="text-end">
                    {{ isset($assets[$i]) ? number_format(($assets[$i]->debit ?? 0), 2) : '' }}
                  </td>

                  {{-- RIGHT SIDE --}}
                  <td>
                    @if(isset($rightSide[$i]))
                      {{ $rightSide[$i]->sub_account_name }}
                      @if(!empty($rightSide[$i]->sub_account_code ?? null))
                        <small class="text-muted">
                          ({{ $rightSide[$i]->main_account_code }}/{{ $rightSide[$i]->sub_account_code }})
                        </small>
                      @endif
                    @endif
                  </td>

                  <td class="text-end">
                    {{ isset($rightSide[$i]) ? number_format(($rightSide[$i]->credit ?? 0), 2) : '' }}
                  </td>
                </tr>
              @endfor
            </tbody>

            {{-- ================= TOTALS ================= --}}
            <tfoot class="table-dark fw-bold">
              <tr>
                <td class="text-end">Total Assets</td>
                <td class="text-end">{{ number_format($totalAssets, 2) }}</td>
                <td class="text-end">Total Liabilities + Capital + Period Result</td>
                <td class="text-end">{{ number_format($totalRight, 2) }}</td>
              </tr>

              <tr>
                <td colspan="4" class="text-center">
                  @if(round($totalAssets, 2) === round($totalRight, 2))
                    ✅ Balance Sheet Balances
                  @else
                    ⚠ Out of Balance by
                    {{ number_format(abs($totalAssets - $totalRight), 2) }}
                  @endif
                </td>
              </tr>
            </tfoot>

          </table>
        </div>

        {{-- ================= PERIOD RESULT DISCLOSURE ================= --}}
        <div class="alert alert-info text-center mt-3">
          <strong>Period {{ $periodResult >= 0 ? 'Profit' : 'Loss' }}:</strong>
          {{ number_format(abs($periodResult), 2) }} KES
          <br>
          <small class="text-muted">
            Displayed explicitly and not absorbed into Capital or Liabilities.
          </small>
        </div>

      </div>
    </div>
  </div>
</div>
@endsection
