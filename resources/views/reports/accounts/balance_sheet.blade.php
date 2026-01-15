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
        {{-- FILTERS --}}
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
                   maxlength="6"
                   placeholder="e.g. 202510">
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

          <div class="col-md-3">
            <button class="btn btn-primary w-100">Generate</button>
          </div>
        </form>

        {{-- EXPORTS --}}
        <div class="d-flex gap-2 mb-3">
          <a href="{{ route('reports.accounts.balance-sheet.excel', request()->query()) }}"
             class="btn btn-success btn-sm">Export Excel</a>

          <a href="{{ route('reports.accounts.balance-sheet.pdf', request()->query()) }}"
             class="btn btn-danger btn-sm">Export PDF</a>
        </div>

        {{-- PRESENTATION NOTE --}}
        <div class="alert alert-secondary small mb-3">
          <strong>Presentation Note:</strong><br>
          This Balance Sheet is derived strictly from Trial Balance data within the
          selected reporting period. No opening balances or brought-forward figures
          are implied.
        </div>

        <p class="text-muted small mb-3">
          <strong>Period:</strong>
          {{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }}
          –
          {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}
        </p>

        {{-- ================= --}}
        {{-- BALANCE SHEET --}}
        {{-- ================= --}}
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

            @php
              $rightSide = $liabilities->concat($capital);
              $maxRows   = max($assets->count(), $rightSide->count());
            @endphp

            <tbody>
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
                <td class="text-end">Total Liabilities &amp; Capital</td>
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

      </div>
    </div>
  </div>
</div>
@endsection
