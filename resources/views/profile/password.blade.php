@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1 class="mb-0">Change Password</h1>
    <div class="header-part-right">
        <i class="i-Full-Screen header-icon d-none d-sm-inline-block" data-fullscreen=""></i>
    </div>
</div>

{{-- 🔶 Mandatory password change alert --}}
@if (request()->has('days'))
    @php $days = request()->get('days'); @endphp
    <div class="alert alert-warning d-flex align-items-center mt-3" role="alert" style="max-width: 700px;">
        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
        <span>
            Mandatory password change after <strong>{{ $days }}</strong> days.
            Please update your password to continue using the system.
        </span>
    </div>
@endif

<div class="separator-breadcrumb border-top"></div>

{{-- ✅ Success message --}}
@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

{{-- ❌ Error messages --}}
@if($errors->any())
    <div class="alert alert-danger">
        <ul class="mb-0">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- 🔒 Change password form --}}
<div class="card mb-5">
    <div class="card-body">
        <form method="POST" action="{{ route('profile.updatePassword') }}">
            @csrf
            <div class="form-group row mb-3">
                <label for="current_password" class="col-sm-2 col-form-label">Current Password</label>
                <div class="col-sm-10">
                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                </div>
            </div>

            <div class="form-group row mb-3">
                <label for="new_password" class="col-sm-2 col-form-label">New Password</label>
                <div class="col-sm-10">
                    <input type="password" class="form-control" id="new_password" name="new_password" required>
                </div>
            </div>

            <div class="form-group row mb-4">
                <label for="new_password_confirmation" class="col-sm-2 col-form-label">Confirm New Password</label>
                <div class="col-sm-10">
                    <input type="password" class="form-control" id="new_password_confirmation" name="new_password_confirmation" required>
                </div>
            </div>

            <div class="form-group row">
                <div class="col-sm-10 offset-sm-2">
                    <button type="submit" class="btn btn-primary">
                        Change Password
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection