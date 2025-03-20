@extends('layouts.app')

@section('content')
<div class="col-md-12">
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