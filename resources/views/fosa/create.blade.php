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
      @include('partials.alerts')
      <div class="card-body">
        <form action="{{ route('fosa.store') }}" method="POST">
  @csrf

  <!-- Type Name -->
  <div class="form-group mb-3">
    <label for="type_name">Type Name</label>
    <input type="text" 
           name="type_name" 
           id="type_name" 
           value="{{ old('type_name') }}"
           class="form-control @error('type_name') is-invalid @enderror" 
           placeholder="e.g. Christmas" required>
    @error('type_name')
      <div class="invalid-feedback">
        {{ $message }}
      </div>
    @enderror
  </div>

  <!-- Prefix -->
  <div class="form-group mb-3">
    <label for="type_prefix">Prefix (2 Letters)</label>
    <input type="text" 
           name="type_prefix" 
           id="type_prefix" 
           value="{{ old('type_prefix') }}"
           class="form-control text-uppercase @error('type_prefix') is-invalid @enderror" 
           maxlength="2" minlength="2"
           placeholder="e.g. CR" required>
    <small class="text-muted">
      Must be 2 letters, not <b>CA</b>, <b>SH</b>, or <b>LN</b>.
    </small>
    @error('type_prefix')
      <div class="invalid-feedback">
        {{ $message }}
      </div>
    @enderror
  </div>

  <button type="submit" class="btn btn-primary">Save</button>
</form>
      </div>
    </div>
  </div>
</div>
@endsection