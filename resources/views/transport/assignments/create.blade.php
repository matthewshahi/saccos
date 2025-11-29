@extends('layouts.app')

@section('content')
<div class="card">
    @include('transport._nav_links')
    <div class="card-header d-flex justify-content-between">
        <h5 class="mb-0">Assign Vehicle to Operator</h5>
        <a href="{{ route('assignments.index') }}" class="btn btn-sm btn-outline-secondary">← Back to Assignments</a>
    </div>

    {{-- ===================== SESSION ALERTS ===================== --}}
    <div class="px-3 mt-3">

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

    </div>
    {{-- ========================================================== --}}

    <div class="card-body">
        <form method="POST" action="{{ route('assignments.store') }}">
            @csrf

            <div class="row mb-3">
                <div class="col-md-6">
                    <label for="v_assignment_operator_id" class="form-label">Operator</label>
                    <select class="form-select" name="v_assignment_operator_id" required>
                        <option value="">-- Select Operator --</option>
                        @foreach($operators as $o)
                            <option value="{{ $o->id }}">{{ $o->full_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label for="v_assignment_vehicle_id" class="form-label">Vehicle</label>
                    <select class="form-select" name="v_assignment_vehicle_id" required>
                        <option value="">-- Select Vehicle --</option>
                        @foreach($vehicles as $v)
                            <option value="{{ $v->id }}">{{ $v->vehicles_registration_number }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <label for="v_assignment_start_date" class="form-label">Start Date</label>
                    <input type="date" class="form-control" name="v_assignment_start_date" required>
                </div>
                <div class="col-md-4">
                    <label for="v_assignment_end_date" class="form-label">End Date (optional)</label>
                    <input type="date" class="form-control" name="v_assignment_end_date">
                </div>
                <div class="col-md-4">
                    <label for="v_assignment_status" class="form-label">Status</label>
                    <select class="form-select" name="v_assignment_status" required>
                        <option value="assigned">Assigned</option>
                        <option value="suspended">Suspended</option>
                        <option value="ended">Ended</option>
                    </select>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Assign Vehicle</button>
        </form>
    </div>
</div>
@endsection