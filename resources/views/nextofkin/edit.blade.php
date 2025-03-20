@extends('layouts.app')

@section('content')
<div class="col-md-12">
    <div class="card mb-4">
        <div class="card-body">
            <div class="card-title mb-3">Edit Next of Kin</div>
            <form action="{{ route('nextofkin.update', $nextOfKin->kin_id) }}" method="POST">
                @csrf
                @method('POST')
                <div class="row">
                    <div class="col-md-6 form-group mb-3">
                        <label>Name</label>
                        <input class="form-control" name="kin_names" type="text" value="{{ $nextOfKin->kin_names }}">
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label>National ID</label>
                        <input class="form-control" name="kin_national_id" type="text" value="{{ $nextOfKin->kin_national_id }}">
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