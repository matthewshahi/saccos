@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div>
                <h5 class="mb-0">Route Audit Logs</h5>
                <small class="text-muted">
                    Login, logout, failed login, page access, form submission and route activity logs.
                </small>
            </div>

            <a href="{{ route('route.audit.logs.data') }}"
               target="_blank"
               class="btn btn-sm btn-outline-secondary">
                JSON Data
            </a>
        </div>

        <div class="card-body">
            <form method="GET" action="{{ route('route.audit.logs.index') }}" class="row g-2 mb-3">
                <div class="col-md-3">
                    <input type="text"
                           name="search"
                           class="form-control"
                           placeholder="Search user, login, route, IP..."
                           value="{{ request('search') }}">
                </div>

                <div class="col-md-2">
                    <select name="event_type" class="form-control">
                        <option value="">All Events</option>
                        @foreach(($eventTypes ?? []) as $eventType)
                            <option value="{{ $eventType }}" @selected(request('event_type') === $eventType)>
                                {{ $eventType }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <select name="outcome" class="form-control">
                        <option value="">All Outcomes</option>
                        @foreach(($outcomes ?? []) as $outcome)
                            <option value="{{ $outcome }}" @selected(request('outcome') === $outcome)>
                                {{ $outcome }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-1">
                    <select name="method" class="form-control">
                        <option value="">Method</option>
                        @foreach(($methods ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE']) as $method)
                            <option value="{{ $method }}" @selected(request('method') === $method)>
                                {{ $method }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2">
                    <input type="date"
                           name="date_from"
                           class="form-control"
                           value="{{ request('date_from') }}">
                </div>

                <div class="col-md-2">
                    <input type="date"
                           name="date_to"
                           class="form-control"
                           value="{{ request('date_to') }}">
                </div>

                <div class="col-md-12 d-flex gap-2 mt-2">
                    <button type="submit" class="btn btn-primary">
                        Filter
                    </button>

                    <a href="{{ route('route.audit.logs.index') }}" class="btn btn-outline-secondary">
                        Reset
                    </a>
                </div>
            </form>

            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Date</th>
                            <th>Event</th>
                            <th>Outcome</th>
                            <th>User</th>
                            <th>Attempted Login</th>
                            <th>Method</th>
                            <th>Route</th>
                            <th>Path</th>
                            <th>Status</th>
                            <th>IP</th>
                            <th>Duration</th>
                            <th></th>
                        </tr>
                    </thead>

                    <tbody>
                        @forelse($logs as $log)
                            @php
                                $outcomeClass = match($log->route_audit_log_outcome) {
                                    'SUCCESS' => 'bg-success',
                                    'FAILED', 'ERROR', 'FORBIDDEN', 'UNAUTHENTICATED', 'LOCKED' => 'bg-danger',
                                    'REDIRECT' => 'bg-warning text-dark',
                                    'NOT_FOUND' => 'bg-dark',
                                    default => 'bg-secondary',
                                };

                                $eventClass = match($log->route_audit_log_event_type) {
                                    'LOGIN_SUCCESS' => 'bg-success',
                                    'LOGIN_FAILED', 'LOGIN_LOCKOUT', 'LOGIN_RECAPTCHA_REJECTED', 'LOGIN_RECAPTCHA_LOW_SCORE' => 'bg-danger',
                                    'LOGOUT' => 'bg-dark',
                                    'FORM_SUBMIT', 'UPDATE_REQUEST', 'DELETE_REQUEST' => 'bg-primary',
                                    default => 'bg-secondary',
                                };
                            @endphp

                            <tr>
                                <td>{{ $log->route_audit_log_id }}</td>

                                <td style="white-space: nowrap;">
                                    <small>{{ $log->route_audit_log_created_at }}</small>
                                </td>

                                <td>
                                    <span class="badge {{ $eventClass }}">
                                        {{ $log->route_audit_log_event_type }}
                                    </span>
                                </td>

                                <td>
                                    <span class="badge {{ $outcomeClass }}">
                                        {{ $log->route_audit_log_outcome }}
                                    </span>
                                </td>

                                <td>
                                    @if($log->route_audit_log_user_name)
                                        <strong>{{ $log->route_audit_log_user_name }}</strong><br>
                                        <small class="text-muted">ID: {{ $log->route_audit_log_user_id }}</small>
                                    @elseif($log->route_audit_log_user_id)
                                        <small class="text-muted">ID: {{ $log->route_audit_log_user_id }}</small>
                                    @else
                                        <small class="text-muted">Guest</small>
                                    @endif
                                </td>

                                <td>
                                    <small>{{ $log->route_audit_log_attempted_login }}</small>
                                </td>

                                <td>
                                    <small>{{ $log->route_audit_log_method }}</small>
                                </td>

                                <td>
                                    <small>{{ $log->route_audit_log_route_name }}</small>
                                </td>

                                <td>
                                    <small>{{ $log->route_audit_log_path }}</small>
                                </td>

                                <td>
                                    <small>{{ $log->route_audit_log_status_code }}</small>
                                </td>

                                <td>
                                    <small>{{ $log->route_audit_log_ip_address }}</small>
                                </td>

                                <td>
                                    @if($log->route_audit_log_duration_ms !== null)
                                        <small>{{ $log->route_audit_log_duration_ms }}ms</small>
                                    @else
                                        <small>-</small>
                                    @endif
                                </td>

                                <td>
                                    <a href="{{ route('route.audit.logs.show', $log->route_audit_log_id) }}"
                                       class="btn btn-sm btn-outline-primary">
                                        View
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="13" class="text-center text-muted py-4">
                                    No route audit logs found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $logs->links() }}
            </div>
        </div>
    </div>
</div>
@endsection