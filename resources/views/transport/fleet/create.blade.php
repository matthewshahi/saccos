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

{{-- SESSION & VALIDATION FEEDBACK --}}
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fa fa-check-circle"></i> {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa fa-exclamation-triangle"></i> {{ session('error') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <strong>There were errors with your submission:</strong>
        <ul class="mb-0 mt-1">
            @foreach ($errors->all() as $error)
                <li>• {{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
@endif
{{-- END FEEDBACK --}}

         
        <form method="POST" action="{{ route('fleet.store') }}">
    @csrf

    <div class="row">
        <div class="col-md-6 form-group mb-3">
            <label>Registration Number *</label>
            <input class="form-control" name="vehicles_registration_number"
                   value="{{ old('vehicles_registration_number') }}"
                   required type="text" placeholder="e.g. KDH 123A">
        </div>

        <div class="col-md-6 form-group mb-3">
            <label>Make</label>
            <input class="form-control" name="vehicles_make"
                   value="{{ old('vehicles_make') }}"
                   type="text" placeholder="e.g. Toyota">
        </div>

        <div class="col-md-6 form-group mb-3">
            <label>Model</label>
            <input class="form-control" name="vehicles_model"
                   value="{{ old('vehicles_model') }}"
                   type="text" placeholder="e.g. Hiace">
        </div>

        <div class="col-md-6 form-group mb-3">
            <label>Year</label>
            <input class="form-control" name="vehicles_year"
                   value="{{ old('vehicles_year') }}"
                   type="number" placeholder="e.g. 2015">
        </div>

        <div class="col-md-6 form-group mb-3">
            <label>Chassis Number</label>
            <input class="form-control" name="vehicles_chassis_number"
                   value="{{ old('vehicles_chassis_number') }}"
                   type="text">
        </div>

        <div class="col-md-6 form-group mb-3">
            <label>Insurance Provider</label>
            <input class="form-control" name="vehicles_insurance_provider"
                   value="{{ old('vehicles_insurance_provider') }}"
                   type="text">
        </div>

        <div class="col-md-6 form-group mb-3">
            <label>Insurance Expiry</label>
            <input class="form-control" name="vehicles_insurance_expiry"
                   value="{{ old('vehicles_insurance_expiry') }}"
                   type="date">
        </div>

        <div class="col-md-6 form-group mb-3">
            <label>Last Inspection Date</label>
            <input class="form-control" name="vehicles_last_inspection_date"
                   value="{{ old('vehicles_last_inspection_date') }}"
                   type="date">
        </div>

        <div class="col-md-6 form-group mb-3">
            <label>PSV License Number</label>
            <input class="form-control" name="vehicles_psv_license_number"
                   value="{{ old('vehicles_psv_license_number') }}"
                   type="text">
        </div>

        <div class="col-md-6 form-group mb-3">
            <label>PSV Expiry</label>
            <input class="form-control" name="vehicles_psv_expiry"
                   value="{{ old('vehicles_psv_expiry') }}"
                   type="date">
        </div>

        <div class="col-md-6 form-group mb-3">
            <label>Route</label>
            <select class="form-control" name="vehicles_route_name">
                <option value="">-- Select Route --</option>
                @foreach($routes as $route)
                    <option value="{{ $route->route_name }}"
                        {{ old('vehicles_route_name') == $route->route_name ? 'selected' : '' }}>
                        {{ $route->route_name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-6 form-group mb-3">
            <label>Owner (Member)</label>
            <select class="form-control" name="vehicles_member_id">
                <option value="">-- Select Member --</option>
                @foreach($members as $member)
                    <option value="{{ $member->member_id }}"
                        {{ old('vehicles_member_id') == $member->member_id ? 'selected' : '' }}>
                        {{ $member->member_name }} - {{ $member->member_sacco_id }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="col-md-6 form-group mb-3">
            <label>Status</label>
            <select class="form-control" name="vehicles_status">
                @php $status = old('vehicles_status'); @endphp
                <option value="pending_approval" {{ $status=='pending_approval'?'selected':'' }}>Pending Approval</option>
                <option value="active" {{ $status=='active'?'selected':'' }}>Active</option>
                <option value="inactive" {{ $status=='inactive'?'selected':'' }}>Inactive</option>
                <option value="suspended" {{ $status=='suspended'?'selected':'' }}>Suspended</option>
                <option value="under_maintenance" {{ $status=='under_maintenance'?'selected':'' }}>Under Maintenance</option>
                <option value="decommissioned" {{ $status=='decommissioned'?'selected':'' }}>Decommissioned</option>
                <option value="blacklisted" {{ $status=='blacklisted'?'selected':'' }}>Blacklisted</option>
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