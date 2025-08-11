@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-md-12">

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

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="card-title">Matatu Routes</h4>
      <a href="{{ route('routes.create') }}" class="btn btn-primary btn-sm">+ Add Route</a>
    </div>

    <div class="card">
      <div class="card-body table-responsive">
        <table class="table table-bordered table-hover">
          <thead>
            <tr>
              <th>#</th>
              <th>Route Name</th>
              <th>Start</th>
              <th>End</th>
              <th>Status</th>
              <th>Created At</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            @forelse($routes as $index => $route)
              <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $route->route_name }}</td>
                <td>{{ $route->route_start }}</td>
                <td>{{ $route->route_end }}</td>
                <td>
                  <span class="badge bg-{{ $route->status === 'active' ? 'success' : 'secondary' }}">
                    {{ ucfirst($route->status) }}
                  </span>
                </td>
                <td>{{ \Carbon\Carbon::parse($route->created_at)->format('Y-m-d') }}</td>
                <td>
                  <a href="{{ route('routes.edit', $route->id) }}" class="btn btn-sm btn-outline-info">Edit</a>
                  <a href="{{ route('routes.delete', $route->id) }}"
                     onclick="return confirm('Are you sure you want to delete this route?')"
                     class="btn btn-sm btn-outline-danger">Delete</a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="7" class="text-center">No routes found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>
@endsection