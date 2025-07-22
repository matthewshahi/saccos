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
                <li><i class="i-Full-Screen header-icon d-none d-sm-inline-block" data-fullscreen=""></i></li>
            </ul>
        </div>
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
                    <form action="{{ url()->current() }}" method="get" class="custom-search-form">
                        <div class="input-group mb-3">
                            <input name="pms_srch" type="text" id="pms_srch" value="{{ str_replace('%', '', $pms_srch) }}" class="form-control" placeholder="Search by name, ID, phone, email, etc.">
                            <div class="input-group-append">
                                <button class="btn btn-primary" type="submit">Search</button>
                            </div>
                        </div>
                    </form>
                    
                    <div class="table-responsive">
                        <table class="display table table-striped table-bordered" id="multicolumn_ordering_table" style="width: 100%">
                        <thead>
    <tr>
        <th style="white-space: nowrap;">Name</th>
        <th style="white-space: nowrap;">Sacco ID</th>
        <th style="white-space: nowrap;">Phone</th>
        <th style="white-space: nowrap;">Email</th>
        <th style="white-space: nowrap;">ID Number</th>
        <th style="white-space: nowrap;">Date Joined</th>
        <th style="white-space: nowrap;">Company</th>
        <th style="white-space: nowrap;">Department</th>
        <th style="white-space: nowrap;">Position</th>
        <th style="white-space: nowrap;">Status</th>
        <th style="white-space: nowrap;">Account Type</th>
        <th style="white-space: nowrap;">Actions</th>
    </tr>
</thead>
<tbody>
    @foreach($members as $member)
        <tr>
            <td style="white-space: nowrap;">{{ $member->member_name }}</td>
            <td style="white-space: nowrap;">{{ $member->member_sacco_id }}</td>
            <td style="white-space: nowrap;">{{ $member->member_phone_no }}</td>
            <td style="white-space: nowrap;">{{ $member->member_email }}</td>
            <td style="white-space: nowrap;">{{ $member->member_national_id }}</td>
            <td style="white-space: nowrap;">{{ \Carbon\Carbon::parse($member->member_date_joined)->format('d/m/Y') }}</td>
            <td style="white-space: nowrap;">{{ $member->company_name }}</td>
            <td style="white-space: nowrap;">{{ $member->department_name }}</td>
            <td style="white-space: nowrap;">{{ $member->position_name }}</td>
            

            <td style="white-space: nowrap;">{{ $member->member_active == 'Y' ? 'Active' : 'Inactive' }}</td>
            <td style="white-space: nowrap;">
    @if ($member->member_is_junior)
        <span class="badge bg-warning text-dark">Junior</span>
    @else
        <span class="badge bg-primary">Standard</span>
    @endif
</td>
            <td style="white-space: nowrap;">
                <div class="btn-group">
                    <button class="btn bg-white _r_btn" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="_dot _r_block-dot bg-success"></span>
                        <span class="_dot _r_block-dot bg-success"></span>
                        <span class="_dot _r_block-dot bg-success"></span>
                    </button>
                    <div class="dropdown-menu">
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
<tfoot>
    <tr>
        <th style="white-space: nowrap;">Name</th>
        <th style="white-space: nowrap;">Sacco ID</th>
        <th style="white-space: nowrap;">Phone</th>
        <th style="white-space: nowrap;">Email</th>
        <th style="white-space: nowrap;">ID Number</th>
        <th style="white-space: nowrap;">Date Joined</th>
        <th style="white-space: nowrap;">Company</th>
        <th style="white-space: nowrap;">Department</th>
        <th style="white-space: nowrap;">Position</th>
        <th style="white-space: nowrap;">Status</th>
        <th style="white-space: nowrap;">Account Type</th>
        <th style="white-space: nowrap;">Actions</th>
    </tr>
</tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('styles')
    <style>
        .custom-search-form {
            margin-bottom: 20px;
        }
        .custom-search-form .form-control {
            border-radius: 0.25rem;
        }
        .custom-search-form .btn {
            border-radius: 0.25rem;
        }
        .table td, .table th {
            white-space: nowrap; /* Prevents wrapping */
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px; /* Adjust as needed */
        }
    </style>
@endsection