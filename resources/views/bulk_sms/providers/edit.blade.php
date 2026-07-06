@extends('layouts.app')

@section('content')
<div class="breadcrumb">
    <h1>Edit SMS Provider</h1>
    <ul>
        <li><a href="{{ url('/dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('bulk_sms.index') }}">Bulk SMS</a></li>
        <li><a href="{{ route('bulk_sms.providers') }}">Providers</a></li>
        <li>Edit {{ $providerRow->provider_name }}</li>
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
                <div class="card-title mb-3">
                    Edit Provider: {{ $providerRow->provider_name }}
                </div>

                <form method="POST" action="{{ route('bulk_sms.providers.update', $providerRow->provider_code) }}">
                    @csrf

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label>Provider Code</label>
                            <input type="text"
                                   class="form-control"
                                   value="{{ $providerRow->provider_code }}"
                                   disabled>
                            <small class="text-muted">
                                Provider code is fixed after creation.
                            </small>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Provider Name</label>
                            <input type="text"
                                   name="provider_name"
                                   class="form-control"
                                   value="{{ old('provider_name', $providerRow->provider_name) }}"
                                   required>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Provider Type</label>
                            <select name="provider_type" class="form-control" required>
                                <option value="prsp" {{ old('provider_type', $providerRow->provider_type) === 'prsp' ? 'selected' : '' }}>
                                    PRSP
                                </option>
                                <option value="aggregator" {{ old('provider_type', $providerRow->provider_type) === 'aggregator' ? 'selected' : '' }}>
                                    Aggregator
                                </option>
                                <option value="gateway" {{ old('provider_type', $providerRow->provider_type) === 'gateway' ? 'selected' : '' }}>
                                    Gateway
                                </option>
                                <option value="other" {{ old('provider_type', $providerRow->provider_type) === 'other' ? 'selected' : '' }}>
                                    Other
                                </option>
                            </select>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Provider Driver</label>
                            <input type="text"
                                   name="provider_driver"
                                   class="form-control"
                                   value="{{ old('provider_driver', $providerRow->provider_driver) }}"
                                   placeholder="adtel"
                                   required>
                            <small class="text-muted">
                                Example: adtel, africastalking, celcom, twilio.
                            </small>
                        </div>

                        <div class="col-md-12 form-group mb-3">
                            <label>Base URL</label>
                            <input type="url"
                                   name="provider_base_url"
                                   class="form-control"
                                   value="{{ old('provider_base_url', $providerRow->provider_base_url) }}"
                                   placeholder="https://api.adtel.co.ke">
                        </div>

                        <div class="col-md-12 form-group mb-3">
                            <label>Token URL</label>
                            <input type="url"
                                   name="provider_token_url"
                                   class="form-control"
                                   value="{{ old('provider_token_url', $providerRow->provider_token_url) }}"
                                   placeholder="https://api.adtel.co.ke/oath/token">
                        </div>

                        <div class="col-md-12 form-group mb-3">
                            <label>SMS Send URL</label>
                            <input type="url"
                                   name="provider_send_url"
                                   class="form-control"
                                   value="{{ old('provider_send_url', $providerRow->provider_send_url) }}"
                                   placeholder="Pending from provider">
                            <small class="text-muted">
                                Leave blank until ADTEL gives the actual SMS sending endpoint.
                            </small>
                        </div>

                        <div class="col-md-12 form-group mb-3">
                            <label>Balance URL</label>
                            <input type="url"
                                   name="provider_balance_url"
                                   class="form-control"
                                   value="{{ old('provider_balance_url', $providerRow->provider_balance_url) }}"
                                   placeholder="Optional balance endpoint">
                        </div>

                        <div class="col-md-12 form-group mb-3">
                            <label>Delivery Status URL</label>
                            <input type="url"
                                   name="provider_delivery_status_url"
                                   class="form-control"
                                   value="{{ old('provider_delivery_status_url', $providerRow->provider_delivery_status_url) }}"
                                   placeholder="Optional delivery status endpoint">
                        </div>

                        <div class="col-md-4 form-group mb-3">
                            <label>Default Network</label>
                            <input type="text"
                                   name="provider_default_network"
                                   class="form-control"
                                   value="{{ old('provider_default_network', $providerRow->provider_default_network) }}"
                                   placeholder="safaricom"
                                   required>
                        </div>

                        <div class="col-md-4 form-group mb-3">
                            <label>Requires Network</label>
                            <select name="provider_requires_network" class="form-control" required>
                                <option value="Y" {{ old('provider_requires_network', $providerRow->provider_requires_network) === 'Y' ? 'selected' : '' }}>
                                    Yes
                                </option>
                                <option value="N" {{ old('provider_requires_network', $providerRow->provider_requires_network) === 'N' ? 'selected' : '' }}>
                                    No
                                </option>
                            </select>
                        </div>

                        <div class="col-md-4 form-group mb-3">
                            <label>Provider Enabled</label>
                            <select name="provider_enabled" class="form-control" required>
                                <option value="Y" {{ old('provider_enabled', $providerRow->provider_enabled) === 'Y' ? 'selected' : '' }}>
                                    Yes
                                </option>
                                <option value="N" {{ old('provider_enabled', $providerRow->provider_enabled) === 'N' ? 'selected' : '' }}>
                                    No
                                </option>
                            </select>
                        </div>

                        <div class="col-md-12 form-group mb-3">
                            <label>Provider Notes</label>
                            <textarea name="provider_notes"
                                      class="form-control"
                                      rows="4"
                                      placeholder="Notes about this SMS provider">{{ old('provider_notes', $providerRow->provider_notes) }}</textarea>
                        </div>

                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">
                                Save Provider
                            </button>

                            <a href="{{ route('bulk_sms.providers') }}" class="btn btn-outline-secondary">
                                Back to Providers
                            </a>

                            <a href="{{ route('bulk_sms.provider_configs', $providerRow->provider_code) }}" class="btn btn-outline-primary">
                                Provider Configs
                            </a>

                            <a href="{{ route('bulk_sms.provider_networks', $providerRow->provider_code) }}" class="btn btn-outline-info">
                                Networks
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
                <h3 class="card-title m-0">Provider Safety</h3>
            </div>

            <div class="card-body">
                <p>
                    Keep ADTEL enabled as a configured provider, but do not add a fake send URL.
                </p>

                <ul>
                    <li>Token URL is known.</li>
                    <li>Send URL is still pending.</li>
                    <li>Balance URL is still pending.</li>
                    <li>Delivery callback format is still pending.</li>
                </ul>

                <p class="mb-0 text-muted">
                    Live SMS should remain blocked until the actual provider send endpoint and payload format are confirmed.
                </p>
            </div>
        </div>

        <div class="card o-hidden mb-4">
            <div class="card-header">
                <h3 class="card-title m-0">Current Provider</h3>
            </div>

            <div class="card-body">
                <table class="table table-bordered table-sm">
                    <tbody>
                        <tr>
                            <th>ID</th>
                            <td>{{ $providerRow->provider_id }}</td>
                        </tr>
                        <tr>
                            <th>Code</th>
                            <td>{{ $providerRow->provider_code }}</td>
                        </tr>
                        <tr>
                            <th>Driver</th>
                            <td>{{ $providerRow->provider_driver }}</td>
                        </tr>
                        <tr>
                            <th>Enabled</th>
                            <td>
                                @if ($providerRow->provider_enabled === 'Y')
                                    <span class="badge bg-success">Yes</span>
                                @else
                                    <span class="badge bg-danger">No</span>
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <th>Created</th>
                            <td>{{ $providerRow->provider_created_at ?? '-' }}</td>
                        </tr>
                        <tr>
                            <th>Updated</th>
                            <td>{{ $providerRow->provider_updated_at ?? '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection