@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-md-12">
    

    <!-- Card -->
    <div class="card mb-4">
      <div class="card-body">
        <div class="card-title mb-3">Edit Fleet Vehicle</div>
        <!-- Top Navigation Buttons -->
    <div class="mb-3 d-flex justify-content-end flex-wrap gap-1">
  <a href="{{ route('fleet') }}" class="btn btn-outline-primary btn-sm">Fleet List</a>
  <a href="{{ route('operators') }}" class="btn btn-outline-secondary btn-sm">Operators</a>
  <a href="{{ route('routes') }}" class="btn btn-outline-info btn-sm">Routes</a>
  <a href="{{ route('collections') }}" class="btn btn-outline-success btn-sm">Collections</a>
  <a href="{{ route('penalties') }}" class="btn btn-outline-warning btn-sm">Penalties</a>
  <a href="{{ route('targets') }}" class="btn btn-outline-dark btn-sm">Targets</a>
  <a href="{{ route('maintenance') }}" class="btn btn-outline-danger btn-sm">Maintenance</a>
</div>

    
        <form method="POST" action="{{ route('fleet.update', $vehicle->id) }}">
          @csrf
          <div class="row">
            <!-- Registration Number -->
            <div class="col-md-6 form-group mb-3">
              <label for="vehicles_registration_number">Registration Number *</label>
              <input class="form-control" name="vehicles_registration_number" required type="text"
                value="{{ old('vehicles_registration_number', $vehicle->vehicles_registration_number) }}">
            </div>

            <!-- Make, Model, Year -->
            <div class="col-md-6 form-group mb-3">
              <label for="vehicles_make">Make</label>
              <input class="form-control" name="vehicles_make" type="text"
                value="{{ old('vehicles_make', $vehicle->vehicles_make) }}">
            </div>

            <div class="col-md-6 form-group mb-3">
              <label for="vehicles_model">Model</label>
              <input class="form-control" name="vehicles_model" type="text"
                value="{{ old('vehicles_model', $vehicle->vehicles_model) }}">
            </div>

            <div class="col-md-6 form-group mb-3">
              <label for="vehicles_year">Year</label>
              <input class="form-control" name="vehicles_year" type="number"
                value="{{ old('vehicles_year', $vehicle->vehicles_year) }}">
            </div>

            <!-- Chassis, Insurance -->
            <div class="col-md-6 form-group mb-3">
              <label for="vehicles_chassis_number">Chassis Number</label>
              <input class="form-control" name="vehicles_chassis_number" type="text"
                value="{{ old('vehicles_chassis_number', $vehicle->vehicles_chassis_number) }}">
            </div>

            <div class="col-md-6 form-group mb-3">
              <label for="vehicles_insurance_provider">Insurance Provider</label>
              <input class="form-control" name="vehicles_insurance_provider" type="text"
                value="{{ old('vehicles_insurance_provider', $vehicle->vehicles_insurance_provider) }}">
            </div>

            <div class="col-md-6 form-group mb-3">
              <label for="vehicles_insurance_expiry">Insurance Expiry</label>
              <input class="form-control" name="vehicles_insurance_expiry" type="date"
                value="{{ old('vehicles_insurance_expiry', $vehicle->vehicles_insurance_expiry) }}">
            </div>

            <div class="col-md-6 form-group mb-3">
              <label for="vehicles_last_inspection_date">Last Inspection Date</label>
              <input class="form-control" name="vehicles_last_inspection_date" type="date"
                value="{{ old('vehicles_last_inspection_date', $vehicle->vehicles_last_inspection_date) }}">
            </div>

            <!-- PSV Details -->
            <div class="col-md-6 form-group mb-3">
              <label for="vehicles_psv_license_number">PSV License Number</label>
              <input class="form-control" name="vehicles_psv_license_number" type="text"
                value="{{ old('vehicles_psv_license_number', $vehicle->vehicles_psv_license_number) }}">
            </div>

            <div class="col-md-6 form-group mb-3">
              <label for="vehicles_psv_expiry">PSV Expiry</label>
              <input class="form-control" name="vehicles_psv_expiry" type="date"
                value="{{ old('vehicles_psv_expiry', $vehicle->vehicles_psv_expiry) }}">
            </div>

            <!-- Route -->
            <div class="col-md-6 form-group mb-3">
              <label for="vehicles_route_name">Route</label>
              <select class="form-control" name="vehicles_route_name">
                <option value="">-- Select Route --</option>
                @foreach($routes as $route)
                  <option value="{{ $route->route_name }}"
                    {{ old('vehicles_route_name', $vehicle->vehicles_route_name) == $route->route_name ? 'selected' : '' }}>
                    {{ $route->route_name }}
                  </option>
                @endforeach
              </select>
            </div>

            <!-- Member (Owner) -->
            <div class="col-md-6 form-group mb-3">
  <label for="vehicles_member_id">Owner (Member)</label>
  <select class="form-control" name="vehicles_member_id" required>
     <!-- Pre-select the saved or old member value (readonly view) -->
<option value="{{ $vehicle->vehicles_member_id }}" selected>
  {{ $vehicle->member_name ?? 'Selected Member' }} - {{ $vehicle->member_sacco_id ?? '' }}
</option>

<!-- Then allow selection from all members -->
@foreach($members as $member)
  @if($member->member_id != $vehicle->vehicles_member_id)
    <option value="{{ $member->member_id }}">
      {{ $member->member_name }} - {{ $member->member_sacco_id }}
    </option>
  @endif
@endforeach
  </select>
</div>

            <!-- Status -->
            <div class="col-md-6 form-group mb-3">
              <label for="vehicles_status">Status</label>
              <select class="form-control" name="vehicles_status">
                @php
                  $statuses = [
                    'pending_approval', 'active', 'inactive', 'suspended',
                    'under_maintenance', 'decommissioned', 'blacklisted'
                  ];
                @endphp
                @foreach($statuses as $status)
                  <option value="{{ $status }}"
                    {{ old('vehicles_status', $vehicle->vehicles_status) == $status ? 'selected' : '' }}>
                    {{ ucfirst(str_replace('_', ' ', $status)) }}
                  </option>
                @endforeach
              </select>
            </div>

            <!-- Actions -->
            <div class="col-md-12">
              <button class="btn btn-primary">Update</button>
              <a href="{{ route('fleet') }}" class="btn btn-secondary ms-2">Cancel</a>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection