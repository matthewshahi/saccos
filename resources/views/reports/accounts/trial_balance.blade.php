@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-md-12">
    <div class="card o-hidden mb-4">
      <div class="card-header d-flex align-items-center">
        <h3 class="w-50 float-start card-title m-0">Trial Balance</h3>
      </div>
      <div class="card-body">
        
        <!-- 🔍 Filter Form -->
        <form method="GET" action="{{ route('reports.accounts.trial-balance') }}" class="row g-3 mb-4">
          <div class="col-md-3">
            <label for="period" class="form-label">Period (YYYYmm)</label>
            <input type="text" name="period" id="period" value="{{ $period ?? '' }}" 
                   class="form-control" maxlength="6" pattern="\d{6}" 
                   placeholder="e.g. 202509">
          </div>
          <div class="col-md-3">
            <label for="date_from" class="form-label">From Date</label>
            <input type="date" name="date_from" id="date_from" class="form-control" value="{{ $dateFrom ?? '' }}">
          </div>
          <div class="col-md-3">
            <label for="date_to" class="form-label">To Date</label>
            <input type="date" name="date_to" id="date_to" class="form-control" value="{{ $dateTo ?? '' }}">
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <button type="submit" class="btn btn-primary w-100">Filter</button>
          </div>
        </form>

        <div class="table-responsive">
          <table class="table table-bordered">
            <thead class="table-light text-center">
              <tr>
                <th style="width:5%">#</th>
                <th style="width:35%; text-align:left;">Sub Account</th>
                <th style="width:20%">Code</th>
                <th style="width:20%; text-align:right;">Debit</th>
                <th style="width:20%; text-align:right;">Credit</th>
              </tr>
            </thead>
            <tbody>
  @php
    $grandDebit = 0; 
    $grandCredit = 0; 
    $i = 1;
    $grouped = $records->groupBy('main_account_type');
  @endphp

  @forelse($grouped as $type => $accounts)
    {{-- Group Header --}}
    <tr class="table-secondary fw-bold">
      <td colspan="5" class="text-start">{{ strtoupper(trim($type)) }}</td>
    </tr>

    @php $typeDebit = 0; $typeCredit = 0; @endphp
    @foreach($accounts as $rec)
      @php
        $debit = $rec->debit ?? 0;
        $credit = $rec->credit ?? 0;
        if ($debit == 0 && $credit == 0) continue;
        $fullCode = trim($rec->main_account_code) . '/' . trim($rec->sub_account_code);
        $typeDebit += $debit;
        $typeCredit += $credit;
        $grandDebit += $debit;
        $grandCredit += $credit;
      @endphp
      <tr>
        <td class="text-center">{{ $i++ }}</td>
        <td class="text-start">{{ trim($rec->sub_account_name) }}</td>
        <td class="text-center">{{ $fullCode }}</td>
        <td class="text-end">{{ $debit > 0 ? number_format($debit,2) : '' }}</td>
        <td class="text-end">{{ $credit > 0 ? number_format($credit,2) : '' }}</td>
      </tr>
    @endforeach

    {{-- Subtotal --}}
    <tr class="fw-bold table-light">
      <td colspan="3" class="text-end">Subtotal {{ strtoupper(trim($type)) }}</td>
      <td class="text-end">{{ number_format($typeDebit,2) }}</td>
      <td class="text-end">{{ number_format($typeCredit,2) }}</td>
    </tr>
  @empty
    <tr><td colspan="5" class="text-center">No transactions found for selected filters</td></tr>
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