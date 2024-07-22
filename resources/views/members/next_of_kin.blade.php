@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Next of Kin for {{ $member->member_name }}</h1>
        <<div class="header-part-right">
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

                    @if (session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    <form action="{{ route('members.updateNextOfKin', $member->member_id) }}" method="POST">
                        @csrf
                        <!-- Next of Kin Information -->
                        <h5 class="mb-4">Add New Next of Kin</h5>
                        <div class="form-group row">
                            <label for="kin_names" class="col-sm-2 col-form-label">Kin Names*</label>
                            <div class="col-sm-10">
                                <input type="text" class="form-control" id="kin_names" name="kin_names" value="{{ old('kin_names') }}" required>
                            </div>
                        </div>
                        <div class="form-group row">
    <label for="kin_address" class="col-sm-2 col-form-label">Address/Contacts</label>
    <div class="col-sm-10">
        <textarea class="form-control" id="kin_address" name="kin_address" rows="2">{{ old('kin_address') }}</textarea>
    </div>
</div>

                        <div class="form-group row">
                            <label for="kin_national_id" class="col-sm-2 col-form-label">National ID/Passport No*</label>
                            <div class="col-sm-10">
                                <input type="text" class="form-control" id="kin_national_id" name="kin_national_id" value="{{ old('kin_national_id') }}" required>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="kin_relationship" class="col-sm-2 col-form-label">Relation Type*</label>
                            <div class="col-sm-10">
                                <select class="form-control" id="kin_relationship" name="kin_relationship" required>
                                    @foreach ($relationTypes as $type)
                                        <option value="{{ $type->kin_type_id }}" {{ old('kin_relationship') == $type->kin_type_id ? 'selected' : '' }}>
                                            {{ $type->kin_type_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        <div class="form-group row">
                            <label for="kin_percent" class="col-sm-2 col-form-label">Percent Allocated*</label>
                            <div class="col-sm-10">
                                <select class="form-control" id="kin_percent" name="kin_percent" required>
                                    @for ($i = 1; $i <= 100; $i++)
                                        <option value="{{ $i }}" {{ old('kin_percent') == $i ? 'selected' : '' }}>
                                            {{ $i }}
                                        </option>
                                    @endfor
                                </select>
                            </div>
                        </div>

                        <div class="form-group row">
                            <div class="col-sm-10 offset-sm-2">
                                <button type="submit" class="btn btn-primary">Save</button>
                            </div>
                        </div>
                    </form>

                    <h5 class="mt-5">Existing Next of Kin</h5>
                    <div class="table-responsive">
                        <table class="display table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Names</th>
                                    <th>Address/Contacts</th>
                                    <th>National ID / Passport No</th>
                                    <th>Relation Type</th>
                                    <th>Percent Allocated</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($nextOfKins as $kin)
                                    <tr>
                                        <td>{{ $kin->kin_names }}</td>
                                        <td>{{ $kin->kin_address }}</td>
                                        <td>{{ $kin->kin_national_id }}</td>
                                        <td>{{ $relationTypes->firstWhere('kin_type_id', $kin->kin_relationship)->kin_type_name }}</td>
                                        <td>{{ $kin->kin_percent }}</td>
                                        <td>
                                            <a href="{{ route('members.deleteNextOfKin', [$member->member_id, $kin->kin_id]) }}" class="btn btn-sm btn-danger">Delete</a>
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
