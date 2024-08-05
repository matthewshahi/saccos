@extends('layouts.app')

@section('content')
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

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    @endif

    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-body">
                <div class="card-title mb-3">{{ isset($batch) ? 'Edit Loan Batch' : 'Add Loan Batch' }}</div>
                @if(isset($batch) && $batch->batch_updated == 'Y')
                    <div class="alert alert-warning">
                        This batch has been finalized and cannot be edited.
                    </div>
                @else
                    <form action="{{ isset($batch) ? route('loans.batch.update', $batch->batch_id) : route('loans.batch.store') }}" method="POST">
                        @csrf
                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label for="batch_reference">Document No./Ref No.*</label>
                                <input type="text" id="batch_reference" name="batch_reference" value="{{ old('batch_reference', $batch->batch_reference ?? '') }}" class="form-control" required>
                            </div>
                            <div class="col-md-6 form-group mb-3">
                                <label for="batch_amount">Amount*</label>
                                <input type="number" step="0.01" id="batch_amount" name="batch_amount" value="{{ old('batch_amount', $batch->batch_amount ?? '') }}" class="form-control" required>
                            </div>
                            <div class="col-md-6 form-group mb-3">
                                <label for="batch_total_trans">No of loans/transactions*</label>
                                <input type="number" id="batch_total_trans" name="batch_total_trans" value="{{ old('batch_total_trans', $batch->batch_total_trans ?? 1) }}" class="form-control" required>
                            </div>
                            <div class="col-md-6 form-group mb-3">
                                <label for="batch_credit_account">Account to credit*</label>
                                <select id="batch_credit_account" name="batch_credit_account" class="form-control" required>
                                    @foreach($accounts as $account)
                                        <option value="{{ $account->sub_account_id }}" {{ isset($batch) && $batch->batch_credit_account == $account->sub_account_id ? 'selected' : '' }}>
                                            {{ $account->account_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-md-6 form-group mb-3">
                                <label for="batch_period">Period*</label>
                                <input type="text" id="batch_period" name="batch_period" value="{{ old('batch_period', $currentPeriod->period_name ?? '') }}" class="form-control" readonly required>
                            </div>
                            <div class="col-md-6 form-group mb-3">
                                <label for="batch_approved">Approved</label>
                                <label class="switch pe-5 switch-success me-3"><span>Success</span>
                                    <input type="checkbox" id="batch_approved" name="batch_approved" disabled>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="col-md-6 form-group mb-3">
                                <label for="batch_updated">Finalized</label>
                                <label class="switch pe-5 switch-success me-3"><span>Success</span>
                                    <input type="checkbox" id="batch_updated" name="batch_updated" disabled>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            <div class="col-md-12">
                                <button type="submit" class="btn btn-primary">Save</button>
                                <a href="{{ route('loans.batches') }}" class="btn btn-secondary">Back to List</a>
                            </div>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
@endsection
