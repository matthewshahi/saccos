@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Add New Module</h1>
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

<div class="row mb-4">
    <div class="col-md-8 offset-md-2">
        <div class="card mb-4">
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif
                <form action="{{ route('admin.access-rights.add.module') }}" method="post">
                    @csrf
                    <div class="mb-3">
                        <label for="module_name" class="form-label">Module Name</label>
                        <input type="text" class="form-control" id="module_name" name="module_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="module_description" class="form-label">Module Description</label>
                        <input type="text" class="form-control" id="module_description" name="module_description" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Add Module</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
