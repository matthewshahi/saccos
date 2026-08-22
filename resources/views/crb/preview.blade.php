@extends('layouts.app')

@section('content')
<div class="breadcrumb">
    <h1>CRB Report Preview</h1>
    <ul>
        <li><a href="{{ url('/dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('reports.crb.index') }}">CRB Reports</a></li>
        <li>{{ $preview['code'] }} Preview</li>
    </ul>
</div>

<div class="separator-breadcrumb border-top"></div>

@include('crb.partials.nav')

@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="row">
    <div class="col-lg-8">
        <div class="card o-hidden mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h3 class="card-title m-0">
                    {{ $preview['code'] }} — {{ $preview['type']['title'] }}
                </h3>
                <span class="badge bg-primary">{{ $preview['type']['frequency'] }}</span>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <div class="text-muted small">Normalised Report Date</div>
                        <strong>{{ $preview['date']->format('d M Y') }}</strong>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="text-muted small">Source Candidates</div>
                        <strong>{{ number_format($preview['candidate_count']) }}</strong>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="text-muted small">Previewed Records</div>
                        <strong>{{ number_format($preview['sample_count']) }} / {{ number_format($preview['sample_limit']) }}</strong>
                    </div>
                </div>

                <div class="alert alert-info mb-0">
                    This is a live preview only. Nothing has been inserted into the CRB report tables. Record counts below are for the displayed sample; generation validates and freezes the complete dataset.
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card o-hidden mb-4">
            <div class="card-header">
                <h3 class="card-title m-0">Preview Sample</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered table-sm mb-0">
                    <tbody>
                        <tr>
                            <th>Valid</th>
                            <td class="text-end">{{ number_format($preview['sample_counts']['VALID'] ?? 0) }}</td>
                        </tr>
                        <tr>
                            <th>Warning</th>
                            <td class="text-end">{{ number_format($preview['sample_counts']['WARNING'] ?? 0) }}</td>
                        </tr>
                        <tr>
                            <th>Error</th>
                            <td class="text-end">{{ number_format($preview['sample_counts']['ERROR'] ?? 0) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@if (empty($preview['readiness']['ready']))
    <div class="alert alert-warning">
        <strong>CRB configuration is not ready for finalisation.</strong>
        <ul class="mb-0 mt-2">
            @foreach ($preview['readiness']['issues'] as $issue)
                <li>{{ $issue['message'] ?? 'Configuration issue' }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="card o-hidden mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title m-0">Preview Records</h3>

        @if ($preview['candidate_count'] > 0)
            <form method="POST" action="{{ route('reports.crb.generate') }}"
                  onsubmit="return confirm('Generate and freeze a new CRB draft version from this report date?');">
                @csrf
                <input type="hidden" name="report_code" value="{{ $preview['code'] }}">
                <input type="hidden" name="report_date" value="{{ $preview['date']->format('Y-m-d') }}">
                <button type="submit" class="btn btn-sm btn-success">
                    Generate Full Draft
                </button>
            </form>
        @endif
    </div>

    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th style="min-width: 80px;">Status</th>
                        <th style="min-width: 80px;">Member</th>
                        <th style="min-width: 80px;">Loan</th>
                        <th style="min-width: 220px;">Summary</th>
                        <th style="min-width: 320px;">Validation Issues</th>
                        <th style="min-width: 420px;">Pipe Record</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($preview['records'] as $record)
                        @php
                            $status = $record['validation_status'] ?? 'VALID';
                            $badge = $status === 'ERROR'
                                ? 'bg-danger'
                                : ($status === 'WARNING' ? 'bg-warning' : 'bg-success');
                        @endphp
                        <tr>
                            <td><span class="badge {{ $badge }}">{{ $status }}</span></td>
                            <td>{{ $record['member_id'] ?? '-' }}</td>
                            <td>{{ $record['loan_id'] ?? '-' }}</td>
                            <td>
                                @foreach (($record['summary'] ?? []) as $key => $value)
                                    <div>
                                        <span class="text-muted">{{ ucwords(str_replace('_', ' ', $key)) }}:</span>
                                        @if (is_numeric($value) && str_contains($key, 'amount') || is_numeric($value) && str_contains($key, 'balance'))
                                            {{ number_format((float) $value, 2) }}
                                        @else
                                            {{ is_scalar($value) ? $value : json_encode($value) }}
                                        @endif
                                    </div>
                                @endforeach
                            </td>
                            <td>
                                @forelse (($record['issues'] ?? []) as $issue)
                                    <div class="mb-1">
                                        <span class="badge {{ ($issue['level'] ?? '') === 'ERROR' ? 'bg-danger' : 'bg-warning' }}">
                                            {{ $issue['level'] ?? 'INFO' }}
                                        </span>
                                        <strong>{{ $issue['code'] ?? '' }}</strong>
                                        <div class="small text-muted">{{ $issue['message'] ?? '' }}</div>
                                    </div>
                                @empty
                                    <span class="text-success">No validation issues.</span>
                                @endforelse
                            </td>
                            <td style="max-width: 520px;">
                                <div style="overflow-x:auto; white-space:nowrap;">
                                    <code>{{ $record['pipe_row'] ?? '' }}</code>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                No source records were found for this report/date.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card o-hidden mb-4">
    <div class="card-header">
        <h3 class="card-title m-0">DST Field Order — {{ $preview['code'] }}</h3>
    </div>
    <div class="card-body">
        <div class="row">
            @foreach (($preview['records'][0]['fields'] ?? []) as $value)
                @php $idx = $loop->index; @endphp
                <div class="col-lg-6 mb-2">
                    <div class="border rounded p-2">
                        <strong>{{ $idx + 1 }}. {{ $preview['field_names'][$idx] ?? 'Field' }}</strong>
                        <div class="small text-muted text-break">
                            {{ $value === '' ? '— blank —' : $value }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if (empty($preview['records']))
            <div class="text-muted">Generate a preview with records to inspect the field values.</div>
        @endif
    </div>
</div>
@endsection
