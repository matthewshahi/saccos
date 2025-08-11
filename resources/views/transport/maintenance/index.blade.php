@extends('layouts.app')

@section('content')
<div class="card">
     @include('transport._nav_links')
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Maintenance Records</h5>
        <a href="{{ route('maintenance.create') }}" class="btn btn-primary btn-sm">+ Add Maintenance</a>
    </div>

    <div class="card-body table-responsive">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <table class="table table-bordered table-hover table-sm">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Vehicle</th>
                    <th>Type</th>
                    <th>Cost</th>
                    <th>Status</th>
                    <th>Recorded By</th>
                    <th>More</th>
                </tr>
            </thead>
            <tbody>
                @forelse($records as $record)
                <tr>
                    <td>{{ $record->maintenance_date }}</td>
                    <td>{{ $record->vehicle_reg ?? 'N/A' }}</td>
                    <td>{{ $record->maintenance_type }}</td>
                    <td>KES {{ number_format($record->maintenance_cost, 2) }}</td>
                    <td>
                        <span class="badge bg-{{ $record->maintenance_status === 'pending' ? 'warning' : 'success' }}">
                            {{ ucfirst($record->maintenance_status) }}
                        </span>
                    </td>
                    <td>{{ $record->recorder_name ?? 'User #' . $record->maintenance_recorded_by }}</td>
                    <td>
    <button class="btn btn-sm btn-info mb-1" data-bs-toggle="modal" data-bs-target="#modal{{ $record->id }}">
        View
    </button>
    <a href="{{ route('maintenance.edit', $record->id) }}" class="btn btn-sm btn-warning mb-1">Edit</a>
</td>
                </tr>

                {{-- Modal --}}
                <div class="modal fade" id="modal{{ $record->id }}" tabindex="-1" aria-labelledby="modalLabel{{ $record->id }}" aria-hidden="true">
                  <div class="modal-dialog modal-lg modal-dialog-scrollable">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title">Maintenance Details</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                      </div>
                      <div class="modal-body">
                        <dl class="row">
                            <dt class="col-sm-4">Date</dt>
                            <dd class="col-sm-8">{{ $record->maintenance_date }}</dd>

                            <dt class="col-sm-4">Vehicle</dt>
                            <dd class="col-sm-8">{{ $record->vehicle_reg ?? 'N/A' }}</dd>

                            <dt class="col-sm-4">Type</dt>
                            <dd class="col-sm-8">{{ $record->maintenance_type }}</dd>

                            <dt class="col-sm-4">SACCO Mechanic?</dt>
                            <dd class="col-sm-8">{{ $record->maintenance_internal ? 'Yes' : 'No' }}</dd>

                            <dt class="col-sm-4">Description</dt>
                            <dd class="col-sm-8">{{ $record->maintenance_description }}</dd>

                            <dt class="col-sm-4">Reported By</dt>
                            <dd class="col-sm-8">{{ $record->maintenance_fault_reported_by ?? '-' }}</dd>

                            <dt class="col-sm-4">Odometer Reading</dt>
                            <dd class="col-sm-8">{{ $record->maintenance_odometer_reading ?? '-' }}</dd>

                            <dt class="col-sm-4">Next Due Date</dt>
                            <dd class="col-sm-8">{{ $record->maintenance_next_due_date ?? '-' }}</dd>

                            <dt class="col-sm-4">Vendor / Garage</dt>
                            <dd class="col-sm-8">{{ $record->maintenance_vendor ?? '-' }}</dd>

                            <dt class="col-sm-4">Payment Mode</dt>
                            <dd class="col-sm-8">{{ $record->maintenance_payment_mode ?? '-' }}</dd>

                            <dt class="col-sm-4">Doc Ref</dt>
                            <dd class="col-sm-8">{{ $record->maintenance_doc_ref ?? '-' }}</dd>

                            <dt class="col-sm-4">Parts Used</dt>
                            <dd class="col-sm-8">{{ $record->maintenance_parts_used ?? '-' }}</dd>

                            <dt class="col-sm-4">Attachment Path</dt>
                            <dd class="col-sm-8">{{ $record->maintenance_attachment_path ?? '-' }}</dd>

                            <dt class="col-sm-4">Status</dt>
                            <dd class="col-sm-8">{{ ucfirst($record->maintenance_status) }}</dd>

                            <dt class="col-sm-4">IP Address</dt>
                            <dd class="col-sm-8">{{ $record->maintenance_ip ?? '-' }}</dd>
                        </dl>
                      </div>
                      <div class="modal-footer">
                        <a href="{{ route('maintenance.edit', $record->id) }}" class="btn btn-primary btn-sm">Edit</a>
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                      </div>
                    </div>
                  </div>
                </div>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted">No maintenance records found.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Bootstrap JS for modal --}}
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@endsection