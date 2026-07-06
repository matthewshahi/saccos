@extends('layouts.app')

@section('content')
<div class="breadcrumb">
    <h1>Bulk SMS</h1>
    <ul>
        <li><a href="{{ url('/dashboard') }}">Dashboard</a></li>
        <li>Communications</li>
        <li>Bulk SMS</li>
    </ul>
</div>

<div class="separator-breadcrumb border-top"></div>

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

@if (session('info'))
    <div class="alert alert-info">
        {{ session('info') }}
    </div>
@endif

<div class="row">
    <div class="col-md-12">
        <div class="card o-hidden mb-4">
            <div class="card-header d-flex align-items-center">
                <h3 class="w-50 float-start card-title m-0">Bulk SMS Dashboard</h3>

                <div class="dropdown dropleft text-end w-50 float-end">
                    <button class="btn bg-gray-100" id="bulkSmsActions" type="button"
                            data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="nav-icon i-Gear-2"></i>
                    </button>

                    <div class="dropdown-menu" aria-labelledby="bulkSmsActions">
                        <a class="dropdown-item" href="{{ route('bulk_sms.settings') }}">Settings</a>
                        <a class="dropdown-item" href="{{ route('bulk_sms.providers') }}">Providers</a>
                        <a class="dropdown-item" href="{{ route('bulk_sms.messages') }}">Messages / Outbox</a>
                        <a class="dropdown-item" href="{{ route('bulk_sms.test') }}">Send Test SMS</a>
                        <a class="dropdown-item" href="{{ route('bulk_sms.diagnostics') }}">Diagnostics</a>
                        <a class="dropdown-item" href="{{ route('bulk_sms.reports.summary') }}">Reports</a>
                    </div>
                </div>
            </div>

            <div class="card-body">
                @if (!empty($readiness['ready_to_send']))
                    <div class="alert alert-success mb-4">
                        Bulk SMS is ready to send.
                    </div>
                @else
                    <div class="alert alert-warning mb-4">
                        <strong>Bulk SMS is not ready to send.</strong>

                        @if (!empty($readiness['issues']))
                            <ul class="mb-0 mt-2">
                                @foreach ($readiness['issues'] as $issue)
                                    <li>{{ $issue }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif

                <div class="row">
                    <div class="col-md-3 mb-3">
                        <div class="card card-icon-bg card-icon-bg-primary o-hidden">
                            <div class="card-body text-center">
                                <i class="i-Speach-Bubble-3"></i>
                                <div class="content">
                                    <p class="text-muted mt-2 mb-0">Total SMS</p>
                                    <p class="text-primary text-24 line-height-1 mb-2">
                                        {{ number_format($stats['total'] ?? 0) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card card-icon-bg card-icon-bg-primary o-hidden">
                            <div class="card-body text-center">
                                <i class="i-Clock"></i>
                                <div class="content">
                                    <p class="text-muted mt-2 mb-0">Queued</p>
                                    <p class="text-primary text-24 line-height-1 mb-2">
                                        {{ number_format($stats['queued'] ?? 0) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card card-icon-bg card-icon-bg-primary o-hidden">
                            <div class="card-body text-center">
                                <i class="i-Yes"></i>
                                <div class="content">
                                    <p class="text-muted mt-2 mb-0">Sent</p>
                                    <p class="text-primary text-24 line-height-1 mb-2">
                                        {{ number_format($stats['sent'] ?? 0) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-3 mb-3">
                        <div class="card card-icon-bg card-icon-bg-primary o-hidden">
                            <div class="card-body text-center">
                                <i class="i-Close-Window"></i>
                                <div class="content">
                                    <p class="text-muted mt-2 mb-0">Failed / Skipped</p>
                                    <p class="text-primary text-24 line-height-1 mb-2">
                                        {{ number_format(($stats['failed'] ?? 0) + ($stats['skipped'] ?? 0)) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <hr>

                <h5 class="mb-3">Current Status</h5>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <tbody>
                            <tr>
                                <th style="width: 250px;">Enabled</th>
                                <td>
                                    @if (!empty($readiness['enabled']))
                                        <span class="badge bg-success">Yes</span>
                                    @else
                                        <span class="badge bg-danger">No</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Demo Mode</th>
                                <td>
                                    @if (!empty($readiness['demo_mode']))
                                        <span class="badge bg-warning">Yes</span>
                                    @else
                                        <span class="badge bg-success">No</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Provider</th>
                                <td>{{ $readiness['provider_code'] ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Provider Exists</th>
                                <td>{{ !empty($readiness['provider_exists']) ? 'Yes' : 'No' }}</td>
                            </tr>
                            <tr>
                                <th>Provider Enabled</th>
                                <td>{{ !empty($readiness['provider_enabled']) ? 'Yes' : 'No' }}</td>
                            </tr>
                            <tr>
                                <th>Default Network</th>
                                <td>{{ $readiness['default_network'] ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Default Sender ID</th>
                                <td>{{ $readiness['default_sender_id'] ?? '-' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <hr>

                <h5 class="mb-3">Recent SMS Logs</h5>

                <div class="table-responsive">
                    <table class="table text-center table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Recipient</th>
                                <th>Phone</th>
                                <th>Provider</th>
                                <th>Mode</th>
                                <th>Status</th>
                                <th>Segments</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($recentMessages as $message)
                                <tr>
                                    <td>{{ $message->sms_id }}</td>
                                    <td>{{ $message->recipient_name ?? '-' }}</td>
                                    <td>{{ $message->recipient_phone_normalized ?? $message->recipient_phone_raw }}</td>
                                    <td>{{ $message->provider_code }}</td>
                                    <td>{{ strtoupper($message->sms_mode) }}</td>
                                    <td>
                                        @if ($message->sms_status === 'sent' || $message->sms_status === 'delivered')
                                            <span class="badge bg-success">{{ $message->sms_status }}</span>
                                        @elseif ($message->sms_status === 'queued')
                                            <span class="badge bg-info">{{ $message->sms_status }}</span>
                                        @elseif ($message->sms_status === 'demo')
                                            <span class="badge bg-warning">{{ $message->sms_status }}</span>
                                        @elseif ($message->sms_status === 'skipped')
                                            <span class="badge bg-secondary">{{ $message->sms_status }}</span>
                                        @else
                                            <span class="badge bg-danger">{{ $message->sms_status }}</span>
                                        @endif
                                    </td>
                                    <td>{{ $message->sms_segments }}</td>
                                    <td>{{ $message->created_at }}</td>
                                    <td>
                                        <a class="text-success me-2"
                                           href="{{ route('bulk_sms.messages.show', $message->sms_id) }}">
                                            <i class="nav-icon i-Eye fw-bold"></i>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted">
                                        No SMS logs found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <a href="{{ route('bulk_sms.messages') }}" class="btn btn-primary">
                        View All Messages
                    </a>

                    <a href="{{ route('bulk_sms.test') }}" class="btn btn-outline-primary">
                        Test SMS
                    </a>

                    <a href="{{ route('bulk_sms.settings') }}" class="btn btn-outline-secondary">
                        Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection