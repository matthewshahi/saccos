@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-md-12">

    <div class="card shadow-sm mb-4">

      {{-- ================= HEADER ================= --}}
      <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="m-0">Balance Sheet</h3>
        @include('includes.accounts_nav')
      </div>

      <div class="card-body">

        {{-- ================= SESSION MESSAGES ================= --}}
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

        {{-- ================= FILTERS ================= --}}
        <form method="GET"
              action="{{ route('reports.accounts.balance-sheet') }}"
              class="row g-3 align-items-end mb-3">

          <div class="col-md-3">
            <label class="form-label fw-semibold">Period (YYYYMM)</label>
            <input type="text"
                   name="period"
                   class="form-control"
                   value="{{ request('period', $period ?? '') }}"
                   placeholder="e.g. 202601"
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

        {{-- ================= EXPORT BUTTONS ================= --}}
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

        {{-- ================= CONTEXT NOTE ================= --}}
        <div class="alert alert-secondary small mb-3">
          <strong>Presentation Note:</strong><br>
          This Balance Sheet is generated strictly from Trial Balance data recorded
          within the selected period. No opening balances or brought-forward figures
          are assumed. Amounts reflect net movements as positioned by account type.
        </div>

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
                <th width="30%">LIABILITIES &amp; CAPITAL</th>
                <th width="10%">KES</th>
              </tr>
            </thead>

            @php
              $rightSide = collect()
                ->concat($liabilities)
                ->concat($capital);

              $maxRows = max($assets->count(), $rightSide->count());
            @endphp

            <tbody>
              @for ($i = 0; $i < $maxRows; $i++)
                <tr>
                  {{-- ASSETS --}}
                  <td>{{ $assets[$i]->sub_account_name ?? '' }}</td>
                  <td class="text-end">
                    @if(isset($assets[$i]))
                      {{ number_format(
                        ($assets[$i]->debit ?? 0) > 0
                          ? $assets[$i]->debit
                          : $assets[$i]->credit,
                        2
                      ) }}
                    @endif
                  </td>

                  {{-- LIABILITIES & CAPITAL --}}
                  <td>{{ $rightSide[$i]->sub_account_name ?? '' }}</td>
                  <td class="text-end">
                    @if(isset($rightSide[$i]))
                      {{ number_format(
                        ($rightSide[$i]->credit ?? 0) > 0
                          ? $rightSide[$i]->credit
                          : $rightSide[$i]->debit,
                        2
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
            </tfoot>

          </table>
        </div>

      </div>
    </div>

  </div>
</div>
@endsection
