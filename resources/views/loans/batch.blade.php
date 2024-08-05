@extends('layouts.app')

@section('content')
    <div class="col-md-12 mb-3">
        <div class="card text-start">
            <div class="card-body">
            <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>{{ isset($batch) ? 'Edit Loan Batch' : 'Add Loan Batch' }}</h1>
        <div class="header-part-right">
            <ul>
                @if(Auth::check())
                    <li>{{ Auth::user()->member_name }}</li>
                @endif
                @if(isset($currentPeriod))
                    <li><a href="#">{{ $currentPeriod->period_name }}</a></li>
                @endif
                <li><i class="i-Full-Screen header-icon d-none d-sm-inline-block" data-fullscreen=""></i></li>
            </ul>
        </div>
    </div>
    <div class="separator-breadcrumb border-top"></div>
    @if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

                <div class="d-flex justify-content-between mb-3">
                    <a href="{{ route('loans.batch') }}" class="btn btn-primary">Add New Loan Batch</a>
                </div>
                <form action="{{ route('loans.batch') }}" method="GET" class="mb-3">
                    <div class="form-group">
                        <label for="period">Period</label>
                        <input type="text" id="period" name="period" value="{{ $period }}" class="form-control" placeholder="YYYYMM">
                    </div>
                    <button type="submit" class="btn btn-primary">Filter</button>
                </form>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Doc. No / ref No</th>
                                <th scope="col">Period</th>
                                <th scope="col">Amount</th>
                                <th scope="col">Items/loans</th>
                                <th scope="col">Credit account</th>
                                <th scope="col">Updated</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($batches as $index => $batch)
                                <tr>
                                    <th scope="row">{{ $index + 1 }}</th>
                                    <td>{{ $batch->batch_reference }}</td>
                                    <td>{{ $batch->batch_period }}</td>
                                    <td>{{ number_format($batch->batch_amount, 2) }}</td>
                                    <td>{{ $batch->batch_total_trans }}</td>
                                    <td>{{ $batch->main_account_code }}/{{ $batch->sub_account_code }} - {{ $batch->sub_account_name }}</td>
                                    <td>{{ $batch->batch_updated }}</td>
                                    <td>
                                        <a href="{{ route('loans.batch.loans', $batch->batch_id) }}" class="btn btn-success">
                                            <i class="nav-icon i-Pen-2"></i> Add loans
                                        </a>
                                        <a href="{{ route('loans.batch.edit', $batch->batch_id) }}" class="btn btn-warning">
                                            <i class="nav-icon i-Pen-2"></i> Edit
                                        </a>
                                        <a href="{{ route('loans.batch.delete', $batch->batch_id) }}" class="btn btn-danger" onclick="return confirm('Are you sure?')">
                                            <i class="nav-icon i-Close-Window"></i> Delete
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                            <tr>
                                <td colspan="4"></td>
                                <td colspan="4" class="text-right">TOTAL: {{ number_format($batches->sum('batch_amount'), 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
