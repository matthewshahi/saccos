@extends('layouts.app')

@section('content')
   @include('transport._nav_links')
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Penalties</h5>
        <a href="{{ route('penalties.create') }}" class="btn btn-primary btn-sm">+ Add New Penalty</a>
    </div>

    <div class="card-body table-responsive">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <table class="table table-bordered table-hover table-sm">
            <thead class="table-light">
                <tr>
                    <th>Date</th>
                    <th>Member</th>
                    <th>Description</th>
                    <th>Amount (KES)</th>
                    
                </tr>
            </thead>
            <tbody>
                @forelse($penalties as $penalty)
                <tr>
                    <td>{{ $penalty->penalty_date }}</td>
                    <td>{{ $penalty->member_name }}</td>
                    <td>{{ $penalty->penalty_description }}</td>
                    <td>KES {{ number_format($penalty->penalty_amount, 2) }}</td>
                     
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="text-center text-muted">No penalties recorded.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection