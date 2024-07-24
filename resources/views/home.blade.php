@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Welcome to Sacco</h1>
    <div class="header-part-right">
        <ul>
            <li><a href="{{ route('login') }}">Login</a></li>
            <li><a href="{{ route('register') }}">Register</a></li>
        </ul>
    </div>
</div>
<div class="separator-breadcrumb border-top"></div>

<div class="row mb-4">
    <div class="col-md-12 mb-4">
        <div class="card text-start">
            <div class="card-body">
                <h5 class="card-title">About Sacco</h5>
                <p class="card-text">Information about Sacco...</p>
            </div>
        </div>
    </div>
</div>
@endsection
