@extends('layouts.app')

@section('content')
<div class="card">
        @include('transport._nav_links')
    <div class="card-header d-flex justify-content-between">
        
        <h5 class="mb-0">Vehicle Assignments</h5>
        

        <a href="{{ route('assignments.create') }}" class="btn btn-primary btn-sm">+ Assign Vehicle</a>
    </div>
    <div class="card-body table-responsive">
        <form method="GET" action="{{ route('assignments.index') }}" class="mb-3">
        <div class="row g-2 align-items-center">
            <div class="col-md-4">
                <input type="text" name="q" class="form-control form-control-sm" placeholder="Search by operator or vehicle..." value="{{ request('q') }}">
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary">Search</button>
            </div>
        </div>
    </form>
        <table class="table table-bordered table-hover table-sm">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Operator</th>
                    <th>Vehicle</th>
                    <th>Status</th>
                    <th>Start Date</th>
                    <th>End Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($assignments as $index => $a)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $a->operator_name }}</td>
                        <td>{{ $a->vehicle_reg }}</td>
                        <td><span class="badge bg-secondary">{{ ucfirst($a->v_assignment_status) }}</span></td>
                        <td>{{ $a->v_assignment_start_date }}</td>
                        <td>{{ $a->v_assignment_end_date ?? '-' }}</td>
                        <td>
                            <a href="{{ route('assignments.edit', $a->id) }}" class="btn btn-sm btn-outline-info">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center">No assignments found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection