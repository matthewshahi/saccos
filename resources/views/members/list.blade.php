@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
  <h1>Members Listing</h1>
  <div class="header-part-right">
    <ul>
      @if(isset($currentPeriod))
        <li><a href="{{ route('admin.periods') }}">{{ $currentPeriod->period_name }}</a></li>
      @endif
      @if(Auth::check())
        <li>{{ Auth::user()->member_name }}</li>
      @endif
      <li><i class="i-Full-Screen header-icon d-none d-sm-inline-block" data-fullscreen></i></li>
    </ul>
  </div>
</div>
<div class="export-buttons mb-3">
    <div class="btn-group">
        <a href="{{ route('members.export.excel', request()->all()) }}" class="btn btn-success">
            <i class="fas fa-file-excel"></i> Export Excel
        </a>
        <a href="{{ route('members.export.csv', request()->all()) }}" class="btn btn-info">
            <i class="fas fa-file-csv"></i> Export CSV
        </a>
    </div>
</div>

<!-- Or if you want a dropdown -->
<div class="dropdown mb-3">
    <button class="btn btn-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
        <i class="fas fa-download"></i> Export Data
    </button>
    <ul class="dropdown-menu">
        <li>
            <a class="dropdown-item" href="{{ route('members.export.excel', request()->all()) }}">
                <i class="fas fa-file-excel text-success"></i> Export as Excel
            </a>
        </li>
        <li>
            <a class="dropdown-item" href="{{ route('members.export.csv', request()->all()) }}">
                <i class="fas fa-file-csv text-info"></i> Export as CSV
            </a>
        </li>
    </ul>
</div>
<div class="separator-breadcrumb border-top"></div>

<div class="row mb-4">
  @if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
  @endif
  @if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
  @endif
  @if ($errors->any())
    <div class="alert alert-danger">
      <ul>
        @foreach ($errors->all() as $error)
          <li>{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <div class="col-md-12 mb-4">
    <div class="card text-start">
      <div class="card-body">
        {{-- Search --}}
        <form action="{{ url()->current() }}" method="get" class="custom-search-form mb-3">
          <div class="input-group">
            <input name="pms_srch" type="text" id="pms_srch" 
                   value="{{ str_replace('%', '', $pms_srch) }}" 
                   class="form-control" 
                   placeholder="Search by name, ID, phone, email, etc.">
            <button class="btn btn-primary" type="submit">Search</button>
          </div>
          <a href="{{ route('members.list.csv', request()->query()) }}" class="btn btn-outline-success mb-3">
    <i class="fa fa-file-csv"></i> Export CSV
</a>

        </form>

        <div class="table-responsive" style="
    min-height: 500px;
">
          <table class="table table-striped table-bordered table-hover align-middle">
            <thead class="thead-light">
              <tr>
                <th>#</th>
                <th>Name & Contact</th>
                <th>Sacco ID</th>
                <th>Date Joined</th>
                <th>Company</th>
                <th>Department</th>
                <th>Position</th>
                <th>Status</th>
                <th>Account Type</th>
                <th class="text-center">Actions</th>
              </tr>
            </thead>
            <tbody>
              @foreach($members as $member)
              <tr>
                <td class="text-center text-muted">{{ $loop->iteration }}</td>
                <td>
                  <div class="fw-bold text-dark">{{ strtoupper($member->member_name) }}</div>
                  <div class="text-muted small">
                    <i class="i-Telephone me-1"></i>{{ $member->member_phone_no }} &nbsp;|&nbsp;
                    <i class="i-ID-Card me-1"></i>{{ $member->member_national_id }} &nbsp;|&nbsp;
                    <i class="i-Mail me-1"></i>{{ $member->member_email }}
                  </div>
                </td>
                <td>{{ $member->member_sacco_id }}</td>
                <td>{{ \Carbon\Carbon::parse($member->member_date_joined)->format('d/m/Y') }}</td>
                <td>{{ $member->company_name }}</td>
                <td>{{ $member->department_name }}</td>
                <td>{{ $member->position_name }}</td>

                {{-- STATUS --}}
                <td>
                  @if ($member->member_active == 'Y')
                    <span class="badge rounded-pill badge-outline-success p-2 px-3">Active</span>
                  @else
                    <span class="badge rounded-pill badge-outline-danger p-2 px-3">Inactive</span>
                  @endif
                </td>

                {{-- ACCOUNT TYPE --}}
                <td>
                  @if ($member->member_is_junior)
                    <span class="badge rounded-pill badge-outline-warning p-2 px-3 text-dark">Junior</span>
                  @else
                    <span class="badge rounded-pill badge-outline-primary p-2 px-3">Standard</span>
                  @endif
                </td>

                {{-- ACTIONS --}}
                <td class="text-center">
                  <div class="btn-group">
                    <button class="btn bg-white _r_btn border-0" type="button" data-bs-toggle="dropdown">
                      <span class="_dot _inline-dot bg-primary"></span>
                      <span class="_dot _inline-dot bg-primary"></span>
                      <span class="_dot _inline-dot bg-primary"></span>
                    </button>
                    <div class="dropdown-menu shadow-sm small">
                      <a class="dropdown-item" href="{{ route('members.edit', $member->member_id) }}">Edit</a>
                      <a class="dropdown-item" href="{{ route('members.nextOfKin', $member->member_id) }}">Next of Kin</a>
                      <a class="dropdown-item" href="{{ route('members.status', $member->member_id) }}">Status</a>
                      <a class="dropdown-item" href="{{ route('members.statement', $member->member_id) }}">Statement</a>
                      <a class="dropdown-item" href="{{ route('members.contributions', $member->member_id) }}">Contributions</a>
                      <div class="dropdown-divider"></div>
                      <a class="dropdown-item" href="{{ route('members.changePassword', $member->member_id) }}">Change Password</a>
                    </div>
                  </div>
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

@section('styles')
<style>
  /* General layout */
  .custom-search-form .form-control {
    border-radius: 0.25rem 0 0 0.25rem;
  }
  .custom-search-form .btn {
    border-radius: 0 0.25rem 0.25rem 0;
  }
  .table th, .table td {
    white-space: nowrap;
    vertical-align: middle;
  }
  .table-striped > tbody > tr:nth-of-type(odd) {
    background-color: rgba(0,0,0,0.02);
  }

  /* Number column */
  td:first-child {
    width: 40px;
  }

  /* Admin-style badge outlines */
  .badge-outline-success {
    border: 1px solid #28a745;
    color: #28a745;
    background-color: rgba(40,167,69,0.1);
  }
  .badge-outline-danger {
    border: 1px solid #dc3545;
    color: #dc3545;
    background-color: rgba(220,53,69,0.1);
  }
  .badge-outline-primary {
    border: 1px solid #007bff;
    color: #007bff;
    background-color: rgba(0,123,255,0.1);
  }
  .badge-outline-warning {
    border: 1px solid #f6c23e;
    color: #856404;
    background-color: rgba(246,194,62,0.15);
  }

  /* Actions button styling */
  ._r_btn {
    padding: 6px;
    border-radius: 50%;
  }
  ._r_btn:hover {
    background-color: #f8f9fa;
  }
  ._dot {
    display: inline-block;
    width: 5px;
    height: 5px;
    border-radius: 50%;
    margin: 0 1.5px;
  }
  ._inline-dot {
    vertical-align: middle;
  }

  .dropdown-menu {
    border: none;
    border-radius: 0.35rem;
  }
</style>
@endsection