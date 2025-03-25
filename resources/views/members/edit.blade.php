@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Edit Member</h1>
        <div class="header-part-right">
        <ul>
                @if(Auth::check())
                    <li>{{ Auth::user()->member_name }}</li>
                @endif
                @if(isset($currentPeriod))
                    <li><a href="{{ route('admin.periods') }}">{{ $currentPeriod->period_name }}</a></li>
                @endif

              
              <li><i class="i-Full-Screen header-icon d-none d-sm-inline-block" data-fullscreen=""></i></li>
            </ul>
            
        </div>
    </div>
    <div class="separator-breadcrumb border-top"></div>

    <div class="row mb-4">
        <div class="col-md-12">
            <div class="card text-start">
                <div class="card-body">
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('members.update', $data['member']->member_id) }}" method="POST">
                        @csrf
                        @method('POST')

                        <!-- Personal Information -->
                        <h5 class="mb-4">Personal Information | 
                        <a href="{{ route('members.edit.image', $data['member']->member_id) }}" class="btn btn-sm btn-outline-primary">
                    Upload Member Docs
                </a></h5>
                        <div class="form-group row">
                            <label for="member_name" class="col-sm-2 col-form-label">Member Name*</label>
                            <div class="col-sm-10">
                                <input type="text" class="form-control" id="member_name" name="member_name" value="{{ old('member_name', $data['member']->member_name) }}" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="member_gender" class="col-sm-2 col-form-label">Gender</label>
                            <div class="col-sm-10">
                                <select class="form-control" id="member_gender" name="member_gender">
                                    <option value="M" {{ old('member_gender', $data['member']->member_gender) == 'M' ? 'selected' : '' }}>Male</option>
                                    <option value="F" {{ old('member_gender', $data['member']->member_gender) == 'F' ? 'selected' : '' }}>Female</option>
                                </select>
                            </div>
                        </div>
                        <?php
                        // dd($data['member']->member_dob);
                        $data['member']->member_dob = substr($data['member']->member_dob,0,10);
                         ?>
                        <div class="form-group row">
                            <label for="member_dob" class="col-sm-2 col-form-label">Date of Birth</label>
                            <div class="col-sm-10">
                                <input type="date" class="form-control" id="member_dob" name="member_dob" value="{{ old('member_dob', $data['member']->member_dob) }}">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="member_date_joined" class="col-sm-2 col-form-label">Date Joined*</label>
                            <div class="col-sm-10">
                                <input type="date" class="form-control" id="member_date_joined" name="member_date_joined" value="{{ old('member_date_joined', \Carbon\Carbon::parse($data['member']->member_date_joined)->format('Y-m-d')) }}" required>
                            </div>
                        </div>

                        <!-- Identification -->
                        <h5 class="mb-4 mt-5">Identification</h5>
                        <div class="form-group row">
                            <label for="member_sacco_id" class="col-sm-2 col-form-label">Sacco No/ID*</label>
                            <div class="col-sm-10">
                                <input type="text" class="form-control" id="member_sacco_id" name="member_sacco_id" value="{{ old('member_sacco_id', $data['member']->member_sacco_id) }}" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="member_national_id" class="col-sm-2 col-form-label">ID No/Passport No*</label>
                            <div class="col-sm-10">
                                <input type="text" class="form-control" id="member_national_id" name="member_national_id" value="{{ old('member_national_id', $data['member']->member_national_id) }}" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="member_kra_pin" class="col-sm-2 col-form-label">KRA PIN</label>
                            <div class="col-sm-10">
                                <input type="text" class="form-control" id="member_kra_pin" name="member_kra_pin" value="{{ old('member_kra_pin', $data['member']->member_kra_pin) }}">
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <h5 class="mb-4 mt-5">Contact Information</h5>
                        <div class="form-group row">
                            <label for="member_phone_no" class="col-sm-2 col-form-label">Phone No</label>
                            <div class="col-sm-10">
                                <input type="text" class="form-control" id="member_phone_no" name="member_phone_no" value="{{ old('member_phone_no', $data['member']->member_phone_no) }}">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="member_email" class="col-sm-2 col-form-label">E-mail (to login)*</label>
                            <div class="col-sm-10">
                                <input type="email" class="form-control" id="member_email" name="member_email" value="{{ old('member_email', $data['member']->member_email) }}" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="member_postal_address" class="col-sm-2 col-form-label">Postal Address</label>
                            <div class="col-sm-10">
                                <textarea class="form-control" id="member_postal_address" name="member_postal_address" rows="3">{{ old('member_postal_address', $data['member']->member_postal_address) }}</textarea>
                            </div>
                        </div>

                        <!-- Organizational Information -->
                        <h5 class="mb-4 mt-5">Organizational Information</h5>
                        <div class="form-group row">
                            <label for="member_dept" class="col-sm-2 col-form-label">Company/Dept*</label>
                            <div class="col-sm-10">
                                <select class="form-control" id="member_dept" name="member_dept">
                                    @foreach ($data['departments'] as $department)
                                        <option value="{{ $department->department_id }}" {{ old('member_dept', $data['member']->member_dept) == $department->department_id ? 'selected' : '' }}>
                                            {{ $department->company_name }}/{{ $department->department_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="member_position" class="col-sm-2 col-form-label">Position/Role*</label>
                            <div class="col-sm-10">
                                <select class="form-control" id="member_position" name="member_position">
                                    @foreach ($data['positions'] as $position)
                                        <option value="{{ $position->position_id }}" {{ old('member_position', $data['member']->member_position) == $position->position_id ? 'selected' : '' }}>
                                            {{ $position->position_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <!-- Bank Information -->
                        <h5 class="mb-4 mt-5">Bank Information</h5>
                        <div class="form-group row">
                            <label for="bank_name" class="col-sm-2 col-form-label">Bank Name</label>
                            <div class="col-sm-10">
                                <input type="text" class="form-control" id="bank_name" name="bank_name" value="{{ old('bank_name', $data['member']->bank_name) }}">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="bank_branch" class="col-sm-2 col-form-label">Bank Branch</label>
                            <div class="col-sm-10">
                                <input type="text" class="form-control" id="bank_branch" name="bank_branch" value="{{ old('bank_branch', $data['member']->bank_branch) }}">
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="bank_account_number" class="col-sm-2 col-form-label">Bank Account Number</label>
                            <div class="col-sm-10">
                                <input type="text" class="form-control" id="bank_account_number" name="bank_account_number" value="{{ old('bank_account_number', $data['member']->bank_account_number) }}">
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-sm-10 offset-sm-2">
                                <button type="submit" class="btn btn-primary">Update</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('styles')
    <style>
       
       .form-group {
            margin-bottom: 1.5rem;
        }
        .col-form-label {
            font-weight: bold;
        }
        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
        }
        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2653d4;
        }
        h5 {
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
            color: #4e73df;
        }
    </style>
@endsection
