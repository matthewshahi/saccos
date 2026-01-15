@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-md-12">
    <div class="card shadow-sm mb-4">

      <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="m-0">Balance Sheet</h3>
        @include('includes.accounts_nav')
      </div>

      <div class="card-body">

        {{-- Presentation Note --}}
        <div class="alert alert-secondary small">
          <strong>Presentation Note:</strong>
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

        <div class="table-responsive">
          <table class="table table-bordered align-middle">
            <thead class="table-light text-center">
              <tr>
                <th width="45%">ASSETS</th>
                <th width="15%">KES</th>
                <th width="30%">LIABILITIES, CAPITAL & PERIOD RESULT</th>
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
                    'label' => $periodResult > 0 ? 'Period Profit' : 'Period Loss',
                    'amount' => abs($periodResult),
                  ]);
                }

                $maxRows = max($assets->count(), $rightSide->count());
              @endphp

              @for ($i = 0; $i < $maxRows; $i++)
                <tr>
                  {{-- Assets --}}
                  <td>
                    {{ $assets[$i]->sub_account_name ?? '' }}
                  </td>
                  <td class="text-end">
                    {{ isset($assets[$i]) ? number_format($assets[$i]->debit, 2) : '' }}
                  </td>

                  {{-- Right Side --}}
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
