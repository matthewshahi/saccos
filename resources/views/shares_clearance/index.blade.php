@extends('layouts.app')

@section('content')
<div class="container">
    <!-- Bootstrap 5 CSS -->
  @include('partials.alerts')

  <h3>Shares Clearance</h3>
  <p class="text-muted">
    Below is a list of members (showing a maximum of 20). Click <b>Clear Loan</b> on the right to apply shares towards outstanding loans.
  </p>

  <!-- Search box -->
  <form method="GET" class="mb-3 d-flex">
    <input type="text" name="term" value="{{ $term ?? '' }}" 
           class="form-control me-2" placeholder="🔍 Search by Name, Sacco ID, ID No, Phone, Company...">
    <button class="btn btn-primary">Search</button>
  </form>

  <!-- Members Table -->
  <div class="table-responsive">
    <table class="table table-bordered table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Sacco ID</th>
          <th>National ID</th>
          <th>Name</th>
          <th>Phone</th>
          <th>Company</th>
          <th>Department</th>
          <th class="text-end">Shares Balance</th>
          <th width="150">Action</th>
        </tr>
      </thead>
      <tbody>
        @forelse($members as $m)
        <tr>
          <td>{{ $loop->iteration }}</td>
          <td>{{ $m->member_sacco_id }}</td>
          <td>{{ $m->member_national_id }}</td>
          <td>{{ $m->member_name }}</td>
          <td>{{ $m->member_phone_no }}</td>
          <td>{{ $m->company_name ?? '-' }}</td>
          <td>{{ $m->department_name ?? '-' }}</td>
          <td class="text-end">{{ number_format($m->member_total_share, 2) }}</td>
          <td>
            <button type="button" 
                    class="btn btn-sm btn-success clear-loan-btn" 
                    data-id="{{ $m->member_id }}">
              Clear Loan
            </button>
          </td>
        </tr>
        @empty
        <tr>
          <td colspan="9" class="text-center text-muted">No members found.</td>
        </tr>
        @endforelse
      </tbody>
    </table>
  </div>

  @if(count($members) >= 20)
  <div class="alert alert-info mt-3">
    Showing first 20 records only. Please refine your search for more specific results.
  </div>
  @endif
</div>

 <!-- ================== MODAL (outside container, works with Bootstrap) ================== -->
<div class="modal fade" id="loansModal" tabindex="-1" aria-labelledby="loansModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="loansModalLabel">Member Loans</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <!-- 🔹 This is where we’ll inject loan content -->
      <div class="modal-body" id="loansModalContent">
        <div class="p-4 text-center text-muted">No data loaded yet.</div>
      </div>
    </div>
  </div>
</div>

<!-- jQuery -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- ✅ Ensure Bootstrap Bundle (with Popper) is loaded -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
$(document).on('click', '.clear-loan-btn', function(){
  let memberId = $(this).data('id');

  // Show modal using Bootstrap 5 API
  let modalEl = document.getElementById('loansModal');
  let modal = new bootstrap.Modal(modalEl);
  modal.show();

  // Set loading state directly on #loansModalContent
  $('#loansModalContent').html('<div class="p-4 text-center">Loading loans...</div>');

  // Load loans into modal body
  $.get("{{ url('shares-clearance') }}/" + memberId + "/loans", function(html){
    $('#loansModalContent').html(html);
  }).fail(function(){
    $('#loansModalContent').html('<div class="p-4 text-danger">Failed to load loans.</div>');
  });
});
</script>
@endsection