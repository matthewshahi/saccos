@extends('layouts.app')

@section('content')
<div class="row">
  <div class="col-md-12">
    <div class="card o-hidden mb-4">
      <div class="card-header d-flex align-items-center">
        <h3 class="w-50 float-start card-title m-0">FOSA Deposit Types</h3>
       
        <div class="text-end w-50 float-end">
    <a href="{{ route('fosa.create') }}" class="btn btn-primary shadow-sm">
        Add New
    </a>
</div>
      </div>
      <div class="card-body">
        <div class="table-responsive">
          <table class="table text-center">
            <thead>
              <tr>
                <th>#</th>
                <th>Type Name</th>
                <th>Default</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              @foreach($records as $i => $rec)
              <tr>
                <td>{{ $i+1 }}</td>
                <td>{{ $rec->type_name }}</td>
                <td>
                  @if($rec->type_default == 'Y')
                    <span class="badge bg-info">Default</span>
                  @endif
                </td>
                <td>
                  @if($rec->type_active == 'Y')
                    <span class="badge bg-success">Active</span>
                  @else
                    <span class="badge bg-danger">Inactive</span>
                  @endif
                </td>
                <td>
                  <form action="{{ route('fosa.toggle', $rec->type_id) }}" method="POST">
                    @csrf
                    <button type="submit" class="btn btn-sm {{ $rec->type_active == 'Y' ? 'btn-warning' : 'btn-success' }}">
                      {{ $rec->type_active == 'Y' ? 'Disable' : 'Enable' }}
                    </button>
                  </form>
                </td>
              </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection