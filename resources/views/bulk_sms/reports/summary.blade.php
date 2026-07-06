@extends('layouts.app')

@section('content')
<div class="breadcrumb">
    <h1>Bulk SMS Reports</h1>
    <ul>
        <li><a href="{{ url('/dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('bulk_sms.index') }}">Bulk SMS</a></li>
        <li>Reports</li>
    </ul>
</div>

<div class="separator-breadcrumb border-top"></div>
@include('bulk_sms.partials.nav')
@if (session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

@if (session('error'))
    <div class="alert alert-danger">
        {{ session('error') }}
    </div>
@endif

<div class="row">
    <div class="col-md-12">
        <div class="card o-hidden mb-4">
            <div class="card-header d-flex align-items-center">
                <h3 class="w-50 float-start card-title m-0">Bulk SMS Report Summary</h3>

                <div class="dropdown dropleft text-end w-50 float-end">
                    <button class="btn bg-gray-100" id="bulkSmsReportsActions" type="button"
                            data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="nav-icon i-Gear-2"></i>
                    </button>

                    <div class="dropdown-menu" aria-labelledby="bulkSmsReportsActions">
                        <a class="dropdown-item" href="{{ route('bulk_sms.index') }}">Dashboard</a>
                        <a class="dropdown-item" href="{{ route('bulk_sms.messages') }}">Messages / Outbox</a>
                        <a class="dropdown-item" href="{{ route('bulk_sms.reports.export') }}">Export Messages CSV</a>
                        <a class="dropdown-item" href="{{ route('bulk_sms.diagnostics') }}">Diagnostics</a>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="alert alert-info">
                    This report summarizes SMS logs already recorded in the Bulk SMS outbox. It does not send SMS.
                </div>

                <div class="mb-3">
                    <a href="{{ route('bulk_sms.reports.export') }}" class="btn btn-primary">
                        Export Messages CSV
                    </a>

                    <a href="{{ route('bulk_sms.messages') }}" class="btn btn-outline-secondary">
                        View Messages
                    </a>

                    <a href="{{ route('bulk_sms.index') }}" class="btn btn-outline-info">
                        Dashboard
                    </a>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="card o-hidden mb-4">
                            <div class="card-header">
                                <h3 class="card-title m-0">Summary by Status</h3>
                            </div>

                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm text-center">
                                        <thead>
                                            <tr>
                                                <th>Status</th>
                                                <th>Total</th>
                                                <th>Segments</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            @forelse ($summaryByStatus as $row)
                                                <tr>
                                                    <td>
                                                        @if ($row->sms_status === 'sent' || $row->sms_status === 'delivered')
                                                            <span class="badge bg-success">{{ $row->sms_status }}</span>
                                                        @elseif ($row->sms_status === 'queued')
                                                            <span class="badge bg-info">{{ $row->sms_status }}</span>
                                                        @elseif ($row->sms_status === 'demo')
                                                            <span class="badge bg-warning">{{ $row->sms_status }}</span>
                                                        @elseif ($row->sms_status === 'skipped')
                                                            <span class="badge bg-secondary">{{ $row->sms_status }}</span>
                                                        @else
                                                            <span class="badge bg-danger">{{ $row->sms_status ?? 'unknown' }}</span>
                                                        @endif
                                                    </td>
                                                    <td>{{ number_format($row->total ?? 0) }}</td>
                                                    <td>{{ number_format($row->segments ?? 0) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="3" class="text-muted">
                                                        No status summary found.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card o-hidden mb-4">
                            <div class="card-header">
                                <h3 class="card-title m-0">Summary by Provider</h3>
                            </div>

                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm text-center">
                                        <thead>
                                            <tr>
                                                <th>Provider</th>
                                                <th>Total</th>
                                                <th>Segments</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            @forelse ($summaryByProvider as $row)
                                                <tr>
                                                    <td>{{ $row->provider_code ?? '-' }}</td>
                                                    <td>{{ number_format($row->total ?? 0) }}</td>
                                                    <td>{{ number_format($row->segments ?? 0) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="3" class="text-muted">
                                                        No provider summary found.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card o-hidden mb-4">
                            <div class="card-header">
                                <h3 class="card-title m-0">Summary by Date</h3>
                            </div>

                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm text-center">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Total</th>
                                                <th>Segments</th>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            @forelse ($summaryByDate as $row)
                                                <tr>
                                                    <td>{{ $row->sms_date ?? '-' }}</td>
                                                    <td>{{ number_format($row->total ?? 0) }}</td>
                                                    <td>{{ number_format($row->segments ?? 0) }}</td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="3" class="text-muted">
                                                        No daily summary found.
                                                    </td>
                                                </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr>

                <div class="alert alert-warning mb-0">
                    <strong>Note:</strong>
                    Current records are test/outbox logs. They should not be treated as billable sent SMS until live provider sending and delivery callbacks are implemented.
                </div>
            </div>
        </div>
    </div>
</div>
@endsection