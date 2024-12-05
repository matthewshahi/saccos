@extends('layouts.app')

@section('content')
<div class="container py-4">
    @include('mpesa.configs.partials.navigation') <!-- Include navigation -->
    <h3>M-Pesa Configurations</h3>
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    <div class="table-responsive">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Shortcode</th>
                    <th>API Type</th>
                    <th>Response Type</th>
                    <th>Confirmation URL</th>
                    <th>Validation URL</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($configs as $config)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $config->shortcode }}</td>
                        <td>{{ $config->api_type }}</td>
                        <td>{{ $config->response_type }}</td>
                        <td>{{ $config->confirmation_url }}</td>
                        <td>{{ $config->validation_url }}</td>
                        <td>
                            <a href="{{ route('mpesa_config.edit', $config->id) }}" class="btn btn-warning btn-sm">Edit</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7">No configurations found.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection