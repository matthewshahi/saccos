@extends('layouts.app')

@section('content')
<div class="breadcrumb">
    <h1>Provider Networks</h1>
    <ul>
        <li><a href="{{ url('/dashboard') }}">Dashboard</a></li>
        <li><a href="{{ route('bulk_sms.index') }}">Bulk SMS</a></li>
        <li><a href="{{ route('bulk_sms.providers') }}">Providers</a></li>
        <li>{{ $providerRow->provider_name }}</li>
        <li>Networks</li>
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
    <div class="col-md-12">
        <div class="card o-hidden mb-4">
            <div class="card-header d-flex align-items-center">
                <h3 class="w-50 float-start card-title m-0">
                    {{ $providerRow->provider_name }} Networks
                </h3>

                <div class="text-end w-50 float-end">
                    <a href="{{ route('bulk_sms.provider_configs', $providerRow->provider_code) }}"
                       class="btn btn-sm btn-outline-primary">
                        Provider Configs
                    </a>

                    <a href="{{ route('bulk_sms.diagnostics') }}"
                       class="btn btn-sm btn-outline-secondary">
                        Diagnostics
                    </a>
                </div>
            </div>

            <div class="card-body">
                <div class="alert alert-info">
                    Provider networks control how recipient numbers are classified before sending. For ADTEL,
                    keep these values flexible until ADTEL confirms the exact network values expected in the SMS payload.
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <div class="card-title mb-3">Add Network</div>

                        <form method="POST"
                              action="{{ route('bulk_sms.provider_networks.store', $providerRow->provider_code) }}">
                            @csrf

                            <div class="row">
                                <div class="col-md-2 form-group mb-3">
                                    <label>Network Code</label>
                                    <input type="text"
                                           name="network_code"
                                           class="form-control"
                                           value="{{ old('network_code') }}"
                                           placeholder="safaricom">
                                </div>

                                <div class="col-md-2 form-group mb-3">
                                    <label>Network Name</label>
                                    <input type="text"
                                           name="network_name"
                                           class="form-control"
                                           value="{{ old('network_name') }}"
                                           placeholder="Safaricom">
                                </div>

                                <div class="col-md-2 form-group mb-3">
                                    <label>Provider Value</label>
                                    <input type="text"
                                           name="provider_network_value"
                                           class="form-control"
                                           value="{{ old('provider_network_value') }}"
                                           placeholder="Provider value">
                                </div>

                                <div class="col-md-2 form-group mb-3">
                                    <label>Default</label>
                                    <select name="network_is_default" class="form-control">
                                        <option value="N" {{ old('network_is_default', 'N') === 'N' ? 'selected' : '' }}>
                                            No
                                        </option>
                                        <option value="Y" {{ old('network_is_default') === 'Y' ? 'selected' : '' }}>
                                            Yes
                                        </option>
                                    </select>
                                </div>

                                <div class="col-md-2 form-group mb-3">
                                    <label>Enabled</label>
                                    <select name="network_enabled" class="form-control">
                                        <option value="Y" {{ old('network_enabled', 'Y') === 'Y' ? 'selected' : '' }}>
                                            Yes
                                        </option>
                                        <option value="N" {{ old('network_enabled') === 'N' ? 'selected' : '' }}>
                                            No
                                        </option>
                                    </select>
                                </div>

                                <div class="col-md-1 form-group mb-3">
                                    <label>Order</label>
                                    <input type="number"
                                           name="network_sort_order"
                                           class="form-control"
                                           min="1"
                                           max="999"
                                           value="{{ old('network_sort_order', 10) }}">
                                </div>

                                <div class="col-md-1 form-group mb-3 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        Add
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm align-middle">
                        <thead>
                            <tr>
                                <th style="width: 70px;">#</th>
                                <th style="width: 140px;">Code</th>
                                <th style="width: 180px;">Name</th>
                                <th style="width: 180px;">Provider Value</th>
                                <th style="width: 100px;">Default</th>
                                <th style="width: 100px;">Enabled</th>
                                <th style="width: 100px;">Order</th>
                                <th style="width: 170px;">Updated</th>
                                <th style="width: 170px;">Action</th>
                            </tr>
                        </thead>

                        <tbody>
                            @forelse ($networks as $network)
                                @php
                                    $formId = 'network-form-' . $network->network_id;
                                @endphp

                                <tr>
                                    <td>{{ $network->network_id }}</td>

                                    <td>
                                        <strong>{{ $network->network_code }}</strong>
                                    </td>

                                    <td>
                                        <input type="text"
                                               name="network_name"
                                               form="{{ $formId }}"
                                               class="form-control"
                                               value="{{ old('network_name', $network->network_name) }}">
                                    </td>

                                    <td>
                                        <input type="text"
                                               name="provider_network_value"
                                               form="{{ $formId }}"
                                               class="form-control"
                                               value="{{ old('provider_network_value', $network->provider_network_value) }}"
                                               placeholder="Provider value">
                                    </td>

                                    <td>
                                        <select name="network_is_default"
                                                form="{{ $formId }}"
                                                class="form-control">
                                            <option value="N" {{ $network->network_is_default === 'N' ? 'selected' : '' }}>
                                                No
                                            </option>
                                            <option value="Y" {{ $network->network_is_default === 'Y' ? 'selected' : '' }}>
                                                Yes
                                            </option>
                                        </select>
                                    </td>

                                    <td>
                                        <select name="network_enabled"
                                                form="{{ $formId }}"
                                                class="form-control">
                                            <option value="Y" {{ $network->network_enabled === 'Y' ? 'selected' : '' }}>
                                                Yes
                                            </option>
                                            <option value="N" {{ $network->network_enabled === 'N' ? 'selected' : '' }}>
                                                No
                                            </option>
                                        </select>
                                    </td>

                                    <td>
                                        <input type="number"
                                               name="network_sort_order"
                                               form="{{ $formId }}"
                                               class="form-control"
                                               min="1"
                                               max="999"
                                               value="{{ old('network_sort_order', $network->network_sort_order) }}">
                                    </td>

                                    <td>
                                        {{ $network->network_updated_at ?? '-' }}
                                    </td>

                                    <td class="text-center">
                                        <form id="{{ $formId }}"
                                              method="POST"
                                              action="{{ route('bulk_sms.provider_networks.update', $network->network_id) }}"
                                              style="display:inline;">
                                            @csrf

                                            <button type="submit" class="btn btn-sm btn-primary">
                                                Save
                                            </button>
                                        </form>

                                        <form method="POST"
                                              action="{{ route('bulk_sms.provider_networks.toggle', $network->network_id) }}"
                                              style="display:inline;"
                                              onsubmit="return confirm('Toggle this network status?');">
                                            @csrf

                                            <button type="submit" class="btn btn-sm btn-outline-secondary">
                                                Toggle
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="text-center text-muted">
                                        No provider networks found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <a href="{{ route('bulk_sms.provider_configs', $providerRow->provider_code) }}"
                       class="btn btn-outline-primary">
                        Back to Provider Configs
                    </a>

                    <a href="{{ route('bulk_sms.diagnostics') }}"
                       class="btn btn-outline-secondary">
                        Diagnostics
                    </a>

                    <a href="{{ route('bulk_sms.settings') }}"
                       class="btn btn-outline-info">
                        Global Settings
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection