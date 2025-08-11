@extends('layouts.app')

@section('content')
@include('transport._nav_links')

<div class="row">
  <div class="col-md-12">
    <form method="GET" action="{{ route('operators') }}" class="mb-3">
      <div class="input-group">
        <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="Search by name, ID, or phone">
        <button type="submit" class="btn btn-outline-primary"><i class="nav-icon i-Magnifi-Glass1"></i> Search</button>
      </div>
    </form>

    <div class="card o-hidden mb-4">
      <div class="card-header d-flex align-items-center justify-content-between">
        <h4 class="card-title m-0">Operators List</h4>
        <a href="{{ route('operators.create') }}" class="btn btn-sm btn-primary">+ Add Operator</a>
      </div>

      <div class="card-body table-responsive">
        <table class="table table-hover table-bordered table-sm text-center">
          <thead class="bg-light">
            <tr>
              <th>#</th>
              <th>Name</th>
              <th>Phone</th>
              <th>ID</th>
              <th>Gender</th>
              <th>Type</th>
              <th>Status</th>
              <th>Reason</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
            @forelse($operators as $index => $operator)
              <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $operator->full_name }}</td>
                <td>{{ $operator->phone }}</td>
                <td>{{ $operator->national_id }}</td>
                <td>{{ ucfirst($operator->gender) }}</td>
                <td><span class="badge bg-info">{{ ucfirst($operator->operator_type) }}</span></td>
                <td>
                  <span class="badge bg-{{ $operator->status === 'active' ? 'success' : 'secondary' }}">
                    {{ ucfirst($operator->status) }}
                  </span>
                </td>
                <td>
                  @if($operator->status_reason)
                    <small class="text-muted">{{ ucfirst($operator->status_reason) }}</small>
                  @endif
                </td>
                <td>
                  <a class="text-success me-2" href="{{ route('operators.edit', $operator->id) }}">
                    <i class="nav-icon i-Pen-2 fw-bold"></i>
                  </a>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="9" class="text-center">No operators found.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
@endsection