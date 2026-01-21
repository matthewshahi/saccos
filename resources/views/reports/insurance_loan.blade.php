@extends('layouts.app')

@section('content')
<div class="col-md-12">
  <div class="card o-hidden mb-4 shadow-sm border-0">
    <div class="card-header d-flex align-items-center justify-content-between bg-light">
      <h4 class="card-title mb-0 text-primary">
        <i class="bi bi-graph-up-arrow me-1"></i> Insurance Loan Report
      </h4>

      <div class="d-flex align-items-center gap-2">
        <!-- Export Button -->
        <a href="{{ route('reports.loans.insurance.export', ['period' => request('period', $period), 'pms_srch' => request('pms_srch')]) }}"
           class="btn btn-sm btn-outline-success d-flex align-items-center gap-1">
          <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
        </a>

        <!-- Search + Period Filter -->
        <form method="GET" action="{{ route('reports.loans.insurance') }}" class="row g-2 align-items-center justify-content-end mt-1">

          <!-- Search -->
          <div class="col-auto">
            <div class="input-group input-group-sm">
              <span class="input-group-text bg-light border-0">
                <i class="bi bi-search text-muted"></i>
              </span>
              <input type="text"
                     name="pms_srch"
                     value="{{ request('pms_srch') }}"
                     class="form-control"
                     placeholder="Search by member name or ID...">
            </div>
          </div>

          <!-- Period -->
          <div class="col-auto">
            <div class="input-group input-group-sm">
              <span class="input-group-text bg-light border-0">
                <i class="bi bi-calendar3 text-muted"></i>
              </span>
              <input type="number"
                     name="period"
                     value="{{ request('period', $period) }}"
                     class="form-control text-center"
                     style="width: 110px;"
                     placeholder="YYYYMM">
            </div>
          </div>

          <!-- Filter Button -->
          <div class="col-auto">
            <button type="submit" class="btn btn-sm btn-primary px-3">
              <i class="bi bi-funnel me-1"></i> Filter
            </button>
          </div>
        </form>
      </div>
    </div>

    <div class="card-body">
      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle text-sm">
          <thead class="table-light">
            <tr>
              <th>#</th>
              <th>Member Name</th>
              <th>Member ID</th>
              <th>Gender</th>
              <th>Loan Type</th>
              <th class="text-end">Loan Amount (KES)</th>
              <th>Approval Date</th>
              <th class="text-center">Loan Period</th>
              <th class="text-center">Tenure (Months)</th>
              <th class="text-end">Outstanding (KES)</th>
              <th class="text-center">Remaining</th>
              <th class="text-center">Interest (%)</th>
            </tr>
          </thead>
          <tbody>
            @forelse($records as $index => $rec)
              <tr>
                <td>{{ $index + 1 }}</td>

                <td class="text-start">
                  <div class="fw-semibold text-dark">{{ strtoupper($rec['member_name']) }}</div>
                  <div class="small text-muted mt-1">
                    @if(!empty($rec['member_phone_no']))
                      <i class="bi bi-telephone me-1"></i>{{ $rec['member_phone_no'] }}
                    @endif
                    @if(!empty($rec['member_national_id']))
                      <span class="mx-2">|</span>
                      <i class="bi bi-person-badge me-1"></i>{{ $rec['member_national_id'] }}
                    @endif
                    @if(!empty($rec['member_kra_pin']))
                      <span class="mx-2">|</span>
                      <i class="bi bi-credit-card-2-front me-1"></i>KRA PIN: {{ $rec['member_kra_pin'] }}
                    @endif
                  </div>
                </td>

                <td>{{ $rec['member_sacco_id'] }}</td>
                <td>{{ $rec['member_gender'] }}</td>
                <td>{{ $rec['loan_type_name'] }}</td>

                <td class="text-end text-success fw-semibold">
                  {{ number_format($rec['loan_amount'], 2) }}
                </td>

                <td>
                  {{ \Carbon\Carbon::parse($rec['loan_on'])->format('d-M-Y') }}
                </td>

                <td class="text-center fw-semibold">
                  {{ $rec['loan_taken_period'] }}
                </td>

                <td class="text-center">
                  {{ $rec['loan_payment_period'] }}
                </td>

                <td class="text-end fw-bold {{ $rec['loan_balance'] > 0 ? 'text-danger' : 'text-muted' }}">
                  {{ number_format($rec['loan_balance'], 2) }}
                </td>

                <td class="text-center">
                  {!! $rec['months_remaining'] !!}
                </td>

                <td class="text-center">
                  {{ $rec['loan_type_interest'] }}
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="12" class="py-4 text-muted text-center">
                  <i class="bi bi-inbox"></i> No records found for this period.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

    @if(count($records))
      <div class="card-footer text-end small text-muted">
        Showing {{ count($records) }} records for period <strong>{{ $period }}</strong>
      </div>
    @endif
  </div>
</div>

<style>
.card-header {
  background-color: #f8f9fa;
  border-bottom: 2px solid #eee;
}

.card-title {
  color: #5A2A83;
  font-weight: 600;
}

.btn-primary {
  background-color: #5A2A83;
  border-color: #5A2A83;
}
.btn-primary:hover {
  background-color: #4a1f6f;
  border-color: #4a1f6f;
}

.btn-outline-success {
  border-color: #5A2A83;
  color: #5A2A83;
}
.btn-outline-success:hover {
  background-color: #5A2A83;
  color: #fff;
}
@extends('layouts.app')

@section('content')
<div class="col-md-12">
    <div class="card o-hidden mb-4">

        <!-- Header -->
        <div class="card-header d-flex align-items-center border-0">
            <h3 class="w-50 float-start card-title m-0">
                <i class="bi bi-graph-up-arrow me-1"></i> Insurance Loan Report
            </h3>

            <div class="w-50 float-end text-end d-flex justify-content-end gap-2">

                <!-- Export -->
                <a href="{{ route('reports.loans.insurance.export', ['period' => request('period', $period), 'pms_srch' => request('pms_srch')]) }}"
                   class="btn btn-sm btn-outline-success">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
                </a>

                <!-- Filters -->
                <form method="GET" action="{{ route('reports.loans.insurance') }}" class="d-flex gap-2">

                    <input type="text"
                           name="pms_srch"
                           value="{{ request('pms_srch') }}"
                           class="form-control form-control-sm"
                           placeholder="Search member...">

                    <input type="number"
                           name="period"
                           value="{{ request('period', $period) }}"
                           class="form-control form-control-sm text-center"
                           style="width:110px"
                           placeholder="YYYYMM">

                    <button class="btn btn-sm btn-primary">
                        <i class="bi bi-funnel"></i>
                    </button>
                </form>
            </div>
        </div>

        <!-- Body -->
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">

                <thead class="table-light">
                    <tr>
                        <th class="text-center">#</th>
                        <th class="text-start">Member Name</th>
                        <th class="text-start">Member ID</th>
                        <th class="text-start">Gender</th>
                        <th class="text-start">Loan Type</th>
                        <th class="text-end">Loan Amount (KES)</th>
                        <th class="text-start">Approval Date</th>
                        <th class="text-center">Loan Period</th>
                        <th class="text-center">Tenure (Months)</th>
                        <th class="text-end">Outstanding (KES)</th>
                        <th class="text-center">Remaining</th>
                        <th class="text-center">Interest (%)</th>
                    </tr>
                </thead>

                <tbody>
                @forelse($records as $index => $rec)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>

                        <!-- Member -->
                        <td class="text-start">
                            <div class="fw-semibold">{{ strtoupper($rec['member_name']) }}</div>
                            <div class="small text-muted">
                                @if($rec['member_phone_no'])
                                    {{ $rec['member_phone_no'] }} |
                                @endif
                                {{ $rec['member_national_id'] }}
                            </div>
                        </td>

                        <td class="text-start">{{ $rec['member_sacco_id'] }}</td>
                        <td class="text-start">{{ $rec['member_gender'] }}</td>
                        <td class="text-start">{{ $rec['loan_type_name'] }}</td>

                        <!-- Amounts -->
                        <td class="text-end fw-semibold text-success">
                            {{ number_format($rec['loan_amount'], 2) }}
                        </td>

                        <!-- Dates / Periods -->
                        <td class="text-start">
                            {{ \Carbon\Carbon::parse($rec['loan_on'])->format('d-M-Y') }}
                        </td>

                        <td class="text-center fw-semibold">
                            {{ $rec['loan_taken_period'] }}
                        </td>

                        <td class="text-center">
                            {{ $rec['loan_payment_period'] }}
                        </td>

                        <!-- Balances -->
                        <td class="text-end fw-bold {{ $rec['loan_balance'] > 0 ? 'text-danger' : 'text-muted' }}">
                            {{ number_format($rec['loan_balance'], 2) }}
                        </td>

                        <td class="text-center">
                            {!! $rec['months_remaining'] !!}
                        </td>

                        <td class="text-center">
                            {{ $rec['loan_type_interest'] }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="text-center text-muted py-4">
                            No records found for this period
                        </td>
                    </tr>
                @endforelse
                </tbody>

            </table>
        </div>

        <!-- Footer -->
        @if(count($records))
            <div class="card-footer text-end small text-muted">
                Showing {{ count($records) }} records for period <strong>{{ $period }}</strong>
            </div>
        @endif

    </div>
</div>
@endsection

.small.text-muted i {
  color: #6c757d;
}

.fw-semibold.text-dark {
  font-size: 0.95rem;
}
</style>
@endsection
@extends('layouts.app')

@section('content')
<div class="col-md-12">
    <div class="card o-hidden mb-4">

        <!-- Header -->
        <div class="card-header d-flex align-items-center border-0">
            <h3 class="w-50 float-start card-title m-0">
                <i class="bi bi-graph-up-arrow me-1"></i> Insurance Loan Report
            </h3>

            <div class="w-50 float-end text-end d-flex justify-content-end gap-2">

                <!-- Export -->
                <a href="{{ route('reports.loans.insurance.export', ['period' => request('period', $period), 'pms_srch' => request('pms_srch')]) }}"
                   class="btn btn-sm btn-outline-success">
                    <i class="bi bi-file-earmark-spreadsheet"></i> Export CSV
                </a>

                <!-- Filters -->
                <form method="GET" action="{{ route('reports.loans.insurance') }}" class="d-flex gap-2">

                    <input type="text"
                           name="pms_srch"
                           value="{{ request('pms_srch') }}"
                           class="form-control form-control-sm"
                           placeholder="Search member...">

                    <input type="number"
                           name="period"
                           value="{{ request('period', $period) }}"
                           class="form-control form-control-sm text-center"
                           style="width:110px"
                           placeholder="YYYYMM">

                    <button class="btn btn-sm btn-primary">
                        <i class="bi bi-funnel"></i>
                    </button>
                </form>
            </div>
        </div>

        <!-- Body -->
        <div class="table-responsive">
            <table class="table table-striped table-hover align-middle">

                <thead class="table-light">
                    <tr>
                        <th class="text-center">#</th>
                        <th class="text-start">Member Name</th>
                        <th class="text-start">Member ID</th>
                        <th class="text-start">Gender</th>
                        <th class="text-start">Loan Type</th>
                        <th class="text-end">Loan Amount (KES)</th>
                        <th class="text-start">Approval Date</th>
                        <th class="text-center">Loan Period</th>
                        <th class="text-center">Tenure (Months)</th>
                        <th class="text-end">Outstanding (KES)</th>
                        <th class="text-center">Remaining</th>
                        <th class="text-center">Interest (%)</th>
                    </tr>
                </thead>

                <tbody>
                @forelse($records as $index => $rec)
                    <tr>
                        <td class="text-center">{{ $index + 1 }}</td>

                        <!-- Member -->
                        <td class="text-start">
                            <div class="fw-semibold">{{ strtoupper($rec['member_name']) }}</div>
                            <div class="small text-muted">
                                @if($rec['member_phone_no'])
                                    {{ $rec['member_phone_no'] }} |
                                @endif
                                {{ $rec['member_national_id'] }}
                            </div>
                        </td>

                        <td class="text-start">{{ $rec['member_sacco_id'] }}</td>
                        <td class="text-start">{{ $rec['member_gender'] }}</td>
                        <td class="text-start">{{ $rec['loan_type_name'] }}</td>

                        <!-- Amounts -->
                        <td class="text-end fw-semibold text-success">
                            {{ number_format($rec['loan_amount'], 2) }}
                        </td>

                        <!-- Dates / Periods -->
                        <td class="text-start">
                            {{ \Carbon\Carbon::parse($rec['loan_on'])->format('d-M-Y') }}
                        </td>

                        <td class="text-center fw-semibold">
                            {{ $rec['loan_taken_period'] }}
                        </td>

                        <td class="text-center">
                            {{ $rec['loan_payment_period'] }}
                        </td>

                        <!-- Balances -->
                        <td class="text-end fw-bold {{ $rec['loan_balance'] > 0 ? 'text-danger' : 'text-muted' }}">
                            {{ number_format($rec['loan_balance'], 2) }}
                        </td>

                        <td class="text-center">
                            {!! $rec['months_remaining'] !!}
                        </td>

                        <td class="text-center">
                            {{ $rec['loan_type_interest'] }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="12" class="text-center text-muted py-4">
                            No records found for this period
                        </td>
                    </tr>
                @endforelse
                </tbody>

            </table>
        </div>

        <!-- Footer -->
        @if(count($records))
            <div class="card-footer text-end small text-muted">
                Showing {{ count($records) }} records for period <strong>{{ $period }}</strong>
            </div>
        @endif

    </div>
</div>
@endsection
