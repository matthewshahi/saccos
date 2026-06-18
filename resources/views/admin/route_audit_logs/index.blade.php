@extends('layouts.master')

@section('main-content')
<div class="breadcrumb">
    <h1>Route Audit Logs</h1>
    <ul>
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li>System Logs</li>
    </ul>
</div>

<div class="separator-breadcrumb border-top"></div>

<div class="row">
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-body">
                <div class="card-title mb-3">Search Logs</div>

                <form id="logsFilterForm">
                    <div class="row">
                        <div class="col-md-3 form-group mb-3">
                            <label for="event_type">Event Type</label>
                            <select class="form-control" id="event_type" name="event_type">
                                <option value="">All Events</option>
                                @foreach($eventTypes as $eventType)
                                    <option value="{{ $eventType }}">{{ $eventType }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="outcome">Outcome</label>
                            <select class="form-control" id="outcome" name="outcome">
                                <option value="">All Outcomes</option>
                                @foreach($outcomes as $outcome)
                                    <option value="{{ $outcome }}">{{ $outcome }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2 form-group mb-3">
                            <label for="method">Method</label>
                            <select class="form-control" id="method" name="method">
                                <option value="">All Methods</option>
                                @foreach($methods as $method)
                                    <option value="{{ $method }}">{{ strtoupper($method) }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2 form-group mb-3">
                            <label for="status_code">HTTP Status</label>
                            <input class="form-control" id="status_code" name="status_code" placeholder="200, 302, 403">
                        </div>

                        <div class="col-md-2 form-group mb-3">
                            <label for="ip_address">IP Address</label>
                            <input class="form-control" id="ip_address" name="ip_address" placeholder="196.96...">
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="user_search">User / Login</label>
                            <input class="form-control" id="user_search" name="user_search" placeholder="Name, email, login">
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <label for="route_search">Route / Path / Controller</label>
                            <input class="form-control" id="route_search" name="route_search" placeholder="login, members, controller">
                        </div>

                        <div class="col-md-2 form-group mb-3">
                            <label for="request_id">Request ID</label>
                            <input class="form-control" id="request_id" name="request_id" placeholder="UUID">
                        </div>

                        <div class="col-md-2 form-group mb-3">
                            <label for="date_from">Date From</label>
                            <input class="form-control" id="date_from" name="date_from" type="date">
                        </div>

                        <div class="col-md-2 form-group mb-3">
                            <label for="date_to">Date To</label>
                            <input class="form-control" id="date_to" name="date_to" type="date">
                        </div>

                        <div class="col-md-12 form-group mb-3">
                            <button type="submit" class="btn btn-primary me-2">
                                Search
                            </button>

                            <button type="button" id="resetFilters" class="btn btn-secondary me-2">
                                Reset
                            </button>

                            <button type="button" id="reloadLogs" class="btn btn-outline-primary">
                                Reload
                            </button>
                        </div>
                    </div>
                </form>

                <small class="text-muted">
                    The table loads a maximum of 50 records per request. Use filters or the DataTables search box to narrow results.
                </small>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-md-12">
        <div class="card o-hidden mb-4">
            <div class="card-header d-flex align-items-center">
                <h3 class="w-50 float-start card-title m-0">System / Route Audit Logs</h3>

                <div class="dropdown dropleft text-end w-50 float-end">
                    <button class="btn bg-gray-100" id="dropdownMenuButton_logs" type="button"
                            data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="nav-icon i-Gear-2"></i>
                    </button>

                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_logs">
                        <a class="dropdown-item" href="#" id="reloadLogsDropdown">Reload Logs</a>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table id="routeAuditLogsTable" class="table table-striped table-bordered text-center" style="width:100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Date</th>
                                <th>Event</th>
                                <th>Outcome</th>
                                <th>User</th>
                                <th>Method</th>
                                <th>Route / Path</th>
                                <th>HTTP</th>
                                <th>IP</th>
                                <th>Duration</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Log Details Modal --}}
<div class="modal fade" id="logDetailsModal" tabindex="-1" aria-labelledby="logDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Log Details</h5>

                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>

            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <tbody id="logDetailsBody"></tbody>
                    </table>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Close
                </button>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    $(document).ready(function () {
        let routeAuditLogsTable = $('#routeAuditLogsTable').DataTable({
            processing: true,
            serverSide: true,
            pageLength: 50,
            lengthMenu: [[10, 25, 50], [10, 25, 50]],
            order: [[1, 'desc']],
            ajax: {
                url: "{{ route('route.audit.logs.data') }}",
                data: function (d) {
                    d.event_type = $('#event_type').val();
                    d.outcome = $('#outcome').val();
                    d.method = $('#method').val();
                    d.status_code = $('#status_code').val();
                    d.ip_address = $('#ip_address').val();
                    d.user_search = $('#user_search').val();
                    d.route_search = $('#route_search').val();
                    d.request_id = $('#request_id').val();
                    d.date_from = $('#date_from').val();
                    d.date_to = $('#date_to').val();
                }
            },
            columns: [
                {data: 'route_audit_log_id', name: 'route_audit_log_id'},
                {data: 'route_audit_log_created_at', name: 'route_audit_log_created_at'},
                {data: 'route_audit_log_event_type', name: 'route_audit_log_event_type'},
                {data: 'route_audit_log_outcome', name: 'route_audit_log_outcome'},
                {data: 'user_display', name: 'user_display'},
                {data: 'route_audit_log_method', name: 'route_audit_log_method'},
                {data: 'route_display', name: 'route_display'},
                {data: 'route_audit_log_status_code', name: 'route_audit_log_status_code'},
                {data: 'route_audit_log_ip_address', name: 'route_audit_log_ip_address'},
                {data: 'route_audit_log_duration_ms', name: 'route_audit_log_duration_ms'},
                {data: 'action', name: 'action', orderable: false, searchable: false}
            ]
        });

        $('#logsFilterForm').on('submit', function (e) {
            e.preventDefault();
            routeAuditLogsTable.ajax.reload();
        });

        $('#resetFilters').on('click', function () {
            $('#logsFilterForm')[0].reset();
            routeAuditLogsTable.search('');
            routeAuditLogsTable.ajax.reload();
        });

        $('#reloadLogs, #reloadLogsDropdown').on('click', function (e) {
            e.preventDefault();
            routeAuditLogsTable.ajax.reload(null, false);
        });

        $(document).on('click', '.view-log-details', function () {
            let encodedDetails = $(this).attr('data-details');
            let details = {};

            try {
                details = JSON.parse(decodeBase64Unicode(encodedDetails));
            } catch (e) {
                details = {};
            }

            let html = '';

            Object.keys(details).forEach(function (key) {
                let value = details[key];

                if (value === null || value === undefined || value === '') {
                    value = '-';
                }

                let valueString = String(value);
                let isLongText = valueString.length > 120 || valueString.includes("\n");

                html += `
                    <tr>
                        <th style="width: 230px;">${escapeHtml(key)}</th>
                        <td>
                            ${
                                isLongText
                                    ? `<pre style="white-space: pre-wrap; max-height: 350px; overflow:auto;">${escapeHtml(valueString)}</pre>`
                                    : escapeHtml(valueString)
                            }
                        </td>
                    </tr>
                `;
            });

            $('#logDetailsBody').html(html);
            $('#logDetailsModal').modal('show');
        });

        function decodeBase64Unicode(base64) {
            let binary = atob(base64);
            let bytes = Uint8Array.from(binary, function (char) {
                return char.charCodeAt(0);
            });

            return new TextDecoder('utf-8').decode(bytes);
        }

        function escapeHtml(value) {
            return String(value)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }
    });
</script>
@endpush