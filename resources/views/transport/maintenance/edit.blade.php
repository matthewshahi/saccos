@extends('layouts.app')

@section('content')
<div class="card">
     @include('transport._nav_links')
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Edit Maintenance Record</h5>
        <a href="{{ route('maintenance') }}" class="btn btn-secondary btn-sm">← Back to List</a>
    </div>

    <form action="{{ route('maintenance.update', $record->id) }}" method="POST">
        @csrf
        @method('POST')
        <div class="card-body">
            {{-- Vehicle & Date --}}
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="maintenance_vehicle_id">Vehicle <span class="text-danger">*</span></label>
                        <select name="maintenance_vehicle_id" class="form-control" required>
                            <option value="">-- Select Vehicle --</option>
                            @foreach($vehicles as $v)
                                <option value="{{ $v->id }}" {{ (old('maintenance_vehicle_id', $record->maintenance_vehicle_id) == $v->id) ? 'selected' : '' }}>
                                    {{ $v->vehicles_registration_number }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="maintenance_date">Maintenance Date <span class="text-danger">*</span></label>
                        <input type="date" name="maintenance_date" class="form-control" value="{{ old('maintenance_date', $record->maintenance_date) }}" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="maintenance_type">Maintenance Type <span class="text-danger">*</span></label>
                        <input type="text" name="maintenance_type" class="form-control" value="{{ old('maintenance_type', $record->maintenance_type) }}" required>
                        <small class="text-muted">e.g., Routine, Breakdown</small>
                    </div>
                </div>
            </div>

            {{-- Description & Internal --}}
            <div class="row">
                <div class="col-md-4">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="maintenance_internal" id="maintenance_internal" value="1" {{ old('maintenance_internal', $record->maintenance_internal) ? 'checked' : '' }}>
                        <label class="form-check-label" for="maintenance_internal">SACCO Mechanic</label>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="form-group mb-3">
                        <label for="maintenance_description">Work Description <span class="text-danger">*</span></label>
                        <textarea name="maintenance_description" class="form-control" rows="2" required>{{ old('maintenance_description', $record->maintenance_description) }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Fault Reporting --}}
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="maintenance_fault_reported_by">Reported By</label>
                        <input type="text" name="maintenance_fault_reported_by" class="form-control" value="{{ old('maintenance_fault_reported_by', $record->maintenance_fault_reported_by) }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="maintenance_odometer_reading">Odometer</label>
                        <input type="number" name="maintenance_odometer_reading" class="form-control" value="{{ old('maintenance_odometer_reading', $record->maintenance_odometer_reading) }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="maintenance_next_due_date">Next Due Date</label>
                        <input type="date" name="maintenance_next_due_date" class="form-control" value="{{ old('maintenance_next_due_date', $record->maintenance_next_due_date) }}">
                    </div>
                </div>
            </div>

            {{-- Vendor & Cost --}}
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="maintenance_vendor">Vendor / Garage</label>
                        <input type="text" name="maintenance_vendor" class="form-control" value="{{ old('maintenance_vendor', $record->maintenance_vendor) }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="maintenance_cost">Cost (KES) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="maintenance_cost" class="form-control" value="{{ old('maintenance_cost', $record->maintenance_cost) }}" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="maintenance_payment_mode">Payment Mode</label>
                        <input type="text" name="maintenance_payment_mode" class="form-control" value="{{ old('maintenance_payment_mode', $record->maintenance_payment_mode) }}">
                    </div>
                </div>
            </div>

            {{-- Docs & Parts --}}
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="maintenance_doc_ref">Doc Ref (Receipt)</label>
                        <input type="text" name="maintenance_doc_ref" class="form-control" value="{{ old('maintenance_doc_ref', $record->maintenance_doc_ref) }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="maintenance_parts_used">Parts Used</label>
                        <input type="text" name="maintenance_parts_used" class="form-control" value="{{ old('maintenance_parts_used', $record->maintenance_parts_used) }}">
                        <small class="text-muted">Comma-separated (e.g., Oil, Belt)</small>
                    </div>
                </div>
            </div>

            {{-- Status & Attachment --}}
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="maintenance_status">Status</label>
                        <select name="maintenance_status" class="form-control">
                            <option value="pending" {{ (old('maintenance_status', $record->maintenance_status) === 'pending') ? 'selected' : '' }}>Pending</option>
                            <option value="completed" {{ (old('maintenance_status', $record->maintenance_status) === 'completed') ? 'selected' : '' }}>Completed</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="maintenance_attachment_path">Attachment Path</label>
                        <input type="text" name="maintenance_attachment_path" class="form-control" value="{{ old('maintenance_attachment_path', $record->maintenance_attachment_path) }}">
                    </div>
                </div>
            </div>
        </div>

        <div class="card-footer text-end">
            <button type="submit" class="btn btn-success">Update Record</button>
        </div>
    </form>
</div>
@endsection