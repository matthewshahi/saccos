@extends('layouts.app')

@section('content')
<div class="row">

  {{-- FILTER CARD --}}
  <div class="col-md-12">
    <div class="card o-hidden mb-4">
      <div class="card-header d-flex align-items-center">
        <h3 class="w-50 float-start card-title m-0">Trial Balance</h3>

        <div class="dropdown dropleft text-end w-50 float-end">
          <button class="btn bg-gray-100" id="dropdownMenuButton_tb" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="nav-icon i-Gear-2"></i>
          </button>
          <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_tb">
            <a class="dropdown-item" href="{{ route('reports.final_accounts.trial_balance.pdf', request()->query()) }}">Download PDF</a>
            <a class="dropdown-item" href="{{ route('reports.final_accounts.trial_balance.excel', request()->query()) }}">Download Excel</a>
          </div>
        </div>
      </div>

      <div class="card-body">
        @if (!empty($notices))
          <div class="alert alert-warning">
            <ul class="mb-0">
              @foreach ($notices as $n)
                <li>{{ $n }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <form method="GET" action="{{ route('reports.final_accounts.trial_balance') }}">
          <div class="row">

            <div class="col-md-3">
              <label class="form-label fw-bold">Mode</label>
              <select name="mode" class="form-control">
                <option value="period" {{ (request('mode','period')=='period') ? 'selected' : '' }}>As at Period (YYYYMM)</option>
                <option value="date" {{ (request('mode')=='date') ? 'selected' : '' }}>As at Date</option>
              </select>
              <small class="text-muted">Period is recommended for SACCO month-end.</small>
            </div>

            <div class="col-md-3">
              <label class="form-label fw-bold">As at Period</label>
              <input type="text" name="as_at_period"
                     value="{{ $ctx['as_at_period'] ?? request('as_at_period') }}"
                     class="form-control" placeholder="YYYYMM e.g. 202512">
              <small class="text-muted">Used when mode=period.</small>
            </div>

            <div class="col-md-3">
              <label class="form-label fw-bold">As at Date</label>
              <input type="date" name="as_at_date"
                     value="{{ isset($ctx['as_at_date']) && $ctx['as_at_date'] ? $ctx['as_at_date']->format('Y-m-d') : request('as_at_date') }}"
                     class="form-control">
              <small class="text-muted">Used when mode=date. End-of-day is applied.</small>
            </div>

            <div class="col-md-3 d-flex align-items-end">
              <button class="btn btn-primary w-100" type="submit">
                <i class="nav-icon i-Search-People me-1"></i> Run Report
              </button>
            </div>

          </div>
        </form>

        <hr>

        {{-- SUMMARY --}}
        <div class="row">
          <div class="col-md-3">
            <div class="p-3 bg-light rounded">
              <div class="text-muted">Total Debit</div>
              <div class="fw-bold">{{ number_format($totals['debit'] ?? 0, 2) }}</div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="p-3 bg-light rounded">
              <div class="text-muted">Total Credit</div>
              <div class="fw-bold">{{ number_format($totals['credit'] ?? 0, 2) }}</div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="p-3 bg-light rounded">
              <div class="text-muted">Diff (Dr - Cr)</div>
              <div class="fw-bold {{ (($totals['diff'] ?? 0) == 0) ? 'text-success' : 'text-danger' }}">
                {{ number_format($totals['diff'] ?? 0, 2) }}
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="p-3 bg-light rounded">
              <div class="text-muted">Cutoff</div>
              <div class="fw-bold">
                @if(($ctx['mode'] ?? '') === 'period')
                  {{ $ctx['as_at_period'] ?? '' }}
                @else
                  {{ isset($ctx['as_at_date']) ? $ctx['as_at_date']->format('Y-m-d') : '' }}
                @endif
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>
  </div>


  {{-- TB TABLE CARD --}}
  <div class="col-md-12">
    <div class="card o-hidden mb-4">
      <div class="card-header d-flex align-items-center">
        <h3 class="w-50 float-start card-title m-0">Trial Balance Lines</h3>
        <div class="dropdown dropleft text-end w-50 float-end">
          <button class="btn bg-gray-100" id="dropdownMenuButton_tb2" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
            <i class="nav-icon i-Gear-2"></i>
          </button>
          <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_tb2">
            <a class="dropdown-item" href="{{ route('reports.final_accounts.trial_balance.pdf', request()->query()) }}">Download PDF</a>
            <a class="dropdown-item" href="{{ route('reports.final_accounts.trial_balance.excel', request()->query()) }}">Download Excel</a>
          </div>
        </div>
      </div>

      @php
        $groupOf = function($t){
          $t = strtoupper(trim((string)$t));
          if (str_starts_with($t, 'ASSET')) return 'ASSET';
          if (str_starts_with($t, 'LIABILIT')) return 'LIABILITY';
          if (str_starts_with($t, 'CAPITAL')) return 'CAPITAL';
          if (str_starts_with($t, 'INCOME')) return 'INCOME';
          if (str_starts_with($t, 'EXPENSE')) return 'EXPENSE';
          return 'OTHER';
        };

        $badgeClass = function($g){
          return match ($g) {
            'ASSET' => 'bg-info',
            'LIABILITY' => 'bg-warning',
            'CAPITAL' => 'bg-dark',
            'INCOME' => 'bg-success',
            'EXPENSE' => 'bg-danger',
            default => 'bg-secondary',
          };
        };
      @endphp

      <div class="card-body">
        <div class="table-responsive">
          <table class="table text-center table-sm">
            <thead>
              <tr>
                <th>#</th>
                <th>Group</th>
                <th>Main</th>
                <th>Main Type</th>
                <th>Sub</th>
                <th class="text-end">Debit</th>
                <th class="text-end">Credit</th>
                <th class="text-end">Closing Dr</th>
                <th class="text-end">Closing Cr</th>
              </tr>
            </thead>
            <tbody>
              @php $i=1; @endphp
              @forelse($rows as $r)
                @php $g = $groupOf($r->main_account_type); @endphp
                <tr>
                  <td>{{ $i++ }}</td>

                  <td>
                    <span class="badge {{ $badgeClass($g) }}">{{ $g }}</span>
                  </td>

                  <td class="text-start">
                    <div class="fw-bold">{{ $r->main_account_code }} - {{ $r->main_account_name }}</div>
                  </td>

                  <td>{{ $r->main_account_type }}</td>

                  <td class="text-start">
                    <div class="fw-bold">{{ $r->sub_account_code }} - {{ $r->sub_account_name }}</div>
                  </td>

                  <td class="text-end">{{ number_format($r->debit ?? 0, 2) }}</td>
                  <td class="text-end">{{ number_format($r->credit ?? 0, 2) }}</td>
                  <td class="text-end">{{ number_format($r->closing_debit ?? 0, 2) }}</td>
                  <td class="text-end">{{ number_format($r->closing_credit ?? 0, 2) }}</td>
                </tr>
              @empty
                <tr>
                  <td colspan="9" class="text-muted">No records found for the selected cutoff.</td>
                </tr>
              @endforelse
            </tbody>

            @if(!empty($rows) && count($rows) > 0)
            <tfoot>
              <tr class="fw-bold">
                <td colspan="5" class="text-end">TOTALS</td>
                <td class="text-end">{{ number_format($totals['debit'] ?? 0, 2) }}</td>
                <td class="text-end">{{ number_format($totals['credit'] ?? 0, 2) }}</td>
                <td class="text-end">{{ number_format($totals['closing_debit'] ?? 0, 2) }}</td>
                <td class="text-end">{{ number_format($totals['closing_credit'] ?? 0, 2) }}</td>
              </tr>
            </tfoot>
            @endif

          </table>
        </div>
      </div>
    </div>
  </div>

</div>
@endsection
