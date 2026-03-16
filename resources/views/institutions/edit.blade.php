@extends('layouts.app')

@section('content')
    <div class="breadcrumb add-institution-breadcrumb d-flex justify-content-between align-items-center">
        <h1>Edit Institution</h1>
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
    <div class="separator-breadcrumb add-institution-separator-breadcrumb border-top"></div>

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

                    <form action="{{ route('institutions.update', $data['institution']->company_id) }}" method="POST">
                        @csrf

                        <h5 class="add-institution-title">Edit Company</h5>

                        <div class="form-group row add-institution-form-group">
                            <label for="company_name" class="col-sm-2 col-form-label add-institution-col-form-label">Company Name</label>
                            <div class="col-sm-10">
                                <input type="text" class="form-control" id="company_name" name="company_name" value="{{ old('company_name', $data['institution']->company_name) }}" required>
                            </div>
                        </div>

                        <div class="form-group row add-institution-form-group">
                            <label for="company_details" class="col-sm-2 col-form-label add-institution-col-form-label">Company Details</label>
                            <div class="col-sm-10">
                                <textarea class="form-control" id="company_details" name="company_details">{{ old('company_details', $data['institution']->company_details) }}</textarea>
                            </div>
                        </div>

                        <div class="form-group row add-institution-form-group">
                            <label for="sub_account_id" class="col-sm-2 col-form-label add-institution-col-form-label">Sub Account*</label>
                            <div class="col-sm-10">
                                <select class="form-control" id="sub_account_id" name="sub_account_id" required>
                                    <option value="">Select sub account</option>
                                    @foreach ($data['accounts'] as $account)
                                        <option value="{{ $account->sub_account_id }}"
                                            {{ old('sub_account_id', $data['institution']->company_account) == $account->sub_account_id ? 'selected' : '' }}>
                                            {{ $account->main_account_code }}/{{ $account->sub_account_code }} - {{ $account->sub_account_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <h5 class="add-institution-title">Existing Departments</h5>

                        @if(count($data['departments']) > 0)
                            @foreach($data['departments'] as $department)
                                <div class="form-group row add-institution-form-group">
                                    <label class="col-sm-2 col-form-label add-institution-col-form-label">Department</label>
                                    <div class="col-sm-10">
                                        <input type="text"
                                               class="form-control"
                                               name="existing_departments[{{ $department->department_id }}]"
                                               value="{{ old('existing_departments.' . $department->department_id, $department->department_name) }}">
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="alert alert-info">
                                No departments found for this institution.
                            </div>
                        @endif

                        <h5 class="add-institution-title">Add New Department</h5>
                        <div class="form-group row add-institution-form-group">
                            <label for="new_department_name" class="col-sm-2 col-form-label add-institution-col-form-label">New Department</label>
                            <div class="col-sm-10">
                                <input type="text" class="form-control" id="new_department_name" name="new_department_name" value="{{ old('new_department_name') }}">
                            </div>
                        </div>

                        <div class="form-group row add-institution-form-group">
                            <div class="col-sm-10 offset-sm-2">
                                <button type="submit" class="btn btn-primary add-institution-btn-primary">Update</button>
                                <a href="{{ route('institutions.list') }}" class="btn btn-secondary">Back</a>
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
        .add-institution-form-group {
            margin-bottom: 1.5rem;
        }
        .add-institution-col-form-label {
            font-weight: bold;
        }
        .add-institution-btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
        }
        .add-institution-btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2653d4;
        }
        .add-institution-title {
            border-bottom: 1px solid #e0e0e0;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
            color: #4e73df;
        }
    </style>
@endsection