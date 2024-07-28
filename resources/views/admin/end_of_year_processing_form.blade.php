@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>End of Year Processing</h1>
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

                    <form action="{{ route('admin.end-of-year-processing') }}" method="POST">
                        @csrf
                        <div class="row row-xs">
                            <div class="col-md-5">
                                <input type="text" id="start_period" name="start_period" class="form-control" placeholder="Start Period (YYYYmm)" value="{{ old('start_period', date('Ym', strtotime('-1 year'))) }}">
                            </div>
                            <div class="col-md-5 mt-3 mt-md-0">
                                <input type="text" id="end_period" name="end_period" class="form-control" placeholder="End Period (YYYYmm)" value="{{ old('end_period', date('Ym', strtotime('-1 month'))) }}">
                            </div>
                            <div class="col-md-2 mt-3 mt-md-0">
                                <button type="submit" class="btn btn-primary w-100">Process</button>
                            </div>
                        </div>
                    </form>

                    <h4 class="mt-4">Previous Processing Records</h4>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered">
                            <thead>
                                <tr>
                                    <th>Period</th>
                                    <th>Processed By</th>
                                    <th>Processed On</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($previousProcesses as $process)
                                    <tr>
                                        <td>{{ $process->end_year_proc_period }}</td>
                                        <td>{{ $process->member_name }}</td>
                                        <td>{{ $process->end_year_proc_on }}</td>
                                        <td>{{ $process->end_year_proc_ip }}</td>
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
