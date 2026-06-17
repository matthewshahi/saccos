@extends('layouts.app')

@section('content')
<div class="main-content-wrap sidenav-open d-flex flex-column">
    <div class="breadcrumb">
        <h1>SASRA Reports</h1>
        <ul>
            <li><a href="{{ url('/dashboard') }}">Dashboard</a></li>
            <li>{{ $reportName }}</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body text-center py-5">
                    <h3 class="mb-3">{{ $reportName }}</h3>

                    <p class="text-muted mb-4" style="font-size: 16px;">
                        This SASRA report menu has been prepared and will be activated on go-live.
                    </p>

                    <a href="{{ url('/dashboard') }}" class="btn btn-primary">
                        Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection