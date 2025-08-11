@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-md-12">
    <div class="card mb-4">
      <div class="card-body">
        <div class="d-flex justify-content-between mb-3">
  <h4 class="card-title">Add New Fleet Vehicle</h4>
  <div>
    <a href="{{ route('fleet') }}" class="btn btn-outline-primary btn-sm">Fleet List</a>
    <a href="{{ route('operators') }}" class="btn btn-outline-secondary btn-sm">Operators</a>
    <a href="{{ route('routes') }}" class="btn btn-outline-info btn-sm">Routes</a>
    <a href="{{ route('collections') }}" class="btn btn-outline-success btn-sm">Collections</a>
    <a href="{{ route('penalties') }}" class="btn btn-outline-warning btn-sm">Penalties</a>
    <a href="{{ route('targets') }}" class="btn btn-outline-dark btn-sm">Targets</a>
    <a href="{{ route('maintenance') }}" class="btn btn-outline-danger btn-sm">Maintenance</a>
  </div>
</div>

         
        <form method="POST" action="{{ route('fleet.store') }}">
          @csrf
          <div class="row">
            <div class="col-md-6 form-group mb-3">
              <label for="vehicles_registration_number">Registration Number *</label>
              <input class="form-control" name="vehicles_registration_number" required type="text" placeholder="e.g. KDH 123A">
            </div>
            <div class="col-md-6 form-group mb-3">
              <label for="vehicles_make">Make</label>
              <input class="form-control" name="vehicles_make" type="text" placeholder="e.g. Toyota">
            </div>
            <div class="col-md-6 form-group mb-3">
              <label for="vehicles_model">Model</label>
              <input class="form-control" name="vehicles_model" type="text" placeholder="e.g. Hiace">
            </div>
            <div class="col-md-6 form-group mb-3">
              <label for="vehicles_year">Year</label>
              <input class="form-control" name="vehicles_year" type="number" placeholder="e.g. 2015">
            </div>
            <div class="col-md-6 form-group mb-3">
              <label for="vehicles_chassis_number">Chassis Number</label>
              <input class="form-control" name="vehicles_chassis_number" type="text">
            </div>
            <div class="col-md-6 form-group mb-3">
              <label for="vehicles_insurance_provider">Insurance Provider</label>
              <input class="form-control" name="vehicles_insurance_provider" type="text">
            </div>
            <div class="col-md-6 form-group mb-3">
              <label for="vehicles_insurance_expiry">Insurance Expiry</label>
              <input class="form-control" name="vehicles_insurance_expiry" type="date">
            </div>
            <div class="col-md-6 form-group mb-3">
              <label for="vehicles_last_inspection_date">Last Inspection Date</label>
              <input class="form-control" name="vehicles_last_inspection_date" type="date">
            </div>
            <div class="col-md-6 form-group mb-3">
              <label for="vehicles_psv_license_number">PSV License Number</label>
              <input class="form-control" name="vehicles_psv_license_number" type="text">
            </div>
            <div class="col-md-6 form-group mb-3">
              <label for="vehicles_psv_expiry">PSV Expiry</label>
              <input class="form-control" name="vehicles_psv_expiry" type="date">
            </div>
            <div class="col-md-6 form-group mb-3">
              <label for="vehicles_route_name">Route</label>
              <select class="form-control" name="vehicles_route_name">
                <option value="">-- Select Route --</option>
                @foreach($routes as $route)
                  <option value="{{ $route->route_name }}">{{ $route->route_name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6 form-group mb-3">
              <label for="vehicles_member_id">Owner (Member)</label>
              <select class="form-control" name="vehicles_member_id">
                <option value="">-- Select Member --</option>
                @foreach($members as $member)
                  <option value="{{ $member->member_id }}">{{ $member->member_name }} - {{ $member->member_sacco_id }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6 form-group mb-3">
              <label for="vehicles_status">Status</label>
              <select class="form-control" name="vehicles_status">
                <option value="pending_approval">Pending Approval</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="suspended">Suspended</option>
                <option value="under_maintenance">Under Maintenance</option>
                <option value="decommissioned">Decommissioned</option>
                <option value="blacklisted">Blacklisted</option>
              </select>
            </div>
            <div class="col-md-12">
              <button class="btn btn-primary">Submit</button>
              <a href="{{ route('fleet') }}" class="btn btn-secondary ms-2">Cancel</a>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection