@extends('layouts.app')

@section('content')
<div class="card">
    @include('transport._nav_links')

    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    @if($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
    @endif

    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Recent Collections</h5>
        <a href="{{ route('collections.create') }}" class="btn btn-primary btn-sm">+ Add Collection</a>
    </div>

    <div class="card-body table-responsive">
        <form method="GET" action="{{ route('collections') }}" class="mb-3">
            <div class="input-group">
                <input type="text" name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Search operator, vehicle, ref, type...">
                <button class="btn btn-outline-secondary btn-sm" type="submit">Search</button>
            </div>
        </form>

        <table class="table table-bordered table-hover table-sm">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Date</th>
                    <th>Operator</th>
                    <th>Vehicle</th>
                    <th>Type</th>
                    <th>Mode</th>
                    <th>Amount</th>
                    <th>Ref</th>
                    <th>Reconciled?</th>
                </tr>
            </thead>
            <tbody>
                @forelse($collections as $index => $c)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $c->coll_date }}</td>
                    <td>{{ $c->operator_name ?? 'N/A' }}</td>
                    <td>{{ $c->vehicle_reg ?? 'N/A' }}</td>
                    <td><span class="badge bg-secondary">{{ ucfirst($c->coll_type) }}</span></td>
                    <td>{{ ucfirst($c->coll_mode) }}</td>
                    <td>KES {{ number_format($c->coll_amount, 2) }}</td>
                    <td>{{ $c->coll_reference ?? '-' }}</td>
                    <td>
                        @if($c->coll_type === 'loan_repayment' && $c->coll_reconciled === 'No')
                            <a href="#" class="btn btn-sm btn-warning reconcile-btn"
                               data-id="{{ $c->id }}"
                               data-member="{{ $c->coll_operator_id }}"
                               data-bs-toggle="modal"
                               data-bs-target="#reconcileModal">
                               Reconcile
                            </a>
                        @else
                            <span class="text-success">✓</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="9" class="text-center">No collections found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Reconciliation Modal --}}
<div class="modal fade" id="reconcileModal" tabindex="-1" aria-labelledby="reconcileModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Reconcile Loan Repayment</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div id="reconcile-content" class="text-muted">Loading loan details...</div>
      </div>
    </div>
  </div>
</div>

{{-- Script to load modal content --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const content = document.getElementById('reconcile-content');

    document.querySelectorAll('.reconcile-btn').forEach(button => {
        button.addEventListener('click', function () {
            const collectionId = this.dataset.id;
            const memberId = this.dataset.member;

            content.innerHTML = '<p class="text-muted">Loading loan details...</p>';

 
            fetch(`/transport/collections/${collectionId}/reconcile?member_id=${memberId}`)
            
                .then(res => res.text())
                .then(html => content.innerHTML = html)
                .catch(() => {
                    content.innerHTML = '<p class="text-danger">Failed to load loan details. Please try again.</p>';
                });
        });
    });
});
</script>
 
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>


 

@endsection