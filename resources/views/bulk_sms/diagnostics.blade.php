@extends('layouts.app')

@section('content')
<div class="breadcrumb">
    <h1>Bulk SMS Diagnostics</h1>
    <ul>
        <li><a href="{{ url('/dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('bulk_sms.index') }}">Bulk SMS</a></li>
        <li>Diagnostics</li>
    </ul>
</div>

<div class="separator-breadcrumb border-top"></div>

<div class="row">
    <div class="col-md-7">
        <div class="card o-hidden mb-4">
            <div class="card-header d-flex align-items-center">
                <h3 class="w-50 float-start card-title m-0">Readiness Check</h3>

                <div class="text-end w-50 float-end">
                    <a href="{{ route('bulk_sms.settings') }}" class="btn btn-sm btn-outline-secondary">
                        Settings
                    </a>
                    <a href="{{ route('bulk_sms.diagnostics.json') }}" target="_blank" class="btn btn-sm btn-outline-primary">
                        JSON
                    </a>
                </div>
            </div>

            <div class="card-body">
                @if (!empty($readiness['ready_to_send']))
                    <div class="alert alert-success">
                        Bulk SMS is ready to send.
                    </div>
                @else
                    <div class="alert alert-warning">
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
                                <th>Provider Code</th>
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
                            <tr>
                                <th>Ready to Send</th>
                                <td>
                                    @if (!empty($readiness['ready_to_send']))
                                        <span class="badge bg-success">Yes</span>
                                    @else
                                        <span class="badge bg-danger">No</span>
                                    @endif
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <a href="{{ route('bulk_sms.index') }}" class="btn btn-outline-secondary">
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card o-hidden mb-4">
            <div class="card-header">
                <h3 class="card-title m-0">Selected Provider</h3>
            </div>

            <div class="card-body">
                @if ($provider)
                    <table class="table table-bordered table-sm">
                        <tbody>
                            <tr>
                                <th>Code</th>
                                <td>{{ $provider->provider_code }}</td>
                            </tr>
                            <tr>
                                <th>Name</th>
                                <td>{{ $provider->provider_name }}</td>
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
                                <th>Base URL</th>
                                <td>{{ $provider->provider_base_url ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Token URL</th>
                                <td>{{ $provider->provider_token_url ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Send URL</th>
                                <td>
                                    @if (!empty($provider->provider_send_url))
                                        {{ $provider->provider_send_url }}
                                    @else
                                        <span class="text-danger">Not configured</span>
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>Requires Network</th>
                                <td>{{ $provider->provider_requires_network }}</td>
                            </tr>
                            <tr>
                                <th>Enabled</th>
                                <td>{{ $provider->provider_enabled }}</td>
                            </tr>
                        </tbody>
                    </table>
                @else
                    <div class="alert alert-danger mb-0">
                        Selected provider does not exist.
                    </div>
                @endif
            </div>
        </div>

        <div class="card o-hidden mb-4">
            <div class="card-header">
                <h3 class="card-title m-0">Provider Configs</h3>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>Key</th>
                                <th>Value</th>
                                <th>Required</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($configs as $key => $config)
                                <tr>
                                    <td>{{ $key }}</td>
                                    <td>
                                        @if (!empty($config['value']))
                                            {{ $config['value'] }}
                                        @elseif (!empty($config['env_key']))
                                            <span class="text-muted">ENV: {{ $config['env_key'] }}</span>
                                        @else
                                            <span class="text-danger">Missing</span>
                                        @endif
                                    </td>
                                    <td>{{ $config['is_required'] ?? '-' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-muted text-center">
                                        No provider configs found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($provider)
                    <a href="{{ route('bulk_sms.provider_configs', $provider->provider_code) }}" class="btn btn-primary">
                        Manage Provider Configs
                    </a>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection