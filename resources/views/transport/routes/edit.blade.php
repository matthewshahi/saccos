@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-md-12">
    <!-- ✅ Top Navigation Buttons -->
    <div class="mb-3 d-flex justify-content-end flex-wrap gap-1">
      <a href="{{ route('fleet') }}" class="btn btn-outline-primary btn-sm">Fleet List</a>
      <a href="{{ route('operators') }}" class="btn btn-outline-secondary btn-sm">Operators</a>
      <a href="{{ route('routes') }}" class="btn btn-outline-info btn-sm">Routes</a>
      <a href="{{ route('collections') }}" class="btn btn-outline-success btn-sm">Collections</a>
      <a href="{{ route('penalties') }}" class="btn btn-outline-warning btn-sm">Penalties</a>
      <a href="{{ route('targets') }}" class="btn btn-outline-dark btn-sm">Targets</a>
      <a href="{{ route('maintenance') }}" class="btn btn-outline-danger btn-sm">Maintenance</a>
    </div>

    <div class="card mb-4">
      <div class="card-body">
        <div class="card-title mb-3">Edit Matatu Route</div>
        <form action="{{ route('routes.update', $route->id) }}" method="POST">
          @csrf
          <div class="row">
            <div class="col-md-6 form-group mb-3">
              <label for="route_name">Route Name</label>
              <input type="text" name="route_name" class="form-control" value="{{ old('route_name', $route->route_name) }}" required>
            </div>

            <div class="col-md-6 form-group mb-3">
              <label for="route_start">Start Point</label>
              <input type="text" name="route_start" class="form-control" value="{{ old('route_start', $route->route_start) }}" required>
            </div>

            <div class="col-md-6 form-group mb-3">
              <label for="route_end">End Point</label>
              <input type="text" name="route_end" class="form-control" value="{{ old('route_end', $route->route_end) }}" required>
            </div>

            <div class="col-md-6 form-group mb-3">
              <label for="route_distance_km">Distance (KM)</label>
              <input type="number" name="route_distance_km" class="form-control" step="0.01" value="{{ old('route_distance_km', $route->route_distance_km) }}">
            </div>

            <div class="col-md-6 form-group mb-3">
              <label for="status">Status</label>
              <select name="status" class="form-control" required>
                <option value="active" {{ $route->status === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ $route->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
              </select>
            </div>

            <div class="col-md-12">
              <div class="d-flex justify-content-between">
                <a href="{{ route('routes') }}" class="btn btn-secondary">Back</a>
                <button type="submit" class="btn btn-primary">Update Route</button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection