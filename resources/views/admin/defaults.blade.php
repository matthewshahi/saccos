@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>System Defaults</h1>
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
                        @foreach(session('error') as $error)
                            <p>{{ $error }}</p>
                        @endforeach
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

                <form action="{{ route('admin.defaults.update') }}" method="POST">
                    @csrf
                    <div class="table-responsive">
                        <table class="display table table-striped table-bordered" style="width: 100%">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Default Name</th>
                                    <th>Default Value</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($defaults as $default)
                                    <tr>
                                        <td>{{ $loop->iteration }}</td>
                                        <td>{{ $default->default_name }}</td>
                                        <td>
                                            <input type="text" name="defaults[{{ $default->default_id }}]" class="form-control" value="{{ $default->default_value }}">
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>

                <a href="#" class="btn btn-secondary mt-4" data-toggle="modal" data-target="#addDefaultModal">
                    Add New Default
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Add Default Modal -->
<div class="modal fade" id="addDefaultModal" tabindex="-1" role="dialog" aria-labelledby="addDefaultModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="{{ route('admin.defaults.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addDefaultModalLabel">Add New Default</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="default_name" class="form-label">Default Name</label>
                        <input type="text" class="form-control" id="default_name" name="default_name" required>
                    </div>
                    <div class="mb-3">
                        <label for="default_value" class="form-label">Default Value</label>
                        <input type="text" class="form-control" id="default_value" name="default_value" required>
                    </div>
                </div>
                <div class="modal-footer d-flex justify-content-between flex-column flex-sm-row">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save Default</button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection

<script src="https://code.jquery.com/jquery-3.3.1.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.1.3/js/bootstrap.min.js"></script>