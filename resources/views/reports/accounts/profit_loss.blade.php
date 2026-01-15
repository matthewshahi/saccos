@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-md-12">
    <div class="card o-hidden mb-4 shadow-sm">

      <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="m-0 fw-bold text-primary">Profit &amp; Loss Statement</h3>
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

 

        {{-- 🔍 FILTER FORM --}}
        <form method="GET" action="{{ route('reports.accounts.profit-loss') }}" class="row g-3 mb-4">
          <div class="col-md-3">
            <label class="form-label">Period (YYYYmm)</label>
            <input type="text" name="period" class="form-control"
              value="{{ old('period', $period ?? '') }}"
              maxlength="6" pattern="\d{6}" placeholder="e.g. 202510">
          </div>

          <div class="col-md-3">
            <label class="form-label">From Date</label>
            <input type="date" name="date_from" class="form-control"
              value="{{ old('date_from', $dateFrom ?? now()->startOfMonth()->format('Y-m-d')) }}">
          </div>

          <div class="col-md-3">
            <label class="form-label">To Date</label>
            <input type="date" name="date_to" class="form-control"
              value="{{ old('date_to', $dateTo ?? now()->endOfMonth()->format('Y-m-d')) }}">
          </div>

          <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-primary w-100">
              <i class="i-Magnifi-Glass1 me-2"></i>Generate
            </button>
          </div>
        </form>

<div class="alert alert-warning small">
  <strong>Note:</strong>
  This Profit &amp; Loss Statement reflects income and expenses
  recognised for the selected reporting period
  ({{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }}
  –
  {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}),
  based on posted ledger transactions.
</div>

        {{-- 📅 PERIOD SUMMARY --}}
        <p class="text-muted small mb-4">
          <strong>Period:</strong>
          {{ \Carbon\Carbon::parse($dateFrom)->format('d M Y') }}
          –
          {{ \Carbon\Carbon::parse($dateTo)->format('d M Y') }}
        </p>

        {{-- 📊 PROFIT & LOSS TABLE --}}
        <div class="table-responsive">
          <table class="table table-bordered table-hover align-middle">

            <thead class="table-secondary text-center fw-bold">
              <tr>
                <th colspan="2" class="bg-dark text-white">INCOME</th>
                <th colspan="2" class="bg-dark text-white">EXPENSES</th>
              </tr>
              <tr class="table-light">
                <th>Account</th>
                <th class="text-end">KES</th>
                <th>Account</th>
                <th class="text-end">KES</th>
              </tr>
            </thead>

            <tbody>
              @php
                $incomeRows   = $income->values();
                $expenseRows = $expenses->values();
                $maxRows = max($incomeRows->count(), $expenseRows->count());
              @endphp

              @for($i = 0; $i < $maxRows; $i++)
                @php
                  $inc = $incomeRows[$i] ?? null;
                  $exp = $expenseRows[$i] ?? null;

                  // Controller already gives raw debit & credit
                  // Net safely here for display
                  $incomeAmount  = $inc ? max(0, ($inc->credit ?? 0) - ($inc->debit ?? 0)) : 0;
                  $expenseAmount = $exp ? max(0, ($exp->debit ?? 0) - ($exp->credit ?? 0)) : 0;
                @endphp

                <tr>
                  {{-- INCOME --}}
                  <td>
                    @if($inc)
                      <strong>{{ strtoupper($inc->sub_account_name) }}</strong><br>
                      <small class="text-muted">
                        {{ $inc->main_account_code }}/{{ $inc->sub_account_code }}
                      </small>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                  <td class="text-end fw-semibold">
                    {{ $incomeAmount > 0 ? number_format($incomeAmount, 2) : '' }}
                  </td>

                  {{-- EXPENSE --}}
                  <td>
                    @if($exp)
                      <strong>{{ strtoupper($exp->sub_account_name) }}</strong><br>
                      <small class="text-muted">
                        {{ $exp->main_account_code }}/{{ $exp->sub_account_code }}
                      </small>
                    @else
                      <span class="text-muted">—</span>
                    @endif
                  </td>
                  <td class="text-end fw-semibold">
                    {{ $expenseAmount > 0 ? number_format($expenseAmount, 2) : '' }}
                  </td>
                </tr>
              @endfor
            </tbody>

            <tfoot class="fw-bold">
              <tr class="bg-dark text-white">
                <td class="text-end">Total Income</td>
                <td class="text-end">{{ number_format($totalIncome, 2) }}</td>
                <td class="text-end">Total Expenses</td>
                <td class="text-end">{{ number_format($totalExpenses, 2) }}</td>
              </tr>
              <tr class="{{ $netProfit >= 0 ? 'table-success' : 'table-danger' }}">
                <td colspan="2" class="text-end">
                  {{ $netProfit >= 0 ? 'Net Profit' : 'Net Loss' }}
                </td>
                <td colspan="2" class="text-end">
                  {{ number_format(abs($netProfit), 2) }}
                </td>
              </tr>
            </tfoot>

          </table>
        </div>

      </div>
    </div>
  </div>
</div>

{{-- 🖋️ STYLES --}}
<style>
  .card-header {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
  }

  th.bg-dark {
    background-color: #343a40 !important;
  }

  .table th, .table td {
    vertical-align: middle !important;
  }

  .table tfoot td {
    font-weight: 700 !important;
  }

  .table-success td, .table-danger td {
    font-size: 1rem;
    font-weight: 600;
  }

  @media (max-width: 768px) {
    th, td {
      font-size: 13px;
    }
  }
</style>
@endsection
