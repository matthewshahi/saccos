@extends('layouts.app')

@section('content')
<div class="col-md-12">
@if (session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    {{ session('success') }}
    <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
@endif

@if (session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    {{ session('error') }}
    <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
@endif

@if ($errors->any())
<div class="alert alert-warning alert-dismissible fade show" role="alert">
    <strong>Errors:</strong>
    <ul>
        @foreach ($errors->all() as $error)
            <li>{{ $error }}</li>
        @endforeach
    </ul>
    <button type="button" class="close" data-bs-dismiss="alert" aria-label="Close">
        <span aria-hidden="true">&times;</span>
    </button>
</div>
@endif
    <div class="card mb-4">
        <div class="card-body">
            <div class="card-title mb-3">Edit Kin Type</div>
            <form action="{{ route('kintype.update', $kinType->kin_type_id) }}" method="POST">
                @csrf
                @method('POST')
                <div class="row">
                    <div class="col-md-6 form-group mb-3">
                        <label>Kin Type Name</label>
                        <input class="form-control" name="kin_type_name" type="text" value="{{ $kinType->kin_type_name }}">
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label>Details</label>
                        <input class="form-control" name="kin_type_details" type="text" value="{{ $kinType->kin_type_details }}">
                    </div>
                    <div class="col-md-12">
                        <button class="btn btn-primary">Update</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection