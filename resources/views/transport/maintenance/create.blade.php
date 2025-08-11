@extends('layouts.app')

@section('content')
<div class="card">
     @include('transport._nav_links')
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Add Maintenance Record</h5>
        <a href="{{ route('maintenance') }}" class="btn btn-secondary btn-sm">← Back to List</a>
    </div>

    <form action="{{ route('maintenance.store') }}" method="POST">
        @csrf
        <div class="card-body">
            {{-- Vehicle & Basic Info --}}
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="maintenance_vehicle_id">Vehicle <span class="text-danger">*</span></label>
                        <select name="maintenance_vehicle_id" class="form-control" required>
                            <option value="">-- Select Vehicle --</option>
                            @foreach($vehicles as $v)
                                <option value="{{ $v->id }}" {{ old('maintenance_vehicle_id') == $v->id ? 'selected' : '' }}>
                                    {{ $v->vehicles_registration_number }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="maintenance_date">Date <span class="text-danger">*</span></label>
                        <input type="date" name="maintenance_date" class="form-control" value="{{ old('maintenance_date') ?? date('Y-m-d') }}" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="maintenance_type">Type <span class="text-danger">*</span></label>
                        <input type="text" name="maintenance_type" class="form-control" placeholder="e.g. Routine, Breakdown" value="{{ old('maintenance_type') }}" required>
                    </div>
                </div>
            </div>

            {{-- Description & SACCO Mechanic --}}
            <div class="row">
                <div class="col-md-4">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" name="maintenance_internal" value="1" id="maintenance_internal" {{ old('maintenance_internal') ? 'checked' : '' }}>
                        <label class="form-check-label" for="maintenance_internal">
                            Done by SACCO Mechanic
                        </label>
                    </div>
                </div>
                <div class="col-md-8">
                    <div class="form-group mb-3">
                        <label for="maintenance_description">Work Description <span class="text-danger">*</span></label>
                        <textarea name="maintenance_description" class="form-control" rows="2" required>{{ old('maintenance_description') }}</textarea>
                    </div>
                </div>
            </div>

            {{-- Fault & Tracking --}}
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="maintenance_fault_reported_by">Fault Reported By</label>
                        <input type="text" name="maintenance_fault_reported_by" class="form-control" value="{{ old('maintenance_fault_reported_by') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="maintenance_odometer_reading">Odometer (KM)</label>
                        <input type="number" name="maintenance_odometer_reading" class="form-control" value="{{ old('maintenance_odometer_reading') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="maintenance_next_due_date">Next Due Date</label>
                        <input type="date" name="maintenance_next_due_date" class="form-control" value="{{ old('maintenance_next_due_date') }}">
                    </div>
                </div>
            </div>

            {{-- Vendor, Cost & Payment --}}
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="maintenance_vendor">Garage / Vendor</label>
                        <input type="text" name="maintenance_vendor" class="form-control" value="{{ old('maintenance_vendor') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="maintenance_cost">Cost (KES) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" name="maintenance_cost" class="form-control" value="{{ old('maintenance_cost') }}" required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group mb-3">
                        <label for="maintenance_payment_mode">Payment Mode</label>
                        <input type="text" name="maintenance_payment_mode" class="form-control" value="{{ old('maintenance_payment_mode') }}">
                    </div>
                </div>
            </div>

            {{-- Docs & Parts --}}
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="maintenance_doc_ref">Doc Ref (Receipt No)</label>
                        <input type="text" name="maintenance_doc_ref" class="form-control" value="{{ old('maintenance_doc_ref') }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="maintenance_parts_used">Parts Used</label>
                        <input type="text" name="maintenance_parts_used" class="form-control" value="{{ old('maintenance_parts_used') }}">
                        <small class="text-muted">Comma-separated list (e.g., Brake Pads, Oil)</small>
                    </div>
                </div>
            </div>

            {{-- Status and Attachment --}}
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="maintenance_status">Status</label>
                        <select name="maintenance_status" class="form-control">
                            <option value="completed" {{ old('maintenance_status') == 'completed' ? 'selected' : '' }}>Completed</option>
                            <option value="pending" {{ old('maintenance_status') == 'pending' ? 'selected' : '' }}>Pending</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group mb-3">
                        <label for="maintenance_attachment_path">Attachment Path (if any)</label>
                        <input type="text" name="maintenance_attachment_path" class="form-control" value="{{ old('maintenance_attachment_path') }}">
                        <small class="text-muted">e.g., /receipts/July-Repair.pdf</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-footer text-end">
            <button type="submit" class="btn btn-success">Save Maintenance</button>
        </div>
    </form>
</div>
@endsection