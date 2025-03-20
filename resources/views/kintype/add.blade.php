@extends('layouts.app')

@section('content')
<div class="col-md-12">
    <div class="card mb-4">
        <div class="card-body">
            <div class="card-title mb-3">Add Kin Type</div>
            <form action="{{ route('kintype.store') }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-6 form-group mb-3">
                        <label>Kin Type Name</label>
                        <input class="form-control" name="kin_type_name" type="text" placeholder="Enter kin type name">
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label>Details</label>
                        <input class="form-control" name="kin_type_details" type="text" placeholder="Enter details">
                    </div>
                    <div class="col-md-12">
                        <button class="btn btn-primary">Submit</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection