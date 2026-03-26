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

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
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

    <div class="row mb-3">
        <div class="col-md-12">
            <div class="card">
                <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2 py-3">
                    <div>
                        <strong>Loan Batches</strong><br>
                        <small class="text-muted">
                            {{ isset($batch) ? 'Update batch details, approval, and finalisation settings.' : 'Create a new batch for loan processing.' }}
                        </small>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <a href="{{ route('loans.batches') }}" class="btn btn-outline-secondary btn-sm">
                            List Loan Batches
                        </a>
                        <a href="{{ route('loans.batch') }}" class="btn btn-primary btn-sm">
                            Add New Loan Batch
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if(isset($batch))
        <div class="row mb-3">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="mb-3">Batch Transaction Summary</h5>
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <strong>Expected Items</strong><br>
                                <span class="text-muted">{{ number_format((int) ($batch->batch_total_trans ?? 0)) }}</span>
                            </div>
                            <div class="col-md-3 mb-2">
                                <strong>Actual Items</strong><br>
                                <span class="text-muted">{{ number_format((int) ($transactionSummary->transaction_count ?? 0)) }}</span>
                            </div>
                            <div class="col-md-3 mb-2">
                                <strong>Expected Amount</strong><br>
                                <span class="text-muted">{{ number_format((float) ($batch->batch_amount ?? 0), 2) }}</span>
                            </div>
                            <div class="col-md-3 mb-2">
                                <strong>Actual Amount</strong><br>
                                <span class="text-muted">{{ number_format((float) ($transactionSummary->total_transaction_amount ?? 0), 2) }}</span>
                            </div>
                        </div>

                        <hr>

                        @if($canApprove)
                            <div class="alert alert-success mb-0">
                                This batch meets the transaction count and amount conditions required for approval.
                            </div>
                        @else
                            <div class="alert alert-warning mb-0">
                                This batch cannot be approved yet because the transaction count or transaction amount does not match the batch totals.
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    @endif

    <div class="row">
        <div class="col-md-12">
            <form action="{{ isset($batch) ? route('loans.batch.update', $batch->batch_id) : route('loans.batch.store') }}" method="POST">
                @csrf

                <div class="card mb-4">
                    <div class="card-header">
                        <h4 class="card-title mb-0">{{ isset($batch) ? 'Edit Loan Batch' : 'Add Loan Batch' }}</h4>
                    </div>

                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label for="batch_reference">Document No./Ref No. <span class="text-danger">*</span></label>
                                <input
                                    type="text"
                                    id="batch_reference"
                                    name="batch_reference"
                                    value="{{ old('batch_reference', $batch->batch_reference ?? '') }}"
                                    class="form-control"
                                    required
                                    {{ isset($batch) && $batch->batch_updated == 'Y' ? 'disabled' : '' }}
                                >
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="batch_amount">Amount <span class="text-danger">*</span></label>
                                <input
                                    type="number"
                                    step="0.01"
                                    id="batch_amount"
                                    name="batch_amount"
                                    value="{{ old('batch_amount', $batch->batch_amount ?? '') }}"
                                    class="form-control"
                                    required
                                    {{ isset($batch) && $batch->batch_updated == 'Y' ? 'disabled' : '' }}
                                >
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="batch_total_trans">No of Loans/Transactions <span class="text-danger">*</span></label>
                                <input
                                    type="number"
                                    id="batch_total_trans"
                                    name="batch_total_trans"
                                    value="{{ old('batch_total_trans', $batch->batch_total_trans ?? 1) }}"
                                    class="form-control"
                                    required
                                    {{ isset($batch) && $batch->batch_updated == 'Y' ? 'disabled' : '' }}
                                >
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="batch_credit_account">Account to Credit <span class="text-danger">*</span></label>
                                <select
                                    id="batch_credit_account"
                                    name="batch_credit_account"
                                    class="form-control"
                                    required
                                    {{ isset($batch) && $batch->batch_updated == 'Y' ? 'disabled' : '' }}
                                >
                                    <option value="">-- Select Account --</option>
                                    @foreach($accounts as $account)
                                        <option value="{{ $account->sub_account_id }}"
                                            {{ (string) old('batch_credit_account', $batch->batch_credit_account ?? '') === (string) $account->sub_account_id ? 'selected' : '' }}>
                                            {{ $account->account_name }}
                                        </option>
                                    @endforeach
                                </select>
                                <small class="text-muted">Select the disbursement source account for this batch.</small>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="batch_period">Period <span class="text-danger">*</span></label>
                                <input
                                    type="text"
                                    id="batch_period"
                                    name="batch_period"
                                    value="{{ old('batch_period', $batch->batch_period ?? ($currentPeriod->period_name ?? '')) }}"
                                    class="form-control"
                                    readonly
                                    required
                                >
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="batch_approved">Approved</label>
                                <div class="mt-2">
                                    <label class="switch pe-5 switch-success me-3">
                                        <span>Success</span>
                                        <input
                                            type="checkbox"
                                            id="batch_approved"
                                            name="batch_approved"
                                            {{ old('batch_approved', isset($batch) && $batch->batch_approved == 'Y' ? '1' : '') ? 'checked' : '' }}
                                            {{ isset($batch) && $batch->batch_updated == 'Y' ? 'disabled' : '' }}
                                            {{ isset($batch) && $batch->batch_approved != 'Y' && !$canApprove ? 'disabled' : '' }}
                                        >
                                        <span class="slider"></span>
                                    </label>
                                </div>

                                @if(isset($batch) && $batch->batch_approved != 'Y' && !$canApprove)
                                    <small class="text-muted">
                                        Approval is only enabled when batch transaction totals and item count match the batch values.
                                    </small>
                                @endif
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="batch_updated">Finalized</label>
                                <div class="mt-2">
                                    <label class="switch pe-5 switch-success me-3">
                                        <span>Success</span>
                                        <input
                                            type="checkbox"
                                            id="batch_updated"
                                            name="batch_updated"
                                            {{ old('batch_updated', isset($batch) && $batch->batch_updated == 'Y' ? '1' : '') ? 'checked' : '' }}
                                            {{ isset($batch) && $batch->batch_approved == 'Y' ? '' : 'disabled' }}
                                        >
                                        <span class="slider"></span>
                                    </label>
                                </div>

                                @if(isset($batch) && $batch->batch_approved != 'Y')
                                    <small class="text-muted">
                                        To enable finalisation, the batch must be approved first.
                                    </small>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="card-footer d-flex justify-content-between flex-wrap gap-2">
                        <a href="{{ route('loans.batches') }}" class="btn btn-secondary">
                            Back to List
                        </a>

                        <button type="submit" class="btn btn-primary">
                            Save Batch
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    @include('loans.partials.manual_batch_notes')

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

@endsection