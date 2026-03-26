@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Loan Batches</h1>
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

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <div class="row">
        <div class="col-md-12">

            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center flex-wrap mb-3">
                        <div>
                            <h4 class="card-title mb-1">Loan Batch Listing</h4>
                            <p class="text-muted mb-0">
                                View, filter, and manage loan batches by period.
                            </p>
                        </div>

                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('files.list') }}" class="btn btn-outline-primary btn-sm">
                                List Files
                            </a>
                            <a href="{{ route('loans.batch') }}" class="btn btn-primary btn-sm">
                                Add New Loan Batch
                            </a>
                        </div>
                    </div>

                    <form action="{{ route('loans.batches') }}" method="GET" class="mb-4">
                        <div class="row align-items-end">
                            <div class="col-md-4 form-group mb-3">
                                <label for="period">Period</label>
                                <input
                                    type="text"
                                    id="period"
                                    name="period"
                                    value="{{ $period }}"
                                    class="form-control"
                                    placeholder="YYYYMM"
                                >
                                <small class="text-muted">Filter loan batches by accounting period.</small>
                            </div>

                            <div class="col-md-8 form-group mb-3">
                                <button type="submit" class="btn btn-primary btn-sm">
                                    Filter
                                </button>
                                <a href="{{ route('loans.batches') }}" class="btn btn-outline-secondary btn-sm">
                                    Reset
                                </a>
                            </div>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle">
                            <thead class="thead-light">
                                <tr>
                                    <th style="width: 60px;">#</th>
                                    <th style="min-width: 180px;">Doc. No / Ref No</th>
                                    <th style="width: 110px;">Period</th>
                                    <th style="width: 130px;" class="text-right">Amount</th>
                                    <th style="width: 120px;" class="text-center">Items/Loans</th>
                                    <th style="min-width: 240px;">Credit Account</th>
                                    <th style="width: 110px;" class="text-center">Approved</th>
                                    <th style="width: 110px;" class="text-center">Updated</th>
                                    <th style="min-width: 260px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($batches as $index => $batch)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            <div class="font-weight-bold">{{ $batch->batch_reference }}</div>
                                        </td>
                                        <td>{{ $batch->batch_period }}</td>
                                        <td class="text-right">{{ number_format($batch->batch_amount, 2) }}</td>
                                        <td class="text-center">{{ $batch->batch_total_trans }}</td>
                                        <td>
                                            <div class="font-weight-bold">
                                                {{ $batch->main_account_code }}/{{ $batch->sub_account_code }}
                                            </div>
                                            <div class="text-muted">
                                                {{ $batch->sub_account_name }}
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            @if($batch->batch_approved == 'Y')
                                                <span class="badge badge-success">Yes</span>
                                            @else
                                                <span class="badge badge-secondary">No</span>
                                            @endif
                                        </td>
                                        <td class="text-center">
                                            @if($batch->batch_updated == 'Y')
                                                <span class="badge badge-success">Yes</span>
                                            @else
                                                <span class="badge badge-secondary">No</span>
                                            @endif
                                        </td>
                                        <td>
                                            <div class="d-flex flex-wrap gap-2">
                                                <a href="{{ route('loans.batch.transactions', $batch->batch_id) }}" class="btn btn-info btn-sm">
                                                    View Transactions
                                                </a>

                                                @if($batch->batch_approved === 'N' || $batch->batch_updated === 'N')
                                                    <a href="{{ route('loans.batch.transactions.add_view', $batch->batch_id) }}" class="btn btn-success btn-sm">
                                                        Add Transaction
                                                    </a>

                                                    <a href="{{ route('loans.batch.edit', $batch->batch_id) }}" class="btn btn-warning btn-sm">
                                                        Edit
                                                    </a>

                                                    <a href="{{ route('loans.batch.delete', $batch->batch_id) }}"
                                                       class="btn btn-danger btn-sm"
                                                       onclick="return confirm('Are you sure you want to delete this loan batch?')">
                                                        Delete
                                                    </a>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">
                                            No loan batches found for the selected period.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>

                            @if($batches->count() > 0)
                                <tfoot>
                                    <tr>
                                        <th colspan="3" class="text-right">TOTAL</th>
                                        <th class="text-right">{{ number_format($batches->sum('batch_amount'), 2) }}</th>
                                        <th colspan="5"></th>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </div>
@endsection