@extends('layouts.app')

@section('content')
<div class="row">
      
    <div class="col-md-12 mb-3">
        <div class="card text-start">
            <div class="card-body">
                  @include('transport._nav_links')
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="card-title">Fleet Vehicles</h4>
                    <a href="{{ url('/transport/fleet/create') }}" class="btn btn-primary btn-sm">
                        <i class="i-Add"></i> Add New Vehicle
                    </a>
                </div>

                {{-- Search Form --}}
                <form method="GET" action="{{ url('/transport/fleet') }}" class="mb-3">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <input type="text" name="q" value="{{ request('q') }}" class="form-control"
                                placeholder="Search reg no, make, model, route, or status">
                        </div>
                        <div class="col-auto">
                            <button type="submit" class="btn btn-outline-primary btn-sm">
                                <i class="i-Magnifi-Glass1"></i> Search
                            </button>
                        </div>
                    </div>
                </form>

                {{-- Table --}}
                <div class="table-responsive">
                    <table class="table table-bordered table-hover table-sm">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Reg No.</th>
                                <th>Make/Model</th>
                                <th>Route</th>
                                <th>Owner</th>
                                <th>Status</th>
                                <th>Insurance Expiry</th>
                                <th>Inspection Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($vehicles as $v)
                                <tr>
                                    <td>{{ ($vehicles->currentPage() - 1) * $vehicles->perPage() + $loop->iteration }}</td>
                                    <td>{{ $v->vehicles_registration_number ?? '-' }}</td>
                                    <td>{{ $v->vehicles_make }} {{ $v->vehicles_model }}</td>
                                    <td>{{ $v->vehicles_route_name ?? '-' }}</td>
                                    <td>
                                        @if($v->vehicles_member_id)
                                            <a href="{{ url('/members/edit/' . $v->vehicles_member_id) }}" target="_blank">
                                                {{ $v->member_name ?? 'Member #' . $v->vehicles_member_id }}
                                            </a><br>
                                            <small class="text-muted">{{ $v->member_phone_no }}</small>
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td>
                                        @php
                                            $status = $v->vehicles_status;
                                            $color = match ($status) {
                                                'active' => 'success',
                                                'suspended' => 'warning',
                                                'inactive' => 'secondary',
                                                'pending_approval' => 'info',
                                                'under_maintenance' => 'primary',
                                                'decommissioned', 'blacklisted' => 'danger',
                                                default => 'light',
                                            };
                                        @endphp
                                        <span class="badge bg-{{ $color }}">
                                            {{ ucfirst(str_replace('_', ' ', $status)) }}
                                        </span>
                                    </td>
                                    <td>
                                        {{ $v->vehicles_insurance_expiry ? \Carbon\Carbon::parse($v->vehicles_insurance_expiry)->format('d-M-Y') : '-' }}
                                    </td>
                                    <td>
                                        {{ $v->vehicles_last_inspection_date ? \Carbon\Carbon::parse($v->vehicles_last_inspection_date)->format('d-M-Y') : '-' }}
                                    </td>
                                    <td>
                                        <a href="{{ url('/transport/fleet/edit/' . $v->id) }}" class="text-success me-2">
                                            <i class="nav-icon i-Pen-2 fw-bold"></i>
                                        </a>
                                        <a href="{{ url('/transport/fleet/delete/' . $v->id) }}" class="text-danger me-2" onclick="return confirm('Are you sure?')">
                                            <i class="nav-icon i-Close-Window fw-bold"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center">No vehicles found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="mt-3">
                    {{ $vehicles->withQueryString()->links('pagination::bootstrap-5') }}
                </div>

            </div>
        </div>
    </div>
</div>
@endsection