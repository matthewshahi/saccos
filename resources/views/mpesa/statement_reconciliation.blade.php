@extends('layouts.app')

@section('content')
<div class="breadcrumb">
    <h1>M-Pesa Statement Reconciliation</h1>
</div>

<div class="separator-breadcrumb border-top"></div>

<div class="row">
    <div class="col-md-12">

        <div class="card mb-4">
            <div class="card-body">
                <div class="card-title mb-3">Upload M-Pesa Business Statement</div>

                <p class="text-muted mb-4">
                    Upload the M-Pesa Business statement Excel or CSV file. The system will read PayBill transactions,
                    skip receipts already existing in <strong>c2b_payments</strong> or <strong>stk_push_responses</strong>,
                    and insert only missing valid records into <strong>stk_push_responses</strong> with
                    <strong>processed = N</strong>.
                </p>

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <strong>Upload failed.</strong>
                        <ul class="mb-0 mt-2">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST"
                      action="{{ route('mpesa.statement.reconciliation.store') }}"
                      enctype="multipart/form-data">
                    @csrf

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label for="statement_file">Statement file <span class="text-danger">*</span></label>
                            <input class="form-control"
                                   id="statement_file"
                                   name="statement_file"
                                   type="file"
                                   accept=".xlsx,.xls,.csv,.txt"
                                   required>
                            <small class="form-text text-muted">
                                Maximum file size: 300KB. Accepted formats: Excel or CSV.
                            </small>
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label for="sheet_name">Excel sheet name</label>
                            <input class="form-control"
                                   id="sheet_name"
                                   name="sheet_name"
                                   type="text"
                                   value="{{ old('sheet_name', 'M-PESA FULL STATEMENT - Utility') }}"
                                   placeholder="M-PESA FULL STATEMENT - Utility">
                            <small class="form-text text-muted">
                                Use the Utility sheet for PayBill customer transactions.
                            </small>
                        </div>

                        <div class="col-md-12 form-group mb-3">
                            <div class="alert alert-info mb-0">
                                <strong>Important:</strong>
                                This upload does not post directly to member accounts. It only imports missing successful
                                payments into <strong>stk_push_responses</strong> as <strong>processed = N</strong>.
                                Your normal transaction processor will later pick and post them.
                            </div>
                        </div>

                        <div class="col-md-12">
                            <button class="btn btn-primary" type="submit">
                                Upload & Reconcile
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

@if(session('mpesa_statement_recon_summary'))
    @php $summary = session('mpesa_statement_recon_summary'); @endphp

    <div class="row">
        <div class="col-md-12">

            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">Import Summary</div>

                    <div class="row">
                        <div class="col-md-2 form-group mb-3">
                            <div class="border rounded p-3">
                                <h3 class="mb-1">{{ $summary['total_rows_seen'] ?? 0 }}</h3>
                                <small class="text-muted">Total Rows</small>
                            </div>
                        </div>

                        <div class="col-md-2 form-group mb-3">
                            <div class="border rounded p-3">
                                <h3 class="mb-1">{{ $summary['valid_paybill_rows'] ?? 0 }}</h3>
                                <small class="text-muted">Valid PayBill Rows</small>
                            </div>
                        </div>

                        <div class="col-md-2 form-group mb-3">
                            <div class="border rounded p-3">
                                <h3 class="mb-1">{{ $summary['inserted'] ?? 0 }}</h3>
                                <small class="text-muted">Inserted as N</small>
                            </div>
                        </div>

                        <div class="col-md-2 form-group mb-3">
                            <div class="border rounded p-3">
                                <h3 class="mb-1">{{ $summary['skipped_existing_c2b'] ?? 0 }}</h3>
                                <small class="text-muted">Already in C2B</small>
                            </div>
                        </div>

                        <div class="col-md-2 form-group mb-3">
                            <div class="border rounded p-3">
                                <h3 class="mb-1">{{ $summary['skipped_existing_stk_response'] ?? 0 }}</h3>
                                <small class="text-muted">Already in STK</small>
                            </div>
                        </div>

                        <div class="col-md-2 form-group mb-3">
                            <div class="border rounded p-3">
                                <h3 class="mb-1">{{ $summary['skipped_no_matching_stk'] ?? 0 }}</h3>
                                <small class="text-muted">No STK Match</small>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 form-group mb-3">
                            <div class="border rounded p-3">
                                <h3 class="mb-1">{{ $summary['skipped_missing_data'] ?? 0 }}</h3>
                                <small class="text-muted">Missing Data</small>
                            </div>
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <div class="border rounded p-3">
                                <h3 class="mb-1">{{ $summary['skipped_not_completed'] ?? 0 }}</h3>
                                <small class="text-muted">Not Completed</small>
                            </div>
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <div class="border rounded p-3">
                                <h3 class="mb-1">{{ $summary['skipped_not_paybill'] ?? 0 }}</h3>
                                <small class="text-muted">Not PayBill</small>
                            </div>
                        </div>

                        <div class="col-md-3 form-group mb-3">
                            <div class="border rounded p-3">
                                <h3 class="mb-1">{{ $summary['errors'] ?? 0 }}</h3>
                                <small class="text-muted">Errors</small>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive mt-3">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Row</th>
                                    <th>Receipt</th>
                                    <th>Account</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Message</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(($summary['rows'] ?? []) as $row)
                                    <tr>
                                        <td>{{ $row['row'] }}</td>
                                        <td>{{ $row['receipt'] }}</td>
                                        <td>{{ $row['account'] }}</td>
                                        <td>{{ $row['amount'] }}</td>
                                        <td>{{ $row['status'] }}</td>
                                        <td>{{ $row['message'] }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">
                                            No row details available.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <small class="text-muted">
                        Only the first 100 row results are shown.
                    </small>
                </div>
            </div>

        </div>
    </div>
@endif
@endsection