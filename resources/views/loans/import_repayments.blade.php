@extends('layouts.app') 
@section('content')

<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Loan Repayment Import</h1>
    <div class="header-part-right">
        <ul>
            <li><i class="i-Home1 text-muted header-icon"></i></li>
        </ul>
    </div>
</div>

<div class="separator-breadcrumb border-top mb-4"></div>

{{-- SUCCESS MESSAGE --}}
@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
@endif

{{-- WARNING MESSAGE --}}
@if(session('warning'))
    <div class="alert alert-warning alert-dismissible fade show" role="alert">
        {{ session('warning') }}
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>

    {{-- ROW-LEVEL ERRORS --}}
    @if(session('row_errors'))
        <div class="alert alert-secondary mt-2">
            <strong>Rows with issues:</strong>
            <ul class="mt-2 mb-0">
                @foreach(session('row_errors') as $err)
                    <li>{{ $err }}</li>
                @endforeach
            </ul>
        </div>
    @endif
@endif

{{-- ERROR MESSAGE --}}
@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
    </div>
@endif

<div class="row">
    <!-- LEFT: CSV UPLOAD -->
    <div class="col-md-6 mb-4">
        <div class="card">
            <div class="card-header">
                <h4 class="card-title m-0">Upload Loan Repayments (CSV)</h4>
            </div>
            <div class="card-body">
                <!-- DROPZONE -->
                <form action="{{ route('loans.repayments.csv.upload') }}" 
                      class="dropzone dz-clickable" 
                      id="repayments-upload">
                    <div class="dz-default dz-message">
                        <span>Drop CSV here or click to upload</span>
                    </div>
                </form>

                <small class="text-muted d-block mt-2">
                    Allowed file: <strong>.csv</strong> only
                </small>

                <!-- PROCESS CSV BUTTON -->
                <form method="POST" 
                      action="{{ route('loans.repayments.csv.process') }}" 
                      class="mt-3">
                    @csrf
                    <input type="hidden" name="path" id="csv-path">

                    <button type="submit" class="btn btn-success">
                        <i class="i-Yes"></i> Process CSV File
                    </button>
                </form>

            </div>
        </div>
    </div>

    <!-- RIGHT: SAMPLE CSV + INFO -->
    <div class="col-md-6">
        <div class="card o-hidden mb-4">
            <div class="card-header">
                <h4 class="card-title m-0">CSV Format Instructions</h4>
            </div>
            <div class="card-body">

                <p>Ensure your CSV contains the following columns:</p>

                <table class="table table-bordered table-sm">
                    <thead>
                        <tr>
                            <th style="width: 30%">Column</th>
                            <th>Description</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>loan_id</td><td>Loan ID (from sacco_loans)</td></tr>
                        <tr><td>amount</td><td>Principal amount paid</td></tr>
                        <tr><td>interest</td><td>Interest portion paid</td></tr>
                        <tr><td>description</td><td>Payment narration (optional)</td></tr>
                        <tr><td>docno</td><td>MPESA/ref number</td></tr>
                        <tr><td>paid_on</td><td>Date of payment (YYYY-MM-DD)</td></tr>
                    </tbody>
                </table>

                <a href="{{ route('loans.repayments.csv.sample') }}" 
                   class="btn btn-primary btn-sm">
                    <i class="i-Download"></i> Download Sample CSV
                </a>

            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<!-- DROPZONE JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/dropzone/5.9.3/min/dropzone.min.js"></script>

<script>
    Dropzone.options.repaymentsUpload = {
        paramName: "file",
        maxFilesize: 5,
        acceptedFiles: ".csv",
        timeout: 10000,

        success: function (file, response) {
            // Save file path for processing
            document.getElementById("csv-path").value = response.path;

            toastr.success("CSV uploaded successfully. Now click 'Process CSV File'");
        },

        error: function (file, message) {
            toastr.error("Upload failed: " + message);
        }
    };
</script>
@endsection
