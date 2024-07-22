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
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
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
                                    <th>Name</th>
                                    <th>Sacco ID</th>
                                    <th>Date Joined</th>
                                    <th>ID Number</th>
                                    <th>Phone Number</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                    <th>Position</th>
                                    <th>Company</th>
                                    <th>Member Active</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($members as $member)
                                    <tr>
                                        <td>{{ $member->member_name }}</td>
                                        <td>{{ $member->member_sacco_id }}</td>
                                        <td>{{ \Carbon\Carbon::parse($member->member_date_joined)->format('d/m/Y') }}</td>
                                        <td>{{ $member->member_national_id }}</td>
                                        <td>{{ $member->member_phone_no }}</td>
                                        <td>{{ $member->member_email }}</td>
                                        <td>{{ $member->department_name }}</td>
                                        <td>{{ $member->position_name }}</td>
                                        <td>{{ $member->company_name }}</td>
                                        <td>{{ $member->member_active == 'Y' ? 'Active' : 'Inactive' }}</td>
                                        <td>
                                        <div class="btn-group">
                                            <button class="btn bg-white _r_btn" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                                <span class="_dot _r_block-dot bg-success"></span><span class="_dot _r_block-dot bg-success"></span><span class="_dot _r_block-dot bg-success"></span>
                                            </button>
                                            <div class="dropdown-menu" x-placement="bottom-start">
                                            <a class="dropdown-item" href="{{ route('members.edit', $member->member_id) }}">Edit</a>
        <a class="dropdown-item" href="{{ route('members.nextOfKin', $member->member_id) }}">Next of Kin</a>
        <a class="dropdown-item" href="{{ route('members.status', $member->member_id) }}">Status</a>
        <a class="dropdown-item" href="{{ route('members.statement', $member->member_id) }}">Statement</a>
        <a class="dropdown-item" href="{{ route('members.contributions', $member->member_id) }}">Member Contributions</a>
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
                                    <th>Name</th>
                                    <th>Sacco ID</th>
                                    <th>Date Joined</th>
                                    <th>ID Number</th>
                                    <th>Phone Number</th>
                                    <th>Email</th>
                                    <th>Department</th>
                                    <th>Position</th>
                                    <th>Company</th>
                                    <th>Member Active</th>
                                    <th>Actions</th>
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
    </style>
@endsection
