@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0">Route Audit Log Details</h5>
                <small class="text-muted">
                    Log #{{ $log->route_audit_log_id }}
                </small>
            </div>

            <a href="{{ route('route.audit.logs.index') }}" class="btn btn-sm btn-outline-secondary">
                Back to Logs
            </a>
        </div>

        <div class="card-body">
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="border rounded p-3 bg-light h-100">
                        <small class="text-muted d-block">Event</small>
                        <strong>{{ $log->route_audit_log_event_type }}</strong>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="border rounded p-3 bg-light h-100">
                        <small class="text-muted d-block">Outcome</small>
                        <strong>{{ $log->route_audit_log_outcome }}</strong>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="border rounded p-3 bg-light h-100">
                        <small class="text-muted d-block">Method</small>
                        <strong>{{ $log->route_audit_log_method ?? '-' }}</strong>
                    </div>
                </div>

                <div class="col-md-3">
                    <div class="border rounded p-3 bg-light h-100">
                        <small class="text-muted d-block">Created At</small>
                        <strong>{{ $log->route_audit_log_created_at }}</strong>
                    </div>
                </div>
            </div>

            <div class="card border shadow-sm mb-3">
                <div class="card-header bg-white">
                    <strong>User / Login Details</strong>
                </div>

                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <th style="width: 220px;">User ID</th>
                            <td>{{ $log->route_audit_log_user_id ?? 'Guest / Not authenticated' }}</td>
                        </tr>
                        <tr>
                            <th>User Name</th>
                            <td>{{ $log->route_audit_log_user_name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>User Email</th>
                            <td>{{ $log->route_audit_log_user_email ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Attempted Login</th>
                            <td>{{ $log->route_audit_log_attempted_login ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>IP Address</th>
                            <td>{{ $log->route_audit_log_ip_address ?? '-' }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="card border shadow-sm mb-3">
                <div class="card-header bg-white">
                    <strong>Route / Request Details</strong>
                </div>

                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <th style="width: 220px;">Request ID</th>
                            <td style="word-break: break-word;">{{ $log->route_audit_log_request_id ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Route Name</th>
                            <td>{{ $log->route_audit_log_route_name ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Controller Action</th>
                            <td style="word-break: break-word;">{{ $log->route_audit_log_controller_action ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Status Code</th>
                            <td>{{ $log->route_audit_log_status_code ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Duration</th>
                            <td>
                                @if($log->route_audit_log_duration_ms !== null)
                                    {{ $log->route_audit_log_duration_ms }}ms
                                @else
                                    -
                                @endif
                            </td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="card border shadow-sm mb-3">
                <div class="card-header bg-white">
                    <strong>URL / Browser Details</strong>
                </div>

                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <th style="width: 220px;">Path</th>
                            <td style="word-break: break-word;">{{ $log->route_audit_log_path ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Full URL</th>
                            <td style="word-break: break-word;">{{ $log->route_audit_log_url ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Referer</th>
                            <td style="word-break: break-word;">{{ $log->route_audit_log_referer ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>User Agent</th>
                            <td style="word-break: break-word;">{{ $log->route_audit_log_user_agent ?? '-' }}</td>
                        </tr>
                    </table>
                </div>
            </div>

            <div class="card border shadow-sm mb-3">
                <div class="card-header bg-white">
                    <strong>Payload</strong>
                </div>

                <div class="card-body">
                    @php
                        $payloadDecoded = null;

                        if (!empty($log->route_audit_log_payload)) {
                            $payloadDecoded = json_decode($log->route_audit_log_payload, true);
                        }
                    @endphp

                    @if(!empty($payloadDecoded))
                        <pre class="mb-0 bg-light rounded p-3 small" style="white-space: pre-wrap;">{{ json_encode($payloadDecoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    @elseif(!empty($log->route_audit_log_payload))
                        <pre class="mb-0 bg-light rounded p-3 small" style="white-space: pre-wrap;">{{ $log->route_audit_log_payload }}</pre>
                    @else
                        <span class="text-muted">No payload captured.</span>
                    @endif
                </div>
            </div>

            <div class="card border shadow-sm mb-3">
                <div class="card-header bg-white">
                    <strong>Uploaded File Metadata</strong>
                </div>

                <div class="card-body">
                    @php
                        $filesDecoded = null;

                        if (!empty($log->route_audit_log_files)) {
                            $filesDecoded = json_decode($log->route_audit_log_files, true);
                        }
                    @endphp

                    @if(!empty($filesDecoded))
                        <pre class="mb-0 bg-light rounded p-3 small" style="white-space: pre-wrap;">{{ json_encode($filesDecoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                    @elseif(!empty($log->route_audit_log_files))
                        <pre class="mb-0 bg-light rounded p-3 small" style="white-space: pre-wrap;">{{ $log->route_audit_log_files }}</pre>
                    @else
                        <span class="text-muted">No uploaded files captured.</span>
                    @endif
                </div>
            </div>

            @if(!empty($log->route_audit_log_exception_class) || !empty($log->route_audit_log_exception_message))
                <div class="card border-danger shadow-sm mb-3">
                    <div class="card-header bg-danger text-white">
                        <strong>Exception / Error Details</strong>
                    </div>

                    <div class="card-body">
                        <table class="table table-sm mb-3">
                            <tr>
                                <th style="width: 220px;">Exception Class</th>
                                <td style="word-break: break-word;">
                                    {{ $log->route_audit_log_exception_class ?? '-' }}
                                </td>
                            </tr>
                        </table>

                        <pre class="mb-0 bg-light rounded p-3 small" style="white-space: pre-wrap;">{{ $log->route_audit_log_exception_message }}</pre>
                    </div>
                </div>
            @endif

            <div class="mt-4">
                <a href="{{ route('route.audit.logs.index') }}" class="btn btn-outline-secondary">
                    Back to Logs
                </a>
            </div>
        </div>
    </div>
</div>
@endsection