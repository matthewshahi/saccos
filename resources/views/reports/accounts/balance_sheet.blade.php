@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-md-12">
    <div class="card o-hidden mb-4 shadow-sm">

      <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="m-0">Balance Sheet</h3>
      </div>

      <div class="card-body">

        {{-- Session messages --}}
        @if(session('error'))
          <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        @endif

        @include('includes.accounts_nav')

        {{-- Filters --}}
        <form method="GET"
              action="{{ route('reports.accounts.balance-sheet') }}"
              class="row g-3 mb-4">

          <div class="col-md-3">
            <label class="form-label">Period (YYYYMM)</label>
            <input type="text"
                   name="period"
                   class="form-control"
                   value="{{ request('period', $period ?? '') }}"
                   maxlength="6"
                   pattern="\d{6}">
          </div>

          <div class="col-md-3">
            <label class="form-label">From Date</label>
            <input type="date"
                   name="date_from"
                   class="form-control"
                   value="{{ request('date_from', $dateFrom) }}">
          </div>

          <div class="col-md-3">
            <label class="form-label">To Date</label>
            <input type="date"
                   name="date_to"
                   class="form-control"
                   value="{{ request('date_to', $dateTo) }}">
          </div>

          <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-primary w-100">Generate</button>
          </div>
        </form>

        {{-- Export buttons --}}
        <div class="d-flex gap-2 mb-3">
          <a href="{{ route('reports.accounts.balance-sheet.excel', request()->query()) }}"
             class="btn btn-success btn-sm">Export Excel</a>

          <a href="{{ route('reports.accounts.balance-sheet.pdf', request()->query()) }}"
             class="btn btn-danger btn-sm">Export PDF</a>
        </div>

        {{-- Period note --}}
        <p class="text-muted small mb-3">
          <strong>As at:</strong>
          {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}<br>
          <strong>Period:</strong>
          {{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }}
          –
          {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}
        </p>

        {{-- Balance Sheet Table --}}
        <div class="table-responsive">
          <table class="table table-bordered align-middle">
            <thead class="table-light text-center">
              <tr>
                <th width="45%">ASSETS</th>
                <th width="15%">KES</th>
                <th width="30%">LIABILITIES &amp; CAPITAL</th>
                <th width="10%">KES</th>
              </tr>
            </thead>

            <tbody>
              @php
                $rightSide = $liabilities->concat($capital)->values();
                $rows = max($assets->count(), $rightSide->count());
              @endphp

              @for($i = 0; $i < $rows; $i++)
                <tr>
                  {{-- Assets --}}
                  <td>
                    {{ $assets[$i]->sub_account_name ?? '' }}
                    @isset($assets[$i]->sub_account_code)
                      <small class="text-muted">
                        ({{ $assets[$i]->main_account_code }}/{{ $assets[$i]->sub_account_code }})
                      </small>
                    @endisset
                  </td>
                  <td class="text-end">
                    {{ isset($assets[$i]) ? number_format($assets[$i]->debit ?? 0, 2) : '0.00' }}
                  </td>

                  {{-- Liabilities & Capital --}}
                  <td>
                    @isset($rightSide[$i])
                      {{ strtoupper($rightSide[$i]->main_account_type) }}
                      — {{ $rightSide[$i]->sub_account_name }}
                      @isset($rightSide[$i]->sub_account_code)
                        <small class="text-muted">
                          ({{ $rightSide[$i]->main_account_code }}/{{ $rightSide[$i]->sub_account_code }})
                        </small>
                      @endisset
                    @else
                      <span class="text-muted">—</span>
                    @endisset
                  </td>
                  <td class="text-end">
                    {{ isset($rightSide[$i]) ? number_format($rightSide[$i]->credit ?? 0, 2) : '0.00' }}
                  </td>
                </tr>
              @endfor
            </tbody>

            <tfoot class="table-dark fw-bold">
              <tr>
                <td class="text-end">Total Assets</td>
                <td class="text-end">{{ number_format($totalAssets, 2) }}</td>
                <td class="text-end">Total Liabilities + Capital</td>
                <td class="text-end">{{ number_format($totalRight, 2) }}</td>
              </tr>
              <tr>
                <td colspan="4" class="text-center">
                  @if(round($totalAssets,2) === round($totalRight,2))
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

      </div>
    </div>
  </div>
</div>
@endsection
