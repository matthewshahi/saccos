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
        <form method="GET" action="{{ route('reports.accounts.balance-sheet') }}" class="row g-3 mb-4">
          <div class="col-md-3">
            <label class="form-label">Period (YYYYMM)</label>
            <input type="text" name="period" class="form-control"
                   value="{{ old('period', $period ?? '') }}"
                   maxlength="6" pattern="\d{6}">
          </div>

          <div class="col-md-3">
            <label class="form-label">From Date</label>
            <input type="date" name="date_from" class="form-control"
                   value="{{ $dateFrom }}">
          </div>

          <div class="col-md-3">
            <label class="form-label">To Date</label>
            <input type="date" name="date_to" class="form-control"
                   value="{{ $dateTo }}">
          </div>

          <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-primary w-100">Generate</button>
          </div>
        </form>

        {{-- Exports --}}
        <div class="d-flex gap-2 mb-3">
          <a href="{{ route('reports.accounts.balance-sheet.excel', request()->query()) }}"
             class="btn btn-success btn-sm">Export Excel</a>
          <a href="{{ route('reports.accounts.balance-sheet.pdf', request()->query()) }}"
             class="btn btn-danger btn-sm">Export PDF</a>
        </div>

        {{-- Presentation Note --}}
        <div class="alert alert-secondary small">
          <strong>Presentation note:</strong>
          This Balance Sheet assumes the selected period represents the
          <em>entire accounting universe</em>.
          No opening or closing balances are implied.
        </div>

        {{-- Balance Sheet --}}
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
                $maxRows = max($assets->count(), $rightSide->count());
              @endphp

              @for($i = 0; $i < $maxRows; $i++)
                <tr>
                  {{-- Assets --}}
                  <td>
                    {{ $assets[$i]->sub_account_name ?? '' }}
                  </td>
                  <td class="text-end">
                    {{ isset($assets[$i]) ? number_format($assets[$i]->debit, 2) : '' }}
                  </td>

                  {{-- Liabilities + Capital --}}
                  <td>
                    {{ $rightSide[$i]->sub_account_name ?? '' }} {{-- FIX: no type prefix --}}
                  </td>
                  <td class="text-end">
                    {{ isset($rightSide[$i]) ? number_format($rightSide[$i]->credit, 2) : '' }}
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
            </tfoot>
          </table>
        </div>

        {{-- Period Result --}}
        @if(isset($periodResult))
          <div class="alert alert-info mt-3 text-center">
            Period {{ $periodResult >= 0 ? 'Profit' : 'Loss' }}:
            <strong>{{ number_format(abs($periodResult), 2) }} KES</strong>
          </div>
        @endif

      </div>
    </div>
  </div>
</div>
@endsection
