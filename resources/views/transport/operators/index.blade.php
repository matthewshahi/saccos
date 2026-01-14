@extends('layouts.app')

@section('content')
@include('transport._nav_links')

<div class="d-flex gap-2 mb-3">
    <a href="{{ route('operators.export.excel') }}"
       class="btn btn-sm btn-success">
        <i class="i-File-Excel"></i> Excel
    </a>

    <a href="{{ route('operators.export.pdf', request()->query()) }}"
       class="btn btn-sm btn-danger">
        <i class="i-File-PDF"></i> PDF
    </a>
</div>

<div class="row">
    <div class="col-md-12">

        <form method="GET" action="{{ route('operators') }}" class="mb-3">
            <div class="input-group">
                <input
                    type="text"
                    name="q"
                    value="{{ request('q') }}"
                    class="form-control"
                    placeholder="Search by name, ID, phone, vehicle…">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="nav-icon i-Magnifi-Glass1"></i> Search
                </button>
            </div>
        </form>

        <div class="card o-hidden mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h4 class="card-title m-0">Operators List</h4>
                <a href="{{ route('operators.create') }}" class="btn btn-sm btn-primary">
                    + Add Operator
                </a>
            </div>

            <div class="card-body table-responsive">
                <table class="table table-hover table-bordered table-sm text-center">
                    <thead class="bg-light">
                        <tr>
                            <th>#</th>
                            <th>Operator</th>
                            <th>Phone</th>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Introduced By</th>
                            <th>Stage</th>
                            <th>Chair</th>
                            <th>Vehicle</th>
                            <th>Owner</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>
                    @forelse($operators as $index => $operator)
                        <tr>
                            <td>{{ $index + 1 }}</td>

                            <td class="text-start">
                                <strong>{{ $operator->operator_name }}</strong>
                                <div class="small text-muted">
                                    {{ ucfirst($operator->operator_type) }}
                                </div>
                            </td>

                            <td>{{ $operator->operator_phone }}</td>
                            <td>{{ $operator->operator_national_id }}</td>

                            <td>
                                <span class="badge bg-info">
                                    {{ ucfirst($operator->operator_type) }}
                                </span>
                            </td>

                            <td>
                                <span class="badge bg-{{ $operator->operator_status === 'active' ? 'success' : 'secondary' }}">
                                    {{ ucfirst($operator->operator_status) }}
                                </span>
                            </td>

                            <td class="text-start">
                                {{ $operator->introduced_by_member_name ?? '-' }}
                                @if(!empty($operator->introduced_by_member_phone))
                                    <div class="small text-muted">
                                        {{ $operator->introduced_by_member_phone }}
                                    </div>
                                @endif
                            </td>

                            <td>
                                {{ $operator->stage_name ?? '-' }}
                            </td>

                            <td class="text-start">
                                {{ $operator->stage_chair_name ?? '-' }}
                                @if(!empty($operator->stage_chair_phone))
                                    <div class="small text-muted">
                                        {{ $operator->stage_chair_phone }}
                                    </div>
                                @endif
                            </td>

                            <td>
                                {{ $operator->vehicles_registration_number ?? '-' }}
                                @if(!empty($operator->vehicle_status))
                                    <div class="small text-muted">
                                        {{ $operator->vehicle_status }}
                                    </div>
                                @endif
                            </td>

                            <td>
                                {{ $operator->vehicle_owner_name ?? '-' }}
                            </td>

                            <td>
                                <a class="text-success"
                                   href="{{ route('operators.edit', $operator->operator_id) }}">
                                    <i class="nav-icon i-Pen-2 fw-bold"></i>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="text-center text-muted">
                                No operators found.
                            </td>
                        </tr>
                    @endforelse
                    </tbody>

                </table>
            </div>
        </div>
    </div>
</div>
@endsection
