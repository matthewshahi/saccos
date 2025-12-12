@extends('layouts.app')

@section('content')
<div class="container py-4">
    <h2 class="mb-4">KAS Member Import</h2>

    {{-- Stats --}}
    <div class="mb-4">
        <h5>Current Stats</h5>
        <ul>
            <li>Staging rows: <strong>{{ $stats['staging_count'] }}</strong></li>
            <li>SACCO Members: <strong>{{ $stats['members_count'] }}</strong></li>
            <li>Companies: <strong>{{ $stats['companies_count'] }}</strong></li>
            <li>Departments: <strong>{{ $stats['departments_count'] }}</strong></li>
        </ul>
    </div>

    {{-- Run Button --}}
    <div class="mb-4">
        <form method="POST" action="{{ route('kasmember.run') }}">
            @csrf
            <button type="submit" class="btn btn-primary"
                    onclick="return confirm('Run full import now? This will create members and contributions.');">
                Run Full Import
            </button>
        </form>
    </div>

    {{-- Summary --}}
    @if($summary)
        <hr>
        <h5>Last Import Summary</h5>
        <ul>
            <li>Total staging rows processed: <strong>{{ $summary['rows_processed'] }}</strong></li>
            <li>Companies created: <strong>{{ $summary['companies_created'] }}</strong></li>
            <li>Departments created: <strong>{{ $summary['departments_created'] }}</strong></li>
            <li>Members created: <strong>{{ $summary['members_created'] }}</strong></li>
            <li>Members matched by ADM: <strong>{{ $summary['members_matched_adm'] }}</strong></li>
            <li>Members matched by name: <strong>{{ $summary['members_matched_name'] }}</strong></li>
            <li>Shares inserted: <strong>{{ $summary['shares_inserted'] }}</strong></li>
            <li>Capital inserted: <strong>{{ $summary['capital_inserted'] }}</strong></li>
            <li>FOSA inserted: <strong>{{ $summary['fosa_inserted'] }}</strong></li>
            <li>Skipped (no company): <strong>{{ $summary['skipped_no_company'] }}</strong></li>
            <li>Skipped (no name): <strong>{{ $summary['skipped_no_name'] }}</strong></li>
        </ul>
    @endif
</div>
@endsection
