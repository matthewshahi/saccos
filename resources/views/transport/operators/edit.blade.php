@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-md-10 mx-auto">
    <div class="card">
      <div class="card-header">
        <h5>Edit Operator</h5>
      </div>
      <div class="card-body">
        @if ($errors->any())
          <div class="alert alert-danger">
            <ul class="mb-0">
              @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
              @endforeach
            </ul>
          </div>
        @endif

        <form method="POST" action="{{ route('operators.update', $operator->id) }}">
          @csrf
          <div class="row mb-3">
            <div class="col-md-6">
              <label>Full Name</label>
              <input type="text" name="full_name" class="form-control" value="{{ $operator->full_name }}" required>
            </div>
            <div class="col-md-6">
              <label>Phone</label>
              <input type="text" name="phone" class="form-control" value="{{ $operator->phone }}">
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label>National ID</label>
              <input type="text" name="national_id" class="form-control" value="{{ $operator->national_id }}">
            </div>
            <div class="col-md-6">
              <label>Gender</label>
              <select name="gender" class="form-control">
                <option value="">-- Select --</option>
                <option value="male" {{ $operator->gender === 'male' ? 'selected' : '' }}>Male</option>
                <option value="female" {{ $operator->gender === 'female' ? 'selected' : '' }}>Female</option>
              </select>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label>Operator Type</label>
              <select name="operator_type" class="form-control" required>
                <option value="driver" {{ $operator->operator_type === 'driver' ? 'selected' : '' }}>Driver</option>
                <option value="conductor" {{ $operator->operator_type === 'conductor' ? 'selected' : '' }}>Conductor</option>
              </select>
            </div>
            <div class="col-md-6">
              <label>Status</label>
              <select name="status" class="form-control" required>
                <option value="active" {{ $operator->status === 'active' ? 'selected' : '' }}>Active</option>
                <option value="inactive" {{ $operator->status === 'inactive' ? 'selected' : '' }}>Inactive</option>
              </select>
            </div>
          </div>

          <div class="row mb-3">
            <div class="col-md-6">
              <label>Status Reason</label>
              <input type="text" name="status_reason" class="form-control" value="{{ $operator->status_reason }}">
            </div>
            <div class="col-md-6">
              <label>Introduced By (Member)</label>
              <select name="introduced_by_member_id" class="form-control">
                <option value="">-- Optional --</option>
                @foreach($members as $member)
                  <option value="{{ $member->id }}" {{ $operator->introduced_by_member_id == $member->id ? 'selected' : '' }}>
                    {{ $member->full_name }}
                  </option>
                @endforeach
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label>Notes</label>
            <textarea name="notes" class="form-control" rows="3">{{ $operator->notes }}</textarea>
          </div>

          <div class="text-end">
            <button type="submit" class="btn btn-success">Update Operator</button>
            <a href="{{ route('operators') }}" class="btn btn-secondary">Cancel</a>
          </div>
        </form>

      </div>
    </div>
  </div>
</div>
@endsection