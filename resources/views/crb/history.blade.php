@extends('layouts.app')

@section('content')
<div class="breadcrumb">
    <h1>CRB Report History</h1>
    <ul>
        <li><a href="{{ url('/dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('reports.crb.index') }}">CRB Reports</a></li>
        <li>History</li>
    </ul>
</div>

<div class="separator-breadcrumb border-top"></div>

@include('crb.partials.nav')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card o-hidden mb-4">
    <div class="card-header">
        <h3 class="card-title m-0">Filters</h3>
    </div>
    <div class="card-body">
        <form method="GET" action="{{ route('reports.crb.history') }}" class="row g-3 align-items-end">
            <div class="col-md-2">
                <label class="form-label">Report Type</label>
                <select name="code" class="form-control">
                    <option value="">All</option>
                    @foreach ($types as $code => $type)
                        <option value="{{ $code }}" @selected(request('code') === $code)>
                            {{ $code }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label">Status</label>
                <select name="status" class="form-control">
                    <option value="">All</option>
                    @foreach (['DRAFT', 'VALIDATED', 'FINALISED', 'SUBMITTED'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>
                            {{ $status }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label class="form-label">Date From</label>
                <input type="date" name="date_from" value="{{ request('date_from') }}" class="form-control">
            </div>

            <div class="col-md-3">
                <label class="form-label">Date To</label>
                <input type="date" name="date_to" value="{{ request('date_to') }}" class="form-control">
            </div>

            <div class="col-md-2">
                <button type="submit" class="btn btn-primary">Filter</button>
                <a href="{{ route('reports.crb.history') }}" class="btn btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>
</div>

<div class="card o-hidden mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title m-0">Versioned Report Runs</h3>
        <span class="text-muted">{{ number_format($reports->total()) }} result(s)</span>
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Frequency</th>
                        <th>Report Date</th>
                        <th>Version</th>
                        <th>Status</th>
                        <th class="text-end">Records</th>
                        <th class="text-end">Valid</th>
                        <th class="text-end">Warnings</th>
                        <th class="text-end">Errors</th>
                        <th>File</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($reports as $report)
                        @php
                            $statusBadge = match ($report->status) {
                                'SUBMITTED' => 'bg-success',
                                'FINALISED', 'FINALIZED' => 'bg-primary',
                                'VALIDATED' => 'bg-info',
                                default => $report->error_records > 0 ? 'bg-danger' : 'bg-warning',
                            };
                        @endphp
                        <tr>
                            <td>{{ $report->id }}</td>
                            <td>
                                <strong>{{ $report->code }}</strong>
                                <div class="small text-muted">{{ $types[$report->code]['title'] ?? '' }}</div>
                            </td>
                            <td>{{ $report->frequency }}</td>
                            <td>{{ optional($report->report_date)->format('d M Y') }}</td>
                            <td>{{ str_pad((string) $report->version, 3, '0', STR_PAD_LEFT) }}</td>
                            <td><span class="badge {{ $statusBadge }}">{{ $report->status }}</span></td>
                            <td class="text-end">{{ number_format($report->total_records) }}</td>
                            <td class="text-end">{{ number_format($report->valid_records) }}</td>
                            <td class="text-end">{{ number_format($report->warning_records) }}</td>
                            <td class="text-end">{{ number_format($report->error_records) }}</td>
                            <td>
                                @if ($report->file_name)
                                    <code>{{ $report->file_name }}</code>
                                @else
                                    <span class="text-muted">Draft only</span>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('reports.crb.show', $report->id) }}" class="btn btn-sm btn-outline-primary">
                                    Open
                                </a>
                                @if (in_array($report->status, ['FINALISED', 'FINALIZED', 'SUBMITTED'], true))
                                    <a href="{{ route('reports.crb.download', $report->id) }}" class="btn btn-sm btn-outline-success">
                                        Download
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="text-center text-muted py-4">
                                No CRB report history matches the selected filters.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if ($reports->hasPages())
        <div class="card-footer">
            {{ $reports->links() }}
        </div>
    @endif
</div>
@endsection
