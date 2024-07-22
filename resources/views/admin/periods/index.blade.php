@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Periods Management</h1>
        <div class="header-part-right">
            <a href="{{ route('admin.periods.create') }}" class="btn btn-primary">Add New Period</a>
        </div>
    </div>
    <div class="separator-breadcrumb border-top"></div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th>Period Name</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['periods'] as $period)
                    <tr>
                        <td>{{ $period->period_name }}</td>
                        <td>{{ $period->period_active == 'Y' ? 'Active' : 'Inactive' }}</td>
                        <td>
                            @if($period->period_active != 'Y')
                                <a href="{{ route('admin.periods.activate', ['id' => $period->period_id]) }}" class="btn btn-success btn-sm">Activate</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
