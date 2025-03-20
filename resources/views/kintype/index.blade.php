@extends('layouts.app')

@section('content')
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
<div class="card o-hidden mb-4">
    <div class="card-header d-flex align-items-center">
        <h3 class="w-50 float-start card-title m-0">Kin Types</h3>
        <div class="text-end w-50 float-end">
            <a href="{{ route('kintype.add') }}" class="btn btn-primary">Add Kin Type</a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table text-center">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Kin Type Name</th>
                        <th>Details</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($kinTypes as $kinType)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $kinType->kin_type_name }}</td>
                        <td>{{ $kinType->kin_type_details }}</td>
                        <td>
                            <a href="{{ route('kintype.edit', $kinType->kin_type_id) }}" class="text-success me-2"><i class="nav-icon i-Pen-2 fw-bold"></i></a>
                            <a href="#" class="text-danger me-2"><i class="nav-icon i-Close-Window fw-bold"></i></a>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection