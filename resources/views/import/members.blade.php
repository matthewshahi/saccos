@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Import Members</h1>
</div>
<div class="separator-breadcrumb border-top"></div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<div class="card">
    <div class="card-body">
        <h4 class="card-title">Upload CSV File</h4>
        <form action="{{ route('import.members.process') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <div class="form-group">
        <label for="import_file">Select CSV File</label>
        <input type="file" name="import_file" class="form-control" id="import_file" required>
    </div>
    <button type="submit" class="btn btn-primary">Import Members</button>
</form>
    </div>
</div>
@endsection