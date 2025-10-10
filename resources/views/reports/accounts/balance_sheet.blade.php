@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-md-12">
    <div class="card o-hidden mb-4 shadow-sm">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="m-0">Balance Sheet</h3>
      </div>

      <div class="card-body">
        @include('includes.accounts_nav')
        <!-- 🔍 Filters -->
        <form method="GET" action="{{ route('reports.accounts.balance-sheet') }}" class="row g-3 mb-4">
          <div class="col-md-3">
            <label for="period" class="form-label">Period (YYYYmm)</label>
            <input type="text" name="period" id="period" class="form-control"
              value="{{ old('period', $period ?? '') }}" maxlength="6" pattern="\d{6}" placeholder="e.g. 202510">
          </div>
          <div class="col-md-3">
            <label for="date_from" class="form-label">From Date</label>
            <input type="date" name="date_from" id="date_from" class="form-control"
              value="{{ old('date_from', $dateFrom ?? now()->startOfMonth()->format('Y-m-d')) }}">
          </div>
          <div class="col-md-3">
            <label for="date_to" class="form-label">To Date</label>
            <input type="date" name="date_to" id="date_to" class="form-control"
              value="{{ old('date_to', $dateTo ?? now()->endOfMonth()->format('Y-m-d')) }}">
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-primary w-100">Generate</button>
          </div>
        </form>

        <!-- 🧾 Period summary -->
        <p class="text-muted small">
          <strong>Period:</strong> {{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }} – {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}
        </p>

        <!-- 📊 Balance Sheet Table -->
        <div class="table-responsive">
          <table class="table table-bordered align-middle">
            <thead class="table-light text-center">
              <tr>
                <th style="width:45%">ASSETS</th>
                <th style="width:15%">KES</th>
                <th style="width:30%">LIABILITIES & CAPITAL</th>
                <th style="width:10%">KES</th>
              </tr>
            </thead>
            <tbody>
              @php
                $maxRows = max($assets->count(), $liabilities->count() + $capital->count());
                $rightSide = $liabilities->concat($capital)->values();
              @endphp

              {{-- Assets vs Liabilities + Capital --}}
              @for($i=0; $i < $maxRows; $i++)
              <tr>
                <td>
                  {{ $assets[$i]->sub_account_name ?? '' }}
                  @if(!empty($assets[$i]->sub_account_code))
                    <small class="text-muted">
                      ({{ $assets[$i]->main_account_code }}/{{ $assets[$i]->sub_account_code }})
                    </small>
                  @endif
                </td>
                <td class="text-end">
                  {{ isset($assets[$i]) ? number_format($assets[$i]->debit - $assets[$i]->credit, 2) : '0.00' }}
                </td>

                <td>
                  @if(isset($rightSide[$i]))
                    {{ $rightSide[$i]->main_account_type ?? '' }} — {{ $rightSide[$i]->sub_account_name }}
                    @if(!empty($rightSide[$i]->sub_account_code))
                      <small class="text-muted">
                        ({{ $rightSide[$i]->main_account_code }}/{{ $rightSide[$i]->sub_account_code }})
                      </small>
                    @endif
                  @else
                    <em class="text-muted">—</em>
                  @endif
                </td>
                <td class="text-end">
                  {{ isset($rightSide[$i]) ? number_format($rightSide[$i]->credit - $rightSide[$i]->debit, 2) : '0.00' }}
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
                    ⚠ Out of Balance by {{ number_format(abs($totalAssets - $totalRight),2) }}
                  @endif
                </td>
              </tr>
            </tfoot>
          </table>
        </div>

        @if($netProfit != 0)
        <div class="alert alert-info mt-3 text-center">
          <strong>Note:</strong>
          Net {{ $netProfit >= 0 ? 'Profit' : 'Loss' }} for this period is
          <strong>{{ number_format(abs($netProfit), 2) }} KES</strong>
        </div>
        @endif
      </div>
    </div>
  </div>
</div>
@endsection