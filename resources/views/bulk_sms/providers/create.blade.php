@extends('layouts.app')

@section('content')
<div class="breadcrumb">
    <h1>Add SMS Provider</h1>
    <ul>
        <li><a href="{{ url('/dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('bulk_sms.index') }}">Bulk SMS</a></li>
        <li><a href="{{ route('bulk_sms.providers') }}">Providers</a></li>
        <li>Add Provider</li>
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
                    Add Bulk SMS Provider
                </div>

                <form method="POST" action="{{ route('bulk_sms.providers.store') }}">
                    @csrf

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label>Provider Code</label>
                            <input type="text"
                                   name="provider_code"
                                   class="form-control"
                                   value="{{ old('provider_code') }}"
                                   placeholder="adtel"
                                   required>
                            <small class="text-muted">
                                Use lowercase short code, for example: adtel, africastalking.
                            </small>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Provider Name</label>
                            <input type="text"
                                   name="provider_name"
                                   class="form-control"
                                   value="{{ old('provider_name') }}"
                                   placeholder="ADTEL Bulk SMS"
                                   required>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Provider Type</label>
                            <select name="provider_type" class="form-control" required>
                                <option value="prsp" {{ old('provider_type', 'prsp') === 'prsp' ? 'selected' : '' }}>
                                    PRSP
                                </option>
                                <option value="aggregator" {{ old('provider_type') === 'aggregator' ? 'selected' : '' }}>
                                    Aggregator
                                </option>
                                <option value="gateway" {{ old('provider_type') === 'gateway' ? 'selected' : '' }}>
                                    Gateway
                                </option>
                                <option value="other" {{ old('provider_type') === 'other' ? 'selected' : '' }}>
                                    Other
                                </option>
                            </select>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label>Provider Driver</label>
                            <input type="text"
                                   name="provider_driver"
                                   class="form-control"
                                   value="{{ old('provider_driver') }}"
                                   placeholder="adtel"
                                   required>
                            <small class="text-muted">
                                This should match the future Laravel driver/service name.
                            </small>
                        </div>

                        <div class="col-md-12 form-group mb-3">
                            <label>Base URL</label>
                            <input type="url"
                                   name="provider_base_url"
                                   class="form-control"
                                   value="{{ old('provider_base_url') }}"
                                   placeholder="https://api.example.com">
                        </div>

                        <div class="col-md-12 form-group mb-3">
                            <label>Token URL</label>
                            <input type="url"
                                   name="provider_token_url"
                                   class="form-control"
                                   value="{{ old('provider_token_url') }}"
                                   placeholder="https://api.example.com/oauth/token">
                        </div>

                        <div class="col-md-12 form-group mb-3">
                            <label>SMS Send URL</label>
                            <input type="url"
                                   name="provider_send_url"
                                   class="form-control"
                                   value="{{ old('provider_send_url') }}"
                                   placeholder="Leave blank until confirmed">
                            <small class="text-muted">
                                Do not guess this value. Add it only from official provider documentation.
                            </small>
                        </div>

                        <div class="col-md-12 form-group mb-3">
                            <label>Balance URL</label>
                            <input type="url"
                                   name="provider_balance_url"
                                   class="form-control"
                                   value="{{ old('provider_balance_url') }}"
                                   placeholder="Optional balance endpoint">
                        </div>

                        <div class="col-md-12 form-group mb-3">
                            <label>Delivery Status URL</label>
                            <input type="url"
                                   name="provider_delivery_status_url"
                                   class="form-control"
                                   value="{{ old('provider_delivery_status_url') }}"
                                   placeholder="Optional delivery status endpoint">
                        </div>

                        <div class="col-md-4 form-group mb-3">
                            <label>Default Network</label>
                            <input type="text"
                                   name="provider_default_network"
                                   class="form-control"
                                   value="{{ old('provider_default_network', 'safaricom') }}"
                                   required>
                        </div>

                        <div class="col-md-4 form-group mb-3">
                            <label>Requires Network</label>
                            <select name="provider_requires_network" class="form-control" required>
                                <option value="Y" {{ old('provider_requires_network', 'Y') === 'Y' ? 'selected' : '' }}>
                                    Yes
                                </option>
                                <option value="N" {{ old('provider_requires_network') === 'N' ? 'selected' : '' }}>
                                    No
                                </option>
                            </select>
                        </div>

                        <div class="col-md-4 form-group mb-3">
                            <label>Provider Enabled</label>
                            <select name="provider_enabled" class="form-control" required>
                                <option value="Y" {{ old('provider_enabled', 'Y') === 'Y' ? 'selected' : '' }}>
                                    Yes
                                </option>
                                <option value="N" {{ old('provider_enabled') === 'N' ? 'selected' : '' }}>
                                    No
                                </option>
                            </select>
                        </div>

                        <div class="col-md-12 form-group mb-3">
                            <label>Provider Notes</label>
                            <textarea name="provider_notes"
                                      class="form-control"
                                      rows="4"
                                      placeholder="Notes about this SMS provider">{{ old('provider_notes') }}</textarea>
                        </div>

                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">
                                Save Provider
                            </button>

                            <a href="{{ route('bulk_sms.providers') }}" class="btn btn-outline-secondary">
                                Back to Providers
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
                <h3 class="card-title m-0">Provider Setup Notes</h3>
            </div>

            <div class="card-body">
                <p>
                    Add a provider only when you have confirmed its official API documentation.
                </p>

                <ul>
                    <li>Provider code should be unique.</li>
                    <li>Send URL should not be guessed.</li>
                    <li>Secret credentials should preferably stay in <code>.env</code>.</li>
                    <li>Provider networks can be added after the provider is created.</li>
                </ul>

                <p class="mb-0 text-muted">
                    For now, ADTEL is already configured. This page is mainly for future providers.
                </p>
            </div>
        </div>
    </div>
</div>
@endsection