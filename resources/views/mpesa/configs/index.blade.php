@extends('layouts.app')

@section('content')
<div class="container py-4">
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h4 class="mb-0">M-Pesa Configurations</h4>
                <a href="{{ route('mpesa_config.create') }}" class="btn btn-success btn-sm">Add New Configuration</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped text-center">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Shortcode</th>
                                <th>API Type</th>
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
                                    <td>{{ $config->confirmation_url }}</td>
                                    <td>{{ $config->validation_url }}</td>
                                    <td>
                                        <a href="{{ route('mpesa_config.edit', $config->id) }}" class="btn btn-warning btn-sm">Edit</a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6">No configurations found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection