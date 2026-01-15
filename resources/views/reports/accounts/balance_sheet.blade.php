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

        {{-- ========================= --}}
        {{-- FILTERS + EXPORT BUTTONS --}}
        {{-- ========================= --}}
        <form method="GET"
              action="{{ route('reports.accounts.balance-sheet') }}"
              class="row g-3 align-items-end mb-3">

          <div class="col-md-3">
            <label class="form-label fw-semibold">Period (YYYYMM)</label>
            <input type="text"
                   name="period"
                   class="form-control"
                   value="{{ request('period', $period ?? '') }}"
                   placeholder="e.g. 202510"
                   maxlength="6">
          </div>

          <div class="col-md-3">
            <label class="form-label fw-semibold">From Date</label>
            <input type="date"
                   name="date_from"
                   class="form-control"
                   value="{{ request('date_from', $dateFrom ?? '') }}">
          </div>

          <div class="col-md-3">
            <label class="form-label fw-semibold">To Date</label>
            <input type="date"
                   name="date_to"
                   class="form-control"
                   value="{{ request('date_to', $dateTo ?? '') }}">
          </div>

          <div class="col-md-3 d-flex gap-2">
            <button class="btn btn-primary w-100">Generate</button>
          </div>

        </form>

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

        {{-- ================= --}}
        {{-- PRESENTATION NOTE --}}
        {{-- ================= --}}
        <div class="alert alert-secondary small mb-3">
          <strong>Presentation Note:</strong><br>
          This Balance Sheet treats the selected reporting period as a complete accounting universe.
          No opening balances, closing balances, or brought-forward figures are implied.
          All values are derived strictly from Trial Balance data recorded within the selected period.
        </div>

        <p class="text-muted small mb-3">
          <strong>Period:</strong>
          {{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }}
          –
          {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}
        </p>

        {{-- ================= --}}
        {{-- BALANCE SHEET TABLE --}}
        {{-- ================= --}}
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
                // Right side = Liabilities + Capital + Explicit Period Result
                $rightSide = collect()
                  ->concat($liabilities)
                  ->concat($capital);

                if ((float)$periodResult !== 0.0) {
                  $rightSide->push((object)[
                    'sub_account_name' =>
                      $periodResult > 0 ? 'Period Profit' : 'Period Loss',
                    'credit' => $periodResult > 0 ? abs($periodResult) : 0,
                    'debit'  => $periodResult < 0 ? abs($periodResult) : 0,
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
                    {{ isset($assets[$i])
                        ? number_format((float)$assets[$i]->debit, 2)
                        : '' }}
                  </td>

                  {{-- RIGHT SIDE --}}
                  <td>
                    {{ $rightSide[$i]->sub_account_name ?? '' }}
                  </td>
                  <td class="text-end">
                    @if(isset($rightSide[$i]))
                      {{ number_format(
                          (float)(
                            ($rightSide[$i]->credit ?? 0)
                            ?: ($rightSide[$i]->debit ?? 0)
                          ), 2
                        ) }}
                    @endif
                  </td>
                </tr>
              @endfor
            </tbody>

            <tfoot class="table-dark fw-bold">
              <tr>
                <td class="text-end">Total Assets</td>
                <td class="text-end">{{ number_format($totalAssets, 2) }}</td>
                <td class="text-end">
                  Total Liabilities + Capital + Period Result
                </td>
                <td class="text-end">{{ number_format($totalRight, 2) }}</td>
              </tr>

              <tr>
                <td colspan="4" class="text-center">
                  @if(round($totalAssets, 2) === round($totalRight, 2))
                    <span class="text-success fw-bold">
                      ✔ Balance Sheet Balances
                    </span>
                  @else
                    <span class="text-danger fw-bold">
                      ✖ Out of Balance by
                      {{ number_format(abs($totalAssets - $totalRight), 2) }}
                    </span>
                  @endif
                </td>
              </tr>
            </tfoot>

          </table>
        </div>

        {{-- ================= --}}
        {{-- EXPLICIT RESULT NOTE --}}
        {{-- ================= --}}
        @if((float)$periodResult !== 0.0)
          <div class="alert alert-info text-center mt-3">
            <strong>
              Period {{ $periodResult > 0 ? 'Profit' : 'Loss' }}:
            </strong>
            {{ number_format(abs($periodResult), 2) }} KES<br>
            <small class="text-muted">
              Displayed explicitly and not absorbed into Capital or Liabilities.
            </small>
          </div>
        @endif

      </div>
    </div>
  </div>
</div>
@endsection
