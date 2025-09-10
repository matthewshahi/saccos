@extends('layouts.app')

@section('content')
<div class="container">
  <h1 class="mb-4">Import Members (CSV)</h1>

  @if ($errors->any())
    <div class="alert alert-danger">
      <ul class="mb-0">
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @if (session('summary'))
    @php($s = session('summary'))
    <div class="card mb-4">
      <div class="card-body">
        <h5 class="card-title">Import Summary</h5>
        <ul class="mb-0">
          <li>Total rows scanned: <strong>{{ $s['total_rows'] }}</strong></li>
          <li>Existing matches (skipped): <strong>{{ $s['matched'] }}</strong></li>
          <li>New members (inserted): <strong>{{ $s['inserted'] }}</strong></li>
        </ul>
      </div>
    </div>

    @if (!empty($s['new_members_preview']))
      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title">Inserted Members (first 200)</h5>
          <div class="table-responsive">
            <table class="table table-sm table-bordered">
              <thead><tr><th>#</th><th>Name</th><th>Email</th><th>SACCO ID</th><th>National ID</th></tr></thead>
              <tbody>
              @foreach ($s['new_members_preview'] as $i => $nm)
                <tr>
                  <td>{{ $i + 1 }}</td>
                  <td>{{ $nm['member_name'] }}</td>
                  <td>{{ $nm['member_email'] }}</td>
                  <td>{{ $nm['member_sacco_id'] }}</td>
                  <td>{{ $nm['member_national_id'] }}</td>
                </tr>
              @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    @endif
  @endif

  <div class="card">
    <div class="card-body">
      <form action="{{ route('members.import') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
          <label for="csv" class="form-label">CSV File</label>
          <input type="file" class="form-control" id="csv" name="csv" accept=".csv,text/csv" required>
          <div class="form-text">
            Required column: <code>name</code> (case-insensitive). Other columns ignored for creation.
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Import</button>
      </form>
    </div>
  </div>
</div>
@endsection