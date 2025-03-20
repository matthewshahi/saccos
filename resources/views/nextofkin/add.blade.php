@extends('layouts.app')

@section('content')
<div class="col-md-12">
    <div class="card mb-4">
        <div class="card-body">
            <div class="card-title mb-3">Add Next of Kin</div>
            <form action="{{ route('nextofkin.store') }}" method="POST">
                @csrf
                <div class="row">
                    <div class="col-md-6 form-group mb-3">
                        <label>Name</label>
                        <input class="form-control" name="kin_names" type="text" placeholder="Enter name">
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label>Address</label>
                        <input class="form-control" name="kin_address" type="text" placeholder="Enter address">
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label>National ID</label>
                        <input class="form-control" name="kin_national_id" type="text" placeholder="Enter National ID">
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label>Percentage (%)</label>
                        <input class="form-control" name="kin_percent" type="number" placeholder="Enter percentage">
                    </div>
                    <div class="col-md-6 form-group mb-3">
                        <label>Relationship</label>
                        <input class="form-control" name="kin_relationship" type="text" placeholder="Enter relationship">
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