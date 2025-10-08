@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-md-12">
    @include('partials.alerts')

    <div class="card o-hidden mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <h3 class="card-title m-0">FOSA Transactions</h3>
        <a href="{{ route('fosa.transactions.create') }}" class="btn btn-success btn-sm">
          <i class="bi bi-plus-circle"></i> Add Transaction
        </a>
      </div>

      <div class="card-body">
        <!-- Search bar -->
         

        <form method="GET" action="{{ route('fosa.transactions.index') }}" class="d-flex mb-3 me-2">
    <input type="text" name="search" value="{{ $search ?? '' }}" 
           class="form-control me-2"
           placeholder="Search by Member, ID, Phone, Doc No, Description, Period">
    <button type="submit" class="btn btn-primary me-2">Search</button>
    <a href="{{ route('fosa.transactions.export', ['search' => $search ?? '']) }}" 
       class="btn btn-success">
        <i class="bi bi-file-earmark-excel"></i> Export XLS
    </a>
</form>

        <div class="table-responsive">
          <table class="table table-striped table-hover align-middle">
            <thead class="table-light text-center">
              <tr>
                <th style="width: 5%">#</th>
                <th style="width: 20%">Member</th>
                <th style="width: 15%">Amount</th>
                <th style="width: 25%">Description</th>
                <th style="width: 15%">Doc No</th>
                <th style="width: 15%">Date</th>
                <th style="width: 5%">Actions</th>
              </tr>
            </thead>
            <tbody>
              @forelse($records as $i => $rec)
                <tr>
                  <td class="text-center">{{ $records->firstItem() + $i }}</td>
                  <td class="fw-semibold">{{ $rec->member_name }}</td>
                  <td class="text-end fw-bold">
                    @if($rec->fosa_amount_paying >= 0)
                      <span class="text-success">+{{ number_format($rec->fosa_amount_paying, 2) }}</span>
                    @else
                      <span class="text-danger">{{ number_format($rec->fosa_amount_paying, 2) }}</span>
                    @endif
                  </td>
                  <td>{{ $rec->fosa_description }}</td>
                  <td>{{ $rec->fosa_doc_no }}</td>
                  <td class="text-center">{{ \Carbon\Carbon::parse($rec->fosa_transdate)->format('d M Y H:i') }}</td>
                  <td class="text-center">
                    <button class="btn btn-sm btn-outline-info" 
                            data-bs-toggle="modal" 
                            data-bs-target="#transactionModal{{ $rec->fosa_id }}">
                      Details
                    </button>
                  </td>
                </tr>

                <!-- Modal for Transaction Details -->
                <div class="modal fade" id="transactionModal{{ $rec->fosa_id }}" tabindex="-1" 
                     aria-labelledby="transactionModalLabel{{ $rec->fosa_id }}" aria-hidden="true">
                  <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                      <div class="modal-header bg-light">
                        <h5 class="modal-title" id="transactionModalLabel{{ $rec->fosa_id }}">
                          Transaction: <strong>{{ $rec->fosa_doc_no }}</strong> – {{ $rec->member_name }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                      </div>

                      <div class="modal-body">
                        <div class="row">
                          <!-- Left Column: Member Info -->
                          <div class="col-md-6">
                            <h6 class="text-primary border-bottom pb-1 mb-3">Member Information</h6>
                            <dl class="row mb-2">
                              <dt class="col-sm-5">Sys ID</dt>
                              <dd class="col-sm-7">{{ $rec->fosa_member_id }}</dd>

                              <dt class="col-sm-5">Member No</dt>
                              <dd class="col-sm-7">{{ $rec->member_sacco_id ?? 'N/A' }}</dd>

                              <dt class="col-sm-5">National ID</dt>
                              <dd class="col-sm-7">{{ $rec->member_national_id ?? 'N/A' }}</dd>

                              <dt class="col-sm-5">Phone</dt>
                              <dd class="col-sm-7">{{ $rec->member_phone_no ?? 'N/A' }}</dd>

                              <dt class="col-sm-5">Email</dt>
                              <dd class="col-sm-7">{{ $rec->member_email ?? 'N/A' }}</dd>
                            </dl>
                          </div>

                          <!-- Right Column: Transaction Info -->
                          <div class="col-md-6">
                            <h6 class="text-primary border-bottom pb-1 mb-3">Transaction Information</h6>
                            <dl class="row mb-2">
                              <dt class="col-sm-5">Amount</dt>
                              <dd class="col-sm-7 fw-bold">
                                @if($rec->fosa_amount_paying >= 0)
                                  <span class="text-success">
                                    +{{ number_format($rec->fosa_amount_paying, 2) }}
                                  </span> 
                                  <span class="badge bg-success">Deposit</span>
                                @else
                                  <span class="text-danger">
                                    {{ number_format($rec->fosa_amount_paying, 2) }}
                                  </span> 
                                  <span class="badge bg-danger">Withdrawal</span>
                                @endif
                              </dd>

                              <dt class="col-sm-5">Description</dt>
                              <dd class="col-sm-7">{{ $rec->fosa_description }}</dd>

                              <dt class="col-sm-5">FOSA Type</dt>
                              <dd class="col-sm-7">
                                <span class="badge bg-info text-dark">{{ $rec->fosa_type_name ?? 'N/A' }}</span>
                              </dd>

                              <dt class="col-sm-5">Period</dt>
                              <dd class="col-sm-7">{{ $rec->fosa_period }}</dd>

                              <dt class="col-sm-5">Date Paid</dt>
                              <dd class="col-sm-7">{{ \Carbon\Carbon::parse($rec->fosa_date_paid)->format('d M Y, H:i') }}</dd>

                              <dt class="col-sm-5">Paid By (Receipt)</dt>
                              <dd class="col-sm-7">{{ $rec->fosa_paid_by }}</dd>

                              <dt class="col-sm-5">Entered By (Staff)</dt>
                              <dd class="col-sm-7">{{ $rec->entered_by_name ?? 'System' }} </dd>

                              <dt class="col-sm-5">IP Address</dt>
                              <dd class="col-sm-7">{{ $rec->fosa_ip }}</dd>
                            </dl>
                          </div>
                        </div>
                      </div>

                      <div class="modal-footer">
  <a href="{{ route('fosa.transactions.receipt', $rec->fosa_id) }}" 
     target="_blank" 
     class="btn btn-primary">
    <i class="bi bi-printer"></i> Print Receipt
  </a>
  <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
</div>
                    </div>
                  </div>
                </div>
              @empty
                <tr>
                  <td colspan="7" class="text-center text-muted">No transactions found.</td>
                </tr>
              @endforelse
            </tbody>
          </table>
        </div>
      </div>

      <!-- Pagination -->
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
 
<!-- Bootstrap Bundle with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
@endsection