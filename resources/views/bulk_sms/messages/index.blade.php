@extends('layouts.app')

@section('content')
<div class="breadcrumb">
    <h1>Bulk SMS Messages</h1>
    <ul>
        <li><a href="{{ url('/dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('bulk_sms.index') }}">Bulk SMS</a></li>
        <li>Messages</li>
    </ul>
</div>

<div class="separator-breadcrumb border-top"></div>
@include('bulk_sms.partials.nav')
@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if (session('info'))
    <div class="alert alert-info">{{ session('info') }}</div>
@endif

<div class="card o-hidden mb-4">
    <div class="card-header d-flex align-items-center">
        <h3 class="w-50 float-start card-title m-0">SMS Outbox / Logs</h3>

        <div class="dropdown dropleft text-end w-50 float-end">
            <button class="btn bg-gray-100" id="bulkSmsMessagesActions" type="button"
                    data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="nav-icon i-Gear-2"></i>
            </button>

            <div class="dropdown-menu" aria-labelledby="bulkSmsMessagesActions">
                <a class="dropdown-item" href="{{ route('bulk_sms.index') }}">Dashboard</a>
                <a class="dropdown-item" href="{{ route('bulk_sms.test') }}">Send Test SMS</a>
                <a class="dropdown-item" href="{{ route('bulk_sms.settings') }}">Settings</a>
                <a class="dropdown-item" href="{{ route('bulk_sms.reports.export') }}">Export CSV</a>
            </div>
        </div>
    </div>

    <div class="card-body">
        <form method="GET" action="{{ route('bulk_sms.messages') }}" class="mb-4">
            <div class="row">
                <div class="col-md-2 form-group mb-3">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="">All</option>
                        @foreach (['queued', 'sent', 'delivered', 'failed', 'skipped', 'demo'] as $status)
                            <option value="{{ $status }}" {{ request('status') === $status ? 'selected' : '' }}>
                                {{ ucfirst($status) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2 form-group mb-3">
                    <label>Mode</label>
                    <select name="mode" class="form-control">
                        <option value="">All</option>
                        <option value="demo" {{ request('mode') === 'demo' ? 'selected' : '' }}>Demo</option>
                        <option value="live" {{ request('mode') === 'live' ? 'selected' : '' }}>Live</option>
                    </select>
                </div>

                <div class="col-md-2 form-group mb-3">
                    <label>Provider</label>
                    <select name="provider" class="form-control">
                        <option value="">All</option>
                        @foreach ($providers as $provider)
                            <option value="{{ $provider->provider_code }}"
                                {{ request('provider') === $provider->provider_code ? 'selected' : '' }}>
                                {{ $provider->provider_name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-2 form-group mb-3">
                    <label>Phone</label>
                    <input type="text" name="phone" class="form-control"
                           value="{{ request('phone') }}" placeholder="2547...">
                </div>

                <div class="col-md-3 form-group mb-3">
                    <label>Search</label>
                    <input type="text" name="search" class="form-control"
                           value="{{ request('search') }}" placeholder="Name, subject, message, reference">
                </div>

                <div class="col-md-1 form-group mb-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        Filter
                    </button>
                </div>
            </div>
        </form>

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div>
                <a href="{{ route('bulk_sms.test') }}" class="btn btn-primary">
                    Test SMS
                </a>

                <a href="{{ route('bulk_sms.index') }}" class="btn btn-outline-secondary">
                    Dashboard
                </a>
            </div>

            <form method="POST" action="{{ route('bulk_sms.messages.dispatch_queued') }}"
                  onsubmit="return confirm('Dispatch queued SMS now? This will still respect disabled/demo/readiness rules.');">
                @csrf
                <input type="hidden" name="limit" value="20">
                <button type="submit" class="btn btn-outline-primary">
                    Dispatch Queued
                </button>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table text-center table-bordered table-sm">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Recipient</th>
                        <th>Phone</th>
                        <th>Network</th>
                        <th>Provider</th>
                        <th>Mode</th>
                        <th>Status</th>
                        <th>Segments</th>
                        <th>Error</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($messages as $message)
                        <tr>
                            <td>{{ $message->sms_id }}</td>
                            <td>{{ $message->recipient_name ?? '-' }}</td>
                            <td>{{ $message->recipient_phone_normalized ?? $message->recipient_phone_raw ?? '-' }}</td>
                            <td>{{ $message->recipient_network ?? '-' }}</td>
                            <td>{{ $message->provider_code ?? '-' }}</td>
                            <td>{{ strtoupper($message->sms_mode ?? '-') }}</td>
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
                            <td>{{ $message->sms_segments ?? 0 }}</td>
                            <td>
                                @if ($message->error_code)
                                    <span class="text-danger">{{ $message->error_code }}</span>
                                @else
                                    -
                                @endif
                            </td>
                            <td>{{ $message->created_at }}</td>
                            <td>
                                <a class="text-success me-2"
                                   href="{{ route('bulk_sms.messages.show', $message->sms_id) }}"
                                   title="View">
                                    <i class="nav-icon i-Eye fw-bold"></i>
                                </a>

                                @if ($message->sms_status === 'queued')
                                    <form method="POST"
                                          action="{{ route('bulk_sms.messages.dispatch', $message->sms_id) }}"
                                          style="display:inline;"
                                          onsubmit="return confirm('Dispatch this SMS now?');">
                                        @csrf
                                        <button type="submit" class="btn btn-link p-0 text-primary" title="Dispatch">
                                            <i class="nav-icon i-Right fw-bold"></i>
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="11" class="text-center text-muted">
                                No SMS records found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            {{ $messages->links() }}
        </div>
    </div>
</div>
@endsection