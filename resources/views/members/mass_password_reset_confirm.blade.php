@extends('layouts.app')

@section('content')

<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1 class="mb-0">Mass Password Reset</h1>
    <div class="header-part-right">
        <ul>
            @if(Auth::check())
                <li>{{ Auth::user()->member_name ?? Auth::user()->name }}</li>
            @endif
        </ul>
    </div>
</div>
<div class="separator-breadcrumb border-top"></div>

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <strong><i class="i-Checked-User"></i> Success:</strong> {{ session('success') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <strong><i class="i-Close-Window"></i> Error:</strong> {{ session('error') }}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

<div class="row">
    <div class="col-lg-8 col-md-10 mx-auto">
        <div class="card mb-4">
            <div class="card-header d-flex align-items-center border-bottom">
                <i class="i-Warning text-danger fs-5 me-2"></i>
                <h4 class="mb-0 text-danger fw-bold">Confirm Mass Password Reset</h4>
            </div>

            <div class="card-body">

                <p class="mb-3 text-muted">
                    You are about to reset passwords for <strong>{{ number_format($total) }}</strong> members.
                    <br>
                    This action will:
                </p>
                <ul class="list-icon list-unstyled mb-4">
                    <li><i class="i-Key text-primary"></i> Generate a new random 8-character password for every member.</li>
                    <li><i class="i-Remove-User text-warning"></i> Log out all currently logged-in users.</li>
                    <li><i class="i-Mail text-success"></i> Create a system notification alerting each member of the change (with their new password).</li>
                </ul>

                <div class="alert alert-warning mb-4">
                    <strong><i class="i-Danger"></i> Once executed, this cannot be undone.</strong><br>
                    Make sure this action is <strong>authorized and necessary</strong>.
                </div>

                <form method="POST" action="{{ route('members.mass_password_reset.execute') }}">
                    @csrf
                    <div class="form-check mb-3">
                        <input type="checkbox" class="form-check-input" id="confirm_reset" name="confirm_reset" required>
                        <label class="form-check-label fw-bold" for="confirm_reset">
                            I confirm that I want to reset passwords for all members.
                        </label>
                    </div>

                    <div class="d-flex justify-content-start mt-4">
                        <button type="submit" class="btn btn-danger btn-rounded me-2">
                            <i class="i-Warning me-1"></i> Confirm & Run Mass Reset
                        </button>
                        <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-rounded">
                            <i class="i-Arrow-Back me-1"></i> Cancel
                        </a>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

@endsection
