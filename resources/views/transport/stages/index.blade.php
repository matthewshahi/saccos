@extends('layouts.app')

@section('content')
<div class="row">
    <div class="col-md-12 mb-3">
        <div class="card text-start">
            <div class="card-body">

                @include('transport._nav_links')

                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h4 class="card-title">Stages</h4>
                    <a href="{{ route('stages.create') }}" class="btn btn-primary btn-sm">
                        <i class="i-Add"></i> Add Stage
                    </a>
                </div>

                {{-- Search --}}
                <form method="GET" action="{{ route('stages.index') }}" class="mb-3">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <input type="text" 
                                   name="q" 
                                   value="{{ request('q') }}" 
                                   class="form-control"
                                   placeholder="Search stage or chair name...">
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
                                <th>Stage Name</th>
                                <th>Stage Chair</th>
                                <th>Added On</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($stages as $stage)
                                <tr>
                                    <td>{{ ($stages->currentPage() - 1) * $stages->perPage() + $loop->iteration }}</td>

                                    {{-- Stage Name --}}
                                    <td>{{ $stage->stage_name }}</td>

                                    {{-- Chair Details --}}
                                    <td>
                                        @if($stage->chair_name)
                                            <strong>{{ $stage->chair_name }}</strong><br>
                                            <small class="text-muted">{{ $stage->chair_phone }}</small>
                                        @else
                                            <span class="text-muted">— No Chair Assigned —</span>
                                        @endif
                                    </td>

                                    {{-- Date --}}
                                    <td>{{ \Carbon\Carbon::parse($stage->created_at)->format('d-M-Y') }}</td>

                                    {{-- Actions --}}
                                    <td>
                                        <a href="{{ route('stages.edit', $stage->id) }}" 
                                           class="text-success me-2">
                                            <i class="i-Pen-2 fw-bold"></i>
                                        </a>

                                        <a href="{{ route('stages.delete', $stage->id) }}"
                                           onclick="return confirm('Delete this stage?')"
                                           class="text-danger">
                                            <i class="i-Close-Window fw-bold"></i>
                                        </a>
                                    </td>
                                </tr>

                            @empty
                                <tr>
                                    <td colspan="5" class="text-center">No stages found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                {{-- Pagination --}}
                <div class="mt-3">
                    {{ $stages->withQueryString()->links('pagination::bootstrap-5') }}
                </div>

            </div>
        </div>
    </div>
</div>
@endsection
