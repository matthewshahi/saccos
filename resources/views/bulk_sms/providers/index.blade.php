@extends('layouts.app')

@section('content')
<style>
    .bulk-sms-provider-card {
        border: 1px solid #e5e7eb;
        border-radius: 8px;
        margin-bottom: 16px;
        background: #fff;
    }

    .bulk-sms-provider-header {
        padding: 15px 18px;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 15px;
    }

    .bulk-sms-provider-body {
        padding: 15px 18px;
    }

    .bulk-sms-provider-title {
        font-size: 16px;
        font-weight: 700;
        margin-bottom: 4px;
    }

    .bulk-sms-provider-code {
        font-size: 12px;
        padding: 4px 8px;
        border-radius: 12px;
        background: #eef2f7;
        display: inline-block;
    }

    .bulk-sms-provider-actions {
        min-width: 230px;
        text-align: right;
    }

    .bulk-sms-provider-actions .btn {
        margin-bottom: 5px;
    }

    .bulk-sms-detail-table th {
        width: 180px;
        background: #fafafa;
    }

    .bulk-sms-detail-table td,
    .bulk-sms-detail-table th {
        font-size: 13px;
        vertical-align: middle;
    }

    .bulk-sms-url {
        word-break: break-word;
        white-space: normal;
    }

    @media (max-width: 768px) {
        .bulk-sms-provider-header {
            display: block;
        }

        .bulk-sms-provider-actions {
            text-align: left;
            margin-top: 12px;
        }

        .bulk-sms-detail-table th {
            width: 120px;
        }
    }
</style>

<div class="breadcrumb">
    <h1>Bulk SMS Providers</h1>
    <ul>
        <li><a href="{{ url('/dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('bulk_sms.index') }}">Bulk SMS</a></li>
        <li>Providers</li>
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

<div class="card o-hidden mb-4">
    <div class="card-header d-flex align-items-center">
        <h3 class="w-50 float-start card-title m-0">Bulk SMS Providers</h3>

        <div class="dropdown dropleft text-end w-50 float-end">
            <button class="btn bg-gray-100" id="bulkSmsProvidersActions" type="button"
                    data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                <i class="nav-icon i-Gear-2"></i>
            </button>

            <div class="dropdown-menu" aria-labelledby="bulkSmsProvidersActions">
                <a class="dropdown-item" href="{{ route('bulk_sms.index') }}">Dashboard</a>
                <a class="dropdown-item" href="{{ route('bulk_sms.settings') }}">Global Settings</a>
                <a class="dropdown-item" href="{{ route('bulk_sms.providers.create') }}">Add Provider</a>
                <a class="dropdown-item" href="{{ route('bulk_sms.diagnostics') }}">Diagnostics</a>
            </div>
        </div>
    </div>

    <div class="card-body">
        <div class="alert alert-info">
            Providers define the SMS gateway or PRSP driver. ADTEL is currently configured, but live sending should remain disabled until the full send API documentation is available.
        </div>

        <div class="mb-3">
            <a href="{{ route('bulk_sms.providers.create') }}" class="btn btn-primary">
                Add Provider
            </a>

            <a href="{{ route('bulk_sms.settings') }}" class="btn btn-outline-secondary">
                Global Settings
            </a>

            <a href="{{ route('bulk_sms.diagnostics') }}" class="btn btn-outline-info">
                Diagnostics
            </a>
        </div>

        @forelse ($providers as $provider)
            <div class="bulk-sms-provider-card">
                <div class="bulk-sms-provider-header">
                    <div>
                        <div class="bulk-sms-provider-title">
                            {{ $provider->provider_name }}
                        </div>

                        <div class="mb-2">
                            <span class="bulk-sms-provider-code">
                                {{ $provider->provider_code }}
                            </span>

                            @if ($provider->provider_enabled === 'Y')
                                <span class="badge bg-success ms-1">Enabled</span>
                            @else
                                <span class="badge bg-danger ms-1">Disabled</span>
                            @endif

                            @if ($provider->provider_requires_network === 'Y')
                                <span class="badge bg-info ms-1">Requires Network</span>
                            @else
                                <span class="badge bg-secondary ms-1">No Network Required</span>
                            @endif
                        </div>

                        @if (!empty($provider->provider_notes))
                            <div class="text-muted">
                                {{ $provider->provider_notes }}
                            </div>
                        @endif
                    </div>

                    <div class="bulk-sms-provider-actions">
                        <a href="{{ route('bulk_sms.providers.edit', $provider->provider_code) }}"
                           class="btn btn-sm btn-outline-success">
                            Edit
                        </a>

                        <a href="{{ route('bulk_sms.provider_configs', $provider->provider_code) }}"
                           class="btn btn-sm btn-outline-primary">
                            Configs
                        </a>

                        <a href="{{ route('bulk_sms.provider_networks', $provider->provider_code) }}"
                           class="btn btn-sm btn-outline-info">
                            Networks
                        </a>

                        <form method="POST"
                              action="{{ route('bulk_sms.providers.toggle', $provider->provider_code) }}"
                              style="display:inline;"
                              onsubmit="return confirm('Toggle this provider status?');">
                            @csrf

                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                Toggle
                            </button>
                        </form>
                    </div>
                </div>

                <div class="bulk-sms-provider-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-bordered table-sm bulk-sms-detail-table">
                                <tbody>
                                    <tr>
                                        <th>Provider ID</th>
                                        <td>{{ $provider->provider_id }}</td>
                                    </tr>
                                    <tr>
                                        <th>Type</th>
                                        <td>{{ $provider->provider_type }}</td>
                                    </tr>
                                    <tr>
                                        <th>Driver</th>
                                        <td>{{ $provider->provider_driver }}</td>
                                    </tr>
                                    <tr>
                                        <th>Default Network</th>
                                        <td>{{ $provider->provider_default_network ?? '-' }}</td>
                                    </tr>
                                    <tr>
                                        <th>Updated</th>
                                        <td>{{ $provider->provider_updated_at ?? '-' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="col-md-6">
                            <table class="table table-bordered table-sm bulk-sms-detail-table">
                                <tbody>
                                    <tr>
                                        <th>Base URL</th>
                                        <td class="bulk-sms-url">
                                            {{ $provider->provider_base_url ?? '-' }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Token URL</th>
                                        <td class="bulk-sms-url">
                                            {{ $provider->provider_token_url ?? '-' }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Send URL</th>
                                        <td class="bulk-sms-url">
                                            @if (!empty($provider->provider_send_url))
                                                {{ $provider->provider_send_url }}
                                            @else
                                                <span class="text-danger">Not configured</span>
                                            @endif
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Balance URL</th>
                                        <td class="bulk-sms-url">
                                            {{ $provider->provider_balance_url ?? '-' }}
                                        </td>
                                    </tr>
                                    <tr>
                                        <th>Status URL</th>
                                        <td class="bulk-sms-url">
                                            {{ $provider->provider_delivery_status_url ?? '-' }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="alert alert-warning mb-0">
                No Bulk SMS providers found.
            </div>
        @endforelse
    </div>
</div>
@endsection