@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Dashboard</h1>
        <div class="header-part-right">
            <ul>
                @if(Auth::check())
                    <li>{{ Auth::user()->member_name }}</li>
                @endif
                @if(isset($currentPeriod))
                    <li><a href="{{ route('admin.periods') }}">{{ $currentPeriod->period_name }}</a></li>
                @endif
                <li><i class="i-Full-Screen header-icon d-none d-sm-inline-block" data-fullscreen=""></i></li>
            </ul>
        </div>
    </div>
    <div class="separator-breadcrumb border-top"></div>
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <div class="header-part-right">
        <a href="{{ route('admin.periods.create') }}" class="btn btn-primary">Add New Period</a>
    </div>
</div>
<div class="separator-breadcrumb border-top"></div>

<div class="container mt-3">
    <!-- Flash Messages -->
    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>

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
