@extends('layouts.app')

@section('content')
@php
    $defaultValue = function ($name, $fallback = null) use ($defaults) {
        return optional($defaults->get($name))->default_value ?? $fallback;
    };

    $bulkSmsEnabled = old('BULK_SMS_ENABLED', $defaultValue('BULK_SMS_ENABLED', 'N'));
    $bulkSmsProvider = old('BULK_SMS_PROVIDER', $defaultValue('BULK_SMS_PROVIDER', 'adtel'));
    $bulkSmsDemoMode = old('BULK_SMS_DEMO_MODE', $defaultValue('BULK_SMS_DEMO_MODE', 'Y'));
    $bulkSmsDefaultNetwork = old('BULK_SMS_DEFAULT_NETWORK', $defaultValue('BULK_SMS_DEFAULT_NETWORK', 'safaricom'));
    $bulkSmsDefaultSenderId = old('BULK_SMS_DEFAULT_SENDER_ID', $defaultValue('BULK_SMS_DEFAULT_SENDER_ID', ''));
    $bulkSmsFailClosed = old('BULK_SMS_FAIL_CLOSED', $defaultValue('BULK_SMS_FAIL_CLOSED', 'Y'));
    $bulkSmsLogToNotifications = old('BULK_SMS_LOG_TO_SYSTEM_NOTIFICATIONS', $defaultValue('BULK_SMS_LOG_TO_SYSTEM_NOTIFICATIONS', 'Y'));
    $bulkSmsMaxRetryAttempts = old('BULK_SMS_MAX_RETRY_ATTEMPTS', $defaultValue('BULK_SMS_MAX_RETRY_ATTEMPTS', 3));
    $bulkSmsRetryDelaySeconds = old('BULK_SMS_RETRY_DELAY_SECONDS', $defaultValue('BULK_SMS_RETRY_DELAY_SECONDS', 300));
    $bulkSmsPhoneFormat = old('BULK_SMS_PHONE_FORMAT', $defaultValue('BULK_SMS_PHONE_FORMAT', 'E164_KE'));
@endphp

<div class="breadcrumb">
    <h1>Bulk SMS Settings</h1>
    <ul>
        <li><a href="{{ url('/dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('bulk_sms.index') }}">Bulk SMS</a></li>
        <li>Settings</li>
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

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Please correct the following errors:</strong>
        <ul class="mb-0 mt-2">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-body">
                <div class="card-title mb-3">Bulk SMS Global Settings</div>

                <form method="POST" action="{{ route('bulk_sms.settings.update') }}">
                    @csrf

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label>Bulk SMS Enabled</label>
                            <select name="BULK_SMS_ENABLED" class="form-control">
                                <option value="N" {{ $bulkSmsEnabled === 'N' ? 'selected' : '' }}>
                                    No - Disabled
                                </option>
                                <option value="Y" {{ $bulkSmsEnabled === 'Y' ? 'selected' : '' }}>
                                    Yes - Enabled
                                </option>
                            </select>
                            <small class="text-muted">
                                Keep this disabled until provider sending is fully tested.
                            </small>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Demo Mode</label>
                            <select name="BULK_SMS_DEMO_MODE" class="form-control">
                                <option value="Y" {{ $bulkSmsDemoMode === 'Y' ? 'selected' : '' }}>
                                    Yes - Log only, do not send
                                </option>
                                <option value="N" {{ $bulkSmsDemoMode === 'N' ? 'selected' : '' }}>
                                    No - Live sending allowed
                                </option>
                            </select>
                            <small class="text-muted">
                                Demo mode should remain enabled until ADTEL live sending is confirmed.
                            </small>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Default Provider</label>
                            <select name="BULK_SMS_PROVIDER" class="form-control">
                                @foreach ($providers as $provider)
                                    <option value="{{ $provider->provider_code }}"
                                        {{ $bulkSmsProvider === $provider->provider_code ? 'selected' : '' }}>
                                        {{ $provider->provider_name }} ({{ $provider->provider_code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Default Network</label>
                            <select name="BULK_SMS_DEFAULT_NETWORK" class="form-control">
                                @forelse ($networks as $network)
                                    <option value="{{ $network->network_code }}"
                                        {{ $bulkSmsDefaultNetwork === $network->network_code ? 'selected' : '' }}>
                                        {{ $network->network_name }} ({{ $network->network_code }})
                                    </option>
                                @empty
                                    <option value="safaricom" {{ $bulkSmsDefaultNetwork === 'safaricom' ? 'selected' : '' }}>
                                        Safaricom
                                    </option>
                                    <option value="airtel" {{ $bulkSmsDefaultNetwork === 'airtel' ? 'selected' : '' }}>
                                        Airtel
                                    </option>
                                    <option value="telkom" {{ $bulkSmsDefaultNetwork === 'telkom' ? 'selected' : '' }}>
                                        Telkom
                                    </option>
                                    <option value="unknown" {{ $bulkSmsDefaultNetwork === 'unknown' ? 'selected' : '' }}>
                                        Unknown
                                    </option>
                                @endforelse
                            </select>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Default Sender ID</label>
                            <input type="text"
                                   name="BULK_SMS_DEFAULT_SENDER_ID"
                                   class="form-control"
                                   value="{{ $bulkSmsDefaultSenderId }}"
                                   placeholder="Example: KASS SACCO">
                            <small class="text-muted">
                                Leave blank until ADTEL confirms the registered sender ID.
                            </small>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Fail Closed</label>
                            <select name="BULK_SMS_FAIL_CLOSED" class="form-control">
                                <option value="Y" {{ $bulkSmsFailClosed === 'Y' ? 'selected' : '' }}>
                                    Yes - safer
                                </option>
                                <option value="N" {{ $bulkSmsFailClosed === 'N' ? 'selected' : '' }}>
                                    No
                                </option>
                            </select>
                            <small class="text-muted">
                                Recommended: Yes. If configuration is incomplete, SMS should not send.
                            </small>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Log to System Notifications</label>
                            <select name="BULK_SMS_LOG_TO_SYSTEM_NOTIFICATIONS" class="form-control">
                                <option value="Y" {{ $bulkSmsLogToNotifications === 'Y' ? 'selected' : '' }}>
                                    Yes
                                </option>
                                <option value="N" {{ $bulkSmsLogToNotifications === 'N' ? 'selected' : '' }}>
                                    No
                                </option>
                            </select>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Phone Format</label>
                            <select name="BULK_SMS_PHONE_FORMAT" class="form-control">
                                <option value="E164_KE" {{ $bulkSmsPhoneFormat === 'E164_KE' ? 'selected' : '' }}>
                                    E164 Kenya - 2547XXXXXXXX
                                </option>
                                <option value="254" {{ $bulkSmsPhoneFormat === '254' ? 'selected' : '' }}>
                                    254 Format
                                </option>
                            </select>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Max Retry Attempts</label>
                            <input type="number"
                                   name="BULK_SMS_MAX_RETRY_ATTEMPTS"
                                   class="form-control"
                                   min="0"
                                   max="10"
                                   value="{{ $bulkSmsMaxRetryAttempts }}">
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Retry Delay Seconds</label>
                            <input type="number"
                                   name="BULK_SMS_RETRY_DELAY_SECONDS"
                                   class="form-control"
                                   min="0"
                                   max="3600"
                                   value="{{ $bulkSmsRetryDelaySeconds }}">
                        </div>

                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">
                                Save Settings
                            </button>

                            <a href="{{ route('bulk_sms.index') }}" class="btn btn-outline-secondary">
                                Back to Dashboard
                            </a>

                            <a href="{{ route('bulk_sms.diagnostics') }}" class="btn btn-outline-info">
                                Diagnostics
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card o-hidden mb-4">
            <div class="card-header">
                <h3 class="card-title m-0">Readiness</h3>
            </div>

            <div class="card-body">
                @if (!empty($readiness['ready_to_send']))
                    <div class="alert alert-success">
                        Bulk SMS is ready to send.
                    </div>
                @else
                    <div class="alert alert-warning">
                        <strong>Not ready to send.</strong>

                        @if (!empty($readiness['issues']))
                            <ul class="mb-0 mt-2">
                                @foreach ($readiness['issues'] as $issue)
                                    <li>{{ $issue }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endif

                <table class="table table-bordered table-sm">
                    <tbody>
                        <tr>
                            <th>Enabled</th>
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
                            <th>Sender ID</th>
                            <td>{{ $readiness['default_sender_id'] ?? '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card o-hidden mb-4">
            <div class="card-header">
                <h3 class="card-title m-0">Safety Note</h3>
            </div>

            <div class="card-body">
                <p class="mb-2">
                    Recommended current state:
                </p>

                <ul class="mb-0">
                    <li><strong>Enabled:</strong> No</li>
                    <li><strong>Demo Mode:</strong> Yes</li>
                    <li><strong>Fail Closed:</strong> Yes</li>
                </ul>

                <p class="mt-3 mb-0 text-muted">
                    Do not switch to live mode until ADTEL provides the full sending endpoint,
                    payload format, success response, error response, and delivery callback format.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection