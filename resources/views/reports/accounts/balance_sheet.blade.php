@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-md-12">
    <div class="card shadow-sm mb-4">

      {{-- HEADER --}}
      <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="m-0">Balance Sheet</h3>
        @include('includes.accounts_nav')
      </div>

      <div class="card-body">

        {{-- FLASH MESSAGES --}}
        @if(session('error'))
          <div class="alert alert-danger alert-dismissible fade show">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
          </div>
        @endif

        {{-- FILTERS --}}
        <form method="GET"
              action="{{ route('reports.accounts.balance-sheet') }}"
              class="row g-3 mb-3">

          <div class="col-md-3">
            <label class="form-label">Period (YYYYMM)</label>
            <input type="text"
                   name="period"
                   class="form-control"
                   maxlength="6"
                   placeholder="e.g. 202601"
                   value="{{ request('period') }}">
          </div>

          <div class="col-md-3">
            <label class="form-label">From Date</label>
            <input type="date"
                   name="date_from"
                   class="form-control"
                   value="{{ request('date_from', $dateFrom ?? '') }}">
          </div>

          <div class="col-md-3">
            <label class="form-label">To Date</label>
            <input type="date"
                   name="date_to"
                   class="form-control"
                   value="{{ request('date_to', $dateTo ?? '') }}">
          </div>

          <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-primary w-100">
              Generate
            </button>
          </div>
        </form>

        {{-- EXPORT BUTTONS --}}
        <div class="d-flex gap-2 mb-3">
          <a href="{{ route('reports.accounts.balance-sheet.excel', request()->query()) }}"
             class="btn btn-success btn-sm">
            Export Excel
          </a>

          <a href="{{ route('reports.accounts.balance-sheet.pdf', request()->query()) }}"
             class="btn btn-danger btn-sm">
            Export PDF
          </a>
        </div>

        {{-- PRESENTATION NOTE --}}
        <div class="alert alert-secondary small mb-3">
          <strong>Presentation Note:</strong>
          This Balance Sheet treats the selected reporting period as a complete accounting universe.
          No opening balances, closing balances, or brought-forward figures are implied.
          All values are derived strictly from Trial Balance data recorded within the selected period.
        </div>

        {{-- PERIOD LABEL --}}
        <p class="text-muted small mb-3">
          <strong>Period:</strong>
          {{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }}
          –
          {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}
        </p>

        {{-- BALANCE SHEET TABLE --}}
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
                $rightSide = collect()
                  ->concat($liabilities)
                  ->concat($capital);

                if ($periodResult != 0) {
                  $rightSide->push((object)[
                    'label'  => $periodResult > 0 ? 'Period Profit' : 'Period Loss',
                    'amount' => abs($periodResult),
                  ]);
                }

                $maxRows = max($assets->count(), $rightSide->count());
              @endphp

              @for ($i = 0; $i < $maxRows; $i++)
                <tr>
                  {{-- ASSETS --}}
                  <td>
                    {{ $assets[$i]->sub_account_name ?? '' }}
                  </td>
                  <td class="text-end">
                    {{ isset($assets[$i]) ? number_format($assets[$i]->debit, 2) : '' }}
                  </td>

                  {{-- RIGHT SIDE --}}
                  <td>
                    {{ $rightSide[$i]->sub_account_name
                        ?? $rightSide[$i]->label
                        ?? '' }}
                  </td>
                  <td class="text-end">
                    {{ isset($rightSide[$i]->credit)
                        ? number_format($rightSide[$i]->credit, 2)
                        : (isset($rightSide[$i]->amount)
                            ? number_format($rightSide[$i]->amount, 2)
                            : '') }}
                  </td>
                </tr>
              @endfor
            </tbody>

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

      </div>
    </div>
  </div>
</div>
@endsection
