@extends('layouts.app')

@section('content')
<div class="row">
    @include('transport._nav_links')
  <div class="col-md-8 offset-md-2">
    
    <h4 class="mb-3">Add New Operator</h4>

    <form action="{{ route('operators.store') }}" method="POST" enctype="multipart/form-data">
      @csrf

      <div class="card mb-3">
        <div class="card-body">
          <div class="row">
            <div class="col-md-6 mb-3">
              <label>Full Name</label>
              <input type="text" name="full_name" class="form-control" required>
            </div>

            <div class="col-md-6 mb-3">
              <label>Phone</label>
              <input type="text" name="phone" class="form-control">
            </div>

            <div class="col-md-6 mb-3">
              <label>National ID</label>
              <input type="text" name="national_id" class="form-control">
            </div>

            <div class="col-md-6 mb-3">
              <label>Gender</label>
              <select name="gender" class="form-control">
                <option value="">-- Select --</option>
                <option value="male">Male</option>
                <option value="female">Female</option>
              </select>
            </div>

            <div class="col-md-6 mb-3">
              <label>Operator Type</label>
              <select name="operator_type" class="form-control" required>
                <option value="driver">Driver</option>
                <option value="conductor">Conductor</option>
              </select>
            </div>

            <div class="col-md-6 mb-3">
              <label>Introduced By (Member)</label>
              <select name="introduced_by_member_id" class="form-control">
                <option value="">-- Select --</option>
                @foreach($members as $member)
                  <option value="{{ $member->id }}">{{ $member->full_name }}</option>
                @endforeach
              </select>
            </div>

            <div class="col-md-6 mb-3">
              <label>Status</label>
              <select name="status" class="form-control" required>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>

            <div class="col-md-6 mb-3">
              <label>Status Reason</label>
              <input type="text" name="status_reason" class="form-control">
            </div>

            <div class="col-md-6 mb-3">
              <label>Photo</label>
              <input type="file" name="photo" class="form-control">
            </div>
          </div>
        </div>
      </div>

      <div class="text-end">
        <a href="{{ route('operators') }}" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">Add Operator</button>
      </div>
    </form>
  </div>
</div>
@endsection