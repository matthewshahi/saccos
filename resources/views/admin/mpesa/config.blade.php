@extends('layouts.app')

@section('content')
<div class="container">
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-body">
                <div class="card-title mb-3">M-Pesa Configuration</div>
                <form action="{{ route('mpesa.config.store') }}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label for="paybill_number">Paybill Number</label>
                            <input class="form-control" id="paybill_number" name="paybill_number" type="text" placeholder="Enter Paybill Number" value="{{ old('paybill_number', $config->paybill_number ?? '') }}" required>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label for="api_key">API Key</label>
                            <input class="form-control" id="api_key" name="api_key" type="text" placeholder="Enter API Key" value="{{ old('api_key', $config->api_key ?? '') }}" required>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label for="secret_key">Secret Key</label>
                            <input class="form-control" id="secret_key" name="secret_key" type="password" placeholder="Enter Secret Key" value="{{ old('secret_key', $config->secret_key ?? '') }}" required>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label for="initiator_name">Initiator Name</label>
                            <input class="form-control" id="initiator_name" name="initiator_name" type="text" placeholder="Enter Initiator Name" value="{{ old('initiator_name', $config->initiator_name ?? '') }}" required>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label for="security_credential">Security Credential</label>
                            <input class="form-control" id="security_credential" name="security_credential" type="password" placeholder="Enter Security Credential" value="{{ old('security_credential', $config->security_credential ?? '') }}" required>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label for="callback_url">Callback URL</label>
                            <input class="form-control" id="callback_url" name="callback_url" type="text" placeholder="Enter Callback URL" value="{{ old('callback_url', $config->callback_url ?? '') }}" required>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label for="success_url">Success URL</label>
                            <input class="form-control" id="success_url" name="success_url" type="text" placeholder="Enter Success URL" value="{{ old('success_url', $config->success_url ?? '') }}" required>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label for="failure_url">Failure URL</label>
                            <input class="form-control" id="failure_url" name="failure_url" type="text" placeholder="Enter Failure URL" value="{{ old('failure_url', $config->failure_url ?? '') }}" required>
                        </div>
                        <div class="col-md-12">
                            <button class="btn btn-primary" type="submit">Save Configuration</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
