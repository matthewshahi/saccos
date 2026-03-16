@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Institutions Listing</h1>
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
        <div class="col-md-12 mb-4">
            <div class="card text-start">
                <div class="card-body">

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

                    <div class="table-responsive">
                        <table class="display table table-striped table-bordered institutions-list-table" id="multicolumn_ordering_table" style="width: 100%">
                            <thead>
                                <tr>
                                    <th>Company Name</th>
                                    <th>Account Number</th>
                                    <th>Company Details</th>
                                    <th>Department Name</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($data['institutions'] as $institution)
                                    <tr>
                                        <td>{{ $institution->company_name }}</td>
                                        <td>
                                            @if($institution->main_account_code && $institution->sub_account_code)
                                                {{ $institution->main_account_code }}/{{ $institution->sub_account_code }} - {{ $institution->sub_account_name }}
                                            @else
                                                -
                                            @endif
                                        </td>
                                        <td>{{ $institution->company_details }}</td>
                                        <td>{{ $institution->department_name ?: '-' }}</td>
                                        <td>
                                            <a href="{{ route('institutions.edit', $institution->company_id) }}" class="btn btn-sm btn-primary">
                                                Edit
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>Company Name</th>
                                    <th>Account Number</th>
                                    <th>Company Details</th>
                                    <th>Department Name</th>
                                    <th>Action</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection