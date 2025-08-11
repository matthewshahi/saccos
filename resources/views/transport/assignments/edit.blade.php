@extends('layouts.app')

@section('content')
<div class="card">
    @include('transport._nav_links')
    <div class="card-header d-flex justify-content-between">
        <h5 class="mb-0">Edit Vehicle Assignment</h5>
        <a href="{{ route('assignments.index') }}" class="btn btn-sm btn-outline-secondary">← Back</a>
    </div>

    <div class="card-body">
        <form method="POST" action="{{ route('assignments.update', $assignment->id) }}">
            @csrf

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="v_assignment_operator_id" class="form-label">Operator</label>
                    <select class="form-select" name="v_assignment_operator_id" required>
                        @foreach($operators as $o)
                            <option value="{{ $o->id }}" {{ $assignment->v_assignment_operator_id == $o->id ? 'selected' : '' }}>
                                {{ $o->full_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="v_assignment_vehicle_id" class="form-label">Vehicle</label>
                    <select class="form-select" name="v_assignment_vehicle_id" required>
                        @foreach($vehicles as $v)
                            <option value="{{ $v->id }}" {{ $assignment->v_assignment_vehicle_id == $v->id ? 'selected' : '' }}>
                                {{ $v->vehicles_registration_number }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <label class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="v_assignment_start_date" value="{{ $assignment->v_assignment_start_date }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">End Date (optional)</label>
                    <input type="date" class="form-control" name="v_assignment_end_date" value="{{ $assignment->v_assignment_end_date }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select class="form-select" name="v_assignment_status" required>
                        @foreach(['assigned', 'suspended', 'ended'] as $status)
                            <option value="{{ $status }}" {{ $assignment->v_assignment_status == $status ? 'selected' : '' }}>
                                {{ ucfirst($status) }}
                            </option>
                        @endforeach
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Update Assignment</button>
        </form>
    </div>
</div>
@endsection