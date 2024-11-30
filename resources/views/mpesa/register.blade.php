@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="my-4">Register M-Pesa URLs</h2>

    <!-- Navigation Links -->
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
           
            <li class="breadcrumb-item"><a href="{{ route('mpesa.register.form') }}">M-Pesa Configurations</a></li>
        </ol>
    </nav>

    <!-- Display success message -->
    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <!-- Display error messages -->
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- M-Pesa URL Registration Form -->
    <form action="{{ route('mpesa.register') }}" method="POST">
        @csrf
        <div class="form-group">
            <label for="shortcode">Shortcode</label>
            <input type="text" class="form-control" id="shortcode" name="shortcode" value="{{ old('shortcode', $config->shortcode ?? '') }}" required>
        </div>

        <div class="form-group">
            <label for="response_type">Response Type</label>
            <select class="form-control" id="response_type" name="response_type" required>
                <option value="Completed" {{ old('response_type', $config->response_type ?? '') == 'Completed' ? 'selected' : '' }}>Completed</option>
                <option value="Cancelled" {{ old('response_type', $config->response_type ?? '') == 'Cancelled' ? 'selected' : '' }}>Cancelled</option>
            </select>
        </div>

        <div class="form-group">
            <label for="confirmation_url">Confirmation URL</label>
            <input type="url" class="form-control" id="confirmation_url" name="confirmation_url" value="{{ old('confirmation_url', $config->confirmation_url ?? '') }}" required>
        </div>

        <div class="form-group">
            <label for="validation_url">Validation URL</label>
            <input type="url" class="form-control" id="validation_url" name="validation_url" value="{{ old('validation_url', $config->validation_url ?? '') }}" required>
        </div>

        <div class="form-group">
            <label for="consumer_key">Consumer Key</label>
            <input type="text" class="form-control" id="consumer_key" name="consumer_key" value="{{ old('consumer_key', $config->consumer_key ?? '') }}" required>
        </div>

        <div class="form-group">
            <label for="consumer_secret">Consumer Secret</label>
            <input type="text" class="form-control" id="consumer_secret" name="consumer_secret" value="{{ old('consumer_secret', $config->consumer_secret ?? '') }}" required>
        </div>

        <!-- Passkey Field Added -->
        <div class="form-group">
            <label for="passkey">Passkey</label>
            <input type="text" class="form-control" id="passkey" name="passkey" value="{{ old('passkey', $config->passkey ?? '') }}">
        </div>

        <div class="form-group">
            <label for="api_type">API Type</label>
            <select class="form-control" id="api_type" name="api_type" required>
                <option value="c2b" {{ old('api_type', $config->api_type ?? '') == 'c2b' ? 'selected' : '' }}>C2B</option>
                <option value="mpesa_express" {{ old('api_type', $config->api_type ?? '') == 'mpesa_express' ? 'selected' : '' }}>M-Pesa Express</option>
            </select>
        </div>

        <button type="submit" class="btn btn-primary">Register URLs</button>
    </form>

    <!-- Listing of Configurations Below -->
    <h3 class="mt-5">Existing M-Pesa Configurations</h3>
    <table class="table table-bordered">
        <thead>
            <tr>
                <th>Shortcode</th>
                <th>API Type</th>
                <th>Response Type</th>
                <th>Confirmation URL</th>
                <th>Validation URL</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            @foreach($configs as $config)
                <tr>
                    <td>{{ $config->shortcode }}</td>
                    <td>{{ ucfirst($config->api_type) }}</td>
                    <td>{{ $config->response_type }}</td>
                    <td>{{ $config->confirmation_url }}</td>
                    <td>{{ $config->validation_url }}</td>
                    <td>
                        <a href="{{ route('mpesa.register.form', ['id' => $config->id]) }}" class="btn btn-sm btn-primary">Edit</a>
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
