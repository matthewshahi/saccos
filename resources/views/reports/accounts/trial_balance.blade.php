@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-md-12">
    <div class="card o-hidden mb-4">

      <div class="card-header d-flex align-items-center">
        <h3 class="w-50 float-start card-title m-0">Trial Balance</h3>
      </div>

      <div class="card-body">

        {{-- ✅ Session messages --}}
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

        {{-- 🔍 Filter Form --}}
        <form method="GET" action="{{ route('reports.accounts.trial-balance') }}" class="row g-3 mb-4">

          <div class="col-md-3">
            <label class="form-label">Period (YYYYmm)</label>
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

        {{-- 📊 Trial Balance Table --}}
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
                    $grossDebit  = $rec->debit ?? 0;
                    $grossCredit = $rec->credit ?? 0;

                    // NETTING LOGIC (core fix)
                    $netDebit  = 0;
                    $netCredit = 0;

                    if ($grossDebit > $grossCredit) {
                      $netDebit = $grossDebit - $grossCredit;
                    } elseif ($grossCredit > $grossDebit) {
                      $netCredit = $grossCredit - $grossDebit;
                    } else {
                      continue; // skip zero balance
                    }

                    $fullCode = trim($rec->main_account_code).'/'.trim($rec->sub_account_code);

                    $typeDebit  += $netDebit;
                    $typeCredit += $netCredit;
                    $grandDebit += $netDebit;
                    $grandCredit+= $netCredit;
                  @endphp

                  <tr>
                    <td class="text-center">{{ $i++ }}</td>
                    <td class="text-start">{{ trim($rec->sub_account_name) }}</td>
                    <td class="text-center">{{ $fullCode }}</td>
                    <td class="text-end">{{ $netDebit > 0 ? number_format($netDebit,2) : '' }}</td>
                    <td class="text-end">{{ $netCredit > 0 ? number_format($netCredit,2) : '' }}</td>
                  </tr>
                @endforeach

                {{-- Subtotal --}}
               @php
  $netTypeBalance = $typeDebit - $typeCredit;
  $subDebit  = $netTypeBalance > 0 ? $netTypeBalance : 0;
  $subCredit = $netTypeBalance < 0 ? abs($netTypeBalance) : 0;
@endphp

<tr class="fw-bold table-light">
  <td colspan="3" class="text-end">
    Subtotal {{ strtoupper(trim($type)) }}
  </td>
  <td class="text-end">
    {{ $subDebit > 0 ? number_format($subDebit, 2) : '' }}
  </td>
  <td class="text-end">
    {{ $subCredit > 0 ? number_format($subCredit, 2) : '' }}
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
                <td class="text-end">{{ number_format($grandDebit,2) }}</td>
                <td class="text-end">{{ number_format($grandCredit,2) }}</td>
              </tr>
              <tr>
                <td colspan="5" class="text-center">
                  @if($grandDebit == $grandCredit)
                    ✅ Trial Balance Balances
                  @else
                    ⚠ Out of Balance by {{ number_format(abs($grandDebit - $grandCredit),2) }}
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
