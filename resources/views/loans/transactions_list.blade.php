@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Transactions for Batch: {{ $batch->batch_reference }}</h1>
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

    <div class="col-md-12 mb-3">
        <div class="card text-start">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <a href="{{ route('loans.batches') }}" class="btn btn-secondary">Back to Batches</a>
                    <a href="{{ route('loans.batch.transactions.add_view', $batch->batch_id) }}" class="btn btn-primary">Add New Transaction</a>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Loan Type</th>
                                <th scope="col">Category</th>
                                <th scope="col">Member</th>
                                <th scope="col">Amount</th>
                                <th scope="col">Duration</th>
                                <th scope="col">Document No</th>
                                <th scope="col">Description</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($transactions as $index => $transaction)
                                <tr>
                                    <th scope="row">{{ $index + 1 }}</th>
                                    <td>{{ $transaction->loan_type_name }}</td>
                                    <td>{{ $transaction->loan_category_name }}</td>
                                    <td>{{ $transaction->member_name }}</td>
                                    <td>{{ number_format($transaction->batch_trans_loan_amount, 2) }}</td>
                                    <td>{{ $transaction->batch_trans_loan_duration }}</td>
                                    <td>{{ $transaction->batch_trans_doc_no }}</td>
                                    <td>{{ $transaction->batch_trans_description }}</td>
                                    <td>
                                    <a href="{{ route('loans.batch.transactions.edit', [$batch->batch_id, $transaction->batch_trans_id]) }}" class="btn btn-warning">Edit</a>

                                        <a href="{{ route('loans.batch.transactions.delete', $transaction->batch_trans_id) }}" class="btn btn-danger" onclick="return confirm('Are you sure?')">Delete</a>
                                    </td>
                                </tr>
                            @endforeach
                            <tr>
                                <td colspan="4"></td>
                                <td colspan="5" class="text-right">TOTAL: {{ number_format($transactions->sum('batch_trans_loan_amount'), 2) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
