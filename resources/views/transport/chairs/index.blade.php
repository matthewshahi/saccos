@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-md-12 mb-3">
        <div class="card text-start">
            <div class="card-body">

                @include('transport._nav_links')

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="card-title">Stage Chairs</h4>
                    <a href="{{ route('chairs.create') }}" class="btn btn-primary btn-sm">
                        <i class="i-Add"></i> Add Chair
                    </a>
                </div>

                {{-- Search --}}
                <form method="GET" action="{{ route('chairs.index') }}" class="mb-3">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <input type="text" name="q" value="{{ request('q') }}" 
                                   class="form-control"
                                   placeholder="Search chair or stage...">
                        </div>
                        <div class="col-auto">
                            <button class="btn btn-outline-primary btn-sm">
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
                                <th>Chair Name</th>
                                <th>Phone</th>
                                <th>Stage</th>
                                <th>Added On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse($chairs as $chair)
                                <tr>
                                    <td>{{ ($chairs->currentPage()-1) * $chairs->perPage() + $loop->iteration }}</td>
                                    <td>{{ $chair->chair_name }}</td>
                                    <td>{{ $chair->chair_phone ?? '-' }}</td>
                                    <td>{{ $chair->stage_name }}</td>
                                    <td>{{ \Carbon\Carbon::parse($chair->created_at)->format('d-M-Y') }}</td>
                                    <td>
                                        <a href="{{ route('chairs.edit', $chair->id) }}" class="text-success me-2">
                                            <i class="i-Pen-2 fw-bold"></i>
                                        </a>

                                        <a href="{{ route('chairs.delete', $chair->id) }}"
                                           onclick="return confirm('Delete this stage chair?')"
                                           class="text-danger">
                                            <i class="i-Close-Window fw-bold"></i>
                                        </a>
                                    </td>
                                </tr>

                            @empty
                                <tr>
                                    <td colspan="6" class="text-center">No chairs found.</td>
                                </tr>
                            @endforelse
                        </tbody>

                    </table>
                </div>

                <div class="mt-3">
                    {{ $chairs->withQueryString()->links('pagination::bootstrap-5') }}
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
