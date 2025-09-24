@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-md-6">
    <div class="card o-hidden mb-4">
      <div class="card-header d-flex align-items-center">
        <h3 class="w-50 float-start card-title m-0">Add FOSA Type</h3>
         <div class="text-end w-50 float-end">
    <a href="{{ route('fosa.index') }}" class="btn btn-primary shadow-sm">
        Back to List
    </a>
</div>
      </div>
      <div class="card-body">
        <form action="{{ route('fosa.store') }}" method="POST">
          @csrf
          <div class="form-group mb-3">
            <label for="type_name">Type Name</label>
            <input type="text" name="type_name" id="type_name" class="form-control" placeholder="e.g. Christmas" required>
          </div>
          <button type="submit" class="btn btn-primary">Save</button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection