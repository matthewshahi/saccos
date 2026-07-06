@extends('layouts.app')

@section('content')
<div class="breadcrumb">
    <h1>Provider Configs</h1>
    <ul>
        <li><a href="{{ url('/dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('bulk_sms.index') }}">Bulk SMS</a></li>
        <li><a href="{{ route('bulk_sms.providers') }}">Providers</a></li>
        <li>{{ $providerRow->provider_name }}</li>
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

<div class="card o-hidden mb-4">
    <div class="card-header d-flex align-items-center">
        <h3 class="w-50 float-start card-title m-0">
            {{ $providerRow->provider_name }} Configs
        </h3>

        <div class="text-end w-50 float-end">
            <a href="{{ route('bulk_sms.providers') }}" class="btn btn-sm btn-outline-secondary">
                Providers
            </a>

            <a href="{{ route('bulk_sms.diagnostics') }}" class="btn btn-sm btn-outline-primary">
                Diagnostics
            </a>
        </div>
    </div>

    <div class="card-body">
        <div class="alert alert-info">
            <strong>Important:</strong>
            Secret values can be stored through ENV keys. If a config is marked secret and you leave the value blank,
            the existing database value will not be overwritten.
        </div>

        <div class="table-responsive">
            <table class="table table-bordered table-sm align-middle">
                <thead>
                    <tr>
                        <th style="width: 140px;">Key</th>
                        <th style="width: 220px;">Value</th>
                        <th style="width: 180px;">ENV Key</th>
                        <th style="width: 90px;">Secret</th>
                        <th style="width: 100px;">Required</th>
                        <th>Description</th>
                        <th style="width: 110px;">Action</th>
                    </tr>
                </thead>

                <tbody>
                    @forelse ($configs as $config)
                        @php
                            $formId = 'config-form-' . $config->config_id;
                            $isSecret = strtoupper((string) $config->config_is_secret) === 'Y';
                        @endphp

                        <tr>
                            <td>
                                <strong>{{ $config->config_key }}</strong>
                                <br>
                                <small class="text-muted">#{{ $config->config_id }}</small>
                            </td>

                            <td>
                                @if ($isSecret)
                                    <input type="password"
                                           name="config_value"
                                           form="{{ $formId }}"
                                           class="form-control"
                                           placeholder="{{ $config->config_value ? 'Stored value hidden' : 'Enter secret value' }}">
                                    <small class="text-muted">
                                        Leave blank to keep existing secret.
                                    </small>
                                @else
                                    <input type="text"
                                           name="config_value"
                                           form="{{ $formId }}"
                                           class="form-control"
                                           value="{{ old('config_value', $config->config_value) }}">
                                @endif
                            </td>

                            <td>
                                <input type="text"
                                       name="config_env_key"
                                       form="{{ $formId }}"
                                       class="form-control"
                                       value="{{ old('config_env_key', $config->config_env_key) }}"
                                       placeholder="Example: ADTEL_CLIENT_ID">
                            </td>

                            <td>
                                <select name="config_is_secret"
                                        form="{{ $formId }}"
                                        class="form-control">
                                    <option value="N" {{ $config->config_is_secret === 'N' ? 'selected' : '' }}>
                                        No
                                    </option>
                                    <option value="Y" {{ $config->config_is_secret === 'Y' ? 'selected' : '' }}>
                                        Yes
                                    </option>
                                </select>
                            </td>

                            <td>
                                <select name="config_is_required"
                                        form="{{ $formId }}"
                                        class="form-control">
                                    <option value="N" {{ $config->config_is_required === 'N' ? 'selected' : '' }}>
                                        No
                                    </option>
                                    <option value="Y" {{ $config->config_is_required === 'Y' ? 'selected' : '' }}>
                                        Yes
                                    </option>
                                </select>
                            </td>

                            <td>
                                <input type="text"
                                       name="config_description"
                                       form="{{ $formId }}"
                                       class="form-control"
                                       value="{{ old('config_description', $config->config_description) }}">
                            </td>

                            <td class="text-center">
                                <form id="{{ $formId }}"
                                      method="POST"
                                      action="{{ route('bulk_sms.provider_configs.update', $config->config_id) }}">
                                    @csrf

                                    <button type="submit" class="btn btn-sm btn-primary">
                                        Save
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted">
                                No provider configs found.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-3">
            <a href="{{ route('bulk_sms.diagnostics') }}" class="btn btn-outline-secondary">
                Back to Diagnostics
            </a>

            <a href="{{ route('bulk_sms.provider_networks', $providerRow->provider_code) }}" class="btn btn-outline-primary">
                Manage Networks
            </a>
        </div>
    </div>
</div>
@endsection