@extends('layouts.app')

@section('content')
<div class="container">
  <h1 class="mb-4">Patch Members from CSV (Update or Insert)</h1>

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
        <h5 class="card-title">Summary</h5>
        <ul class="mb-0">
          <li>Total rows scanned: <strong>{{ $s['total_rows'] }}</strong></li>
          <li>Updated existing members: <strong>{{ $s['matched_updated'] }}</strong></li>
          <li>Newly inserted members: <strong>{{ $s['new_inserted'] }}</strong></li>
        </ul>
      </div>
    </div>

    {{-- Updates Preview --}}
    @if (!empty($s['updates_preview']))
      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title">Updates (first 200)</h5>
          <div class="table-responsive">
            <table class="table table-sm table-bordered">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Name</th>
                  <th>SACCO #</th>
                  <th>National ID</th>
                  <th>Phone</th>
                  <th>DOB</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($s['updates_preview'] as $i => $u)
                  <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $u['name'] }}</td>
                    <td>{{ $u['member_sacco_id'] ?? '' }}</td>
                    <td>{{ $u['member_national_id'] ?? '' }}</td>
                    <td>{{ $u['member_phone_no'] ?? '' }}</td>
                    <td>{{ $u['member_dob'] ?? '' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        </div>
      </div>
    @endif

    {{-- New Members Preview --}}
    @if (!empty($s['new_members_preview']))
      <div class="card mb-4">
        <div class="card-body">
          <h5 class="card-title">New Members (first 200)</h5>
          <div class="table-responsive">
            <table class="table table-sm table-bordered">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Name</th>
                  <th>SACCO #</th>
                  <th>National ID</th>
                  <th>Phone</th>
                  <th>DOB</th>
                </tr>
              </thead>
              <tbody>
                @foreach ($s['new_members_preview'] as $i => $n)
                  <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ $n['name'] }}</td>
                    <td>{{ $n['member_sacco_id'] ?? '' }}</td>
                    <td>{{ $n['member_national_id'] ?? '' }}</td>
                    <td>{{ $n['member_phone_no'] ?? '' }}</td>
                    <td>{{ $n['member_dob'] ?? '' }}</td>
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
      <form action="{{ route('members.patch') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <div class="mb-3">
          <label for="csv" class="form-label">CSV File</label>
          <input type="file" class="form-control" id="csv" name="csv" accept=".csv,text/csv" required>
          <div class="form-text">
            Expected columns (any case; extra spaces ok). If not detected, we’ll use column order:<br>
            <code>NAME | PHONE NUMBER | IDENTIFICATION NUMBER | SACCO NUMBER | DATE OF BIRTH</code><br>
            Row 1 is treated as header and skipped.
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Run Patch</button>
      </form>
    </div>
  </div>
</div>
@endsection