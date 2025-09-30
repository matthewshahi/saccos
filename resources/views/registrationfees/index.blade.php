@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-md-12">
    @include('partials.alerts')

    <div class="card o-hidden mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title m-0">Registration Fee Payments</h3>
      </div>

      <div class="card-body">
        <!-- 🔍 Search bar -->
        <form method="GET" action="{{ route('registrationfees.index') }}" class="d-flex mb-3">
          <input type="text" name="search" value="{{ $search ?? '' }}" 
                 class="form-control me-2"
                 placeholder="Search by Member, ID, Phone, Doc No, Description">
          <button type="submit" class="btn btn-primary">Search</button>
        </form>

        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light text-center">
              <tr>
                <th style="width: 5%">#</th>
                <th style="width: 20%">Member</th>
                <th style="width: 12%">Amount</th>
                <th style="width: 20%">Description</th>
                <th style="width: 10%">Doc No</th>
                <th style="width: 15%">Date Paid</th>
                <th style="width: 10%">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($records as $i => $rec)
                <tr>
                  <td class="text-center">{{ $records->firstItem() + $i }}</td>
                  <td>
                    <div class="fw-semibold">{{ $rec->member_name }}</div>
                    <div class="text-muted small">
                      Sacco ID: {{ $rec->member_sacco_id ?? 'N/A' }} |
                      ID: {{ $rec->member_national_id ?? 'N/A' }} |
                      Phone: {{ $rec->member_phone_no ?? 'N/A' }}
                    </div>
                  </td>
                  <td class="text-end fw-bold text-success">
                    +{{ number_format($rec->regfee_amount, 2) }}
                  </td>
                  <td>{{ $rec->regfee_description }}</td>
                  <td>{{ $rec->regfee_doc_no }}</td>
                  <td class="text-center">
                    {{ \Carbon\Carbon::parse($rec->regfee_date_paid)->format('d M Y') }}
                  </td>
                  <td class="text-center">
                    <a href="{{ route('registrationfees.receipt', $rec->regfee_id) }}" 
                       target="_blank" 
                       class="btn btn-sm btn-outline-info">
                      Receipt
                    </a>
                  </td>
                </tr>
              @empty
                <tr>
                  <td colspan="7" class="text-center text-muted">No registration fees found.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <!-- 📄 Pagination -->
      <div class="card-footer d-flex justify-content-between align-items-center">
        <div class="text-muted small">
          Showing {{ $records->firstItem() }} to {{ $records->lastItem() }} of {{ $records->total() }} results
        </div>
        <div>
          {{ $records->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
      </div>
    </div>
  </div>
</div>
@endsection