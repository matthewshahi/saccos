@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-md-12">
    <div class="card o-hidden mb-4">

      <div class="card-header d-flex align-items-center">
        <h3 class="w-50 float-start card-title m-0">Trial Balance</h3>
      </div>

      <div class="card-body">

        {{-- Session messages --}}
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

        {{-- Navigation --}}
        @include('includes.accounts_nav')

        {{-- Export buttons --}}
        <div class="d-flex gap-2 mb-3">
          <a href="{{ route('reports.accounts.trial-balance.excel', request()->query()) }}"
             class="btn btn-success btn-sm">
            Export Excel
          </a>

          <a href="{{ route('reports.accounts.trial-balance.pdf', request()->query()) }}"
             class="btn btn-danger btn-sm">
            Export PDF
          </a>
        </div>

        {{-- Filter Form --}}
        <form method="GET" action="{{ route('reports.accounts.trial-balance') }}" class="row g-3 mb-4">

          <div class="col-md-3">
            <label class="form-label">Period (YYYYMM)</label>
            <input type="text"
                   name="period"
                   value="{{ old('period', $period ?? '') }}"
                   class="form-control"
                   maxlength="6"
                   pattern="\d{6}"
                   placeholder="e.g. 202510">
          </div>

          <div class="col-md-3">
            <label class="form-label">From Date</label>
            <input type="date"
                   name="date_from"
                   class="form-control"
                   value="{{ old('date_from', $dateFrom ?? '') }}">
          </div>

          <div class="col-md-3">
            <label class="form-label">To Date</label>
            <input type="date"
                   name="date_to"
                   class="form-control"
                   value="{{ old('date_to', $dateTo ?? '') }}">
          </div>

          <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-primary w-100">Filter</button>
          </div>

        </form>

        {{-- Trial Balance Table --}}
        <div class="table-responsive">
          <table class="table table-bordered">

            <thead class="table-light text-center">
              <tr>
                <th width="5%">#</th>
                <th width="35%" class="text-start">Sub Account</th>
                <th width="20%">Code</th>
                <th width="20%" class="text-end">Debit</th>
                <th width="20%" class="text-end">Credit</th>
              </tr>
            </thead>

            <tbody>
              @php
                $grandDebit  = 0;
                $grandCredit = 0;
                $i = 1;

                // Group by account type for sectioning
                $grouped = $records->groupBy('main_account_type');
              @endphp

              @forelse($grouped as $type => $accounts)

                {{-- Section Header --}}
                <tr class="table-secondary fw-bold">
                  <td colspan="5">{{ strtoupper(trim($type)) }}</td>
                </tr>

                @php
                  $typeDebit  = 0;
                  $typeCredit = 0;
                @endphp

                @foreach($accounts as $rec)
                  @php
                    // Values are already NET from controller
                    $netDebit  = (float) ($rec->debit ?? 0);
                    $netCredit = (float) ($rec->credit ?? 0);

                    if ($netDebit == 0 && $netCredit == 0) {
                      continue;
                    }

                    $fullCode = trim($rec->main_account_code) . '/' . trim($rec->sub_account_code);

                    $typeDebit   += $netDebit;
                    $typeCredit  += $netCredit;
                    $grandDebit  += $netDebit;
                    $grandCredit += $netCredit;
                  @endphp

                  <tr>
                    <td class="text-center">{{ $i++ }}</td>
                    <td class="text-start">{{ trim($rec->sub_account_name) }}</td>
                    <td class="text-center">{{ $fullCode }}</td>
                    <td class="text-end">{{ $netDebit > 0 ? number_format($netDebit, 2) : '' }}</td>
                    <td class="text-end">{{ $netCredit > 0 ? number_format($netCredit, 2) : '' }}</td>
                  </tr>
                @endforeach

                {{-- Subtotal (true Trial Balance subtotal) --}}
                <tr class="fw-bold table-light">
                  <td colspan="3" class="text-end">
                    Subtotal {{ strtoupper(trim($type)) }}
                  </td>
                  <td class="text-end">
                    {{ $typeDebit > 0 ? number_format($typeDebit, 2) : '' }}
                  </td>
                  <td class="text-end">
                    {{ $typeCredit > 0 ? number_format($typeCredit, 2) : '' }}
                  </td>
                </tr>

              @empty
                <tr>
                  <td colspan="5" class="text-center">No transactions found</td>
                </tr>
              @endforelse
            </tbody>

            <tfoot class="table-dark fw-bold">
              <tr>
                <td colspan="3" class="text-end">Grand Totals</td>
                <td class="text-end">{{ number_format($grandDebit, 2) }}</td>
                <td class="text-end">{{ number_format($grandCredit, 2) }}</td>
              </tr>
              <tr>
                <td colspan="5" class="text-center">
                  @if(abs($grandDebit - $grandCredit) < 0.01)
                    ✅ Trial Balance Balances
                  @else
                    ⚠ Out of Balance by {{ number_format(abs($grandDebit - $grandCredit), 2) }}
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
