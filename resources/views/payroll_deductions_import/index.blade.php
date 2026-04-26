@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Payroll Deductions Import</h1>

    <div class="header-part-right">
        <ul>
            @if(Auth::check())
                <li>{{ Auth::user()->member_name }}</li>
            @endif

            @if(isset($currentPeriod) && $currentPeriod)
                <li>
                    <a href="{{ route('admin.periods') }}">
                        Active Period: {{ $currentPeriod->period_name }}
                    </a>
                </li>
            @endif
        </ul>
    </div>
</div>

<div class="separator-breadcrumb border-top"></div>

@if(session('success'))
    <div class="alert alert-success">
        {{ session('success') }}
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <strong>Import stopped. Please fix the issues below:</strong>
        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)
                <li>{!! $error !!}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row">
    <div class="col-md-8">
        <div class="card mb-4">
            <div class="card-body">
                <div class="card-title mb-3">Upload Payroll Deductions File</div>

                <div class="alert alert-light border mb-4">
                    <strong>Before uploading:</strong>
                    make sure the file has an <strong>ID Number</strong> column and all money columns use the correct prefix.
                </div>

                <form method="POST"
                      action="{{ route('payroll.deductions.import.preview') }}"
                      enctype="multipart/form-data">
                    @csrf

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label for="company_id">Institution / Company</label>
                            <select class="form-control @error('company_id') is-invalid @enderror"
                                    id="company_id"
                                    name="company_id"
                                    required>
                                <option value="">Select institution</option>

                                @foreach($companies as $company)
                                    <option value="{{ $company->company_id }}"
                                        {{ old('company_id') == $company->company_id ? 'selected' : '' }}>
                                        {{ $company->company_name }}
                                    </option>
                                @endforeach
                            </select>

                            @error('company_id')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label for="period">Period</label>
                            <input class="form-control @error('period') is-invalid @enderror"
                                   id="period"
                                   name="period"
                                   type="text"
                                   value="{{ old('period', $currentPeriod->period_name ?? date('Ym')) }}"
                                   placeholder="Example: 202603"
                                   maxlength="6"
                                   required>

                            <small class="form-text text-muted">
                                Use the payroll month in YYYYMM format.
                            </small>

                            @error('period')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label for="doc_no">Document / Payroll Reference</label>
                            <input class="form-control @error('doc_no') is-invalid @enderror"
                                   id="doc_no"
                                   name="doc_no"
                                   type="text"
                                   value="{{ old('doc_no') }}"
                                   placeholder="Example: JAMII-KASS-MAR-2026"
                                   required>

                            <small class="form-text text-muted">
                                Use a unique reference for this payroll file.
                            </small>

                            @error('doc_no')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-md-6 form-group mb-3">
                            <label for="payment_date">Payment Date</label>
                            <input class="form-control @error('payment_date') is-invalid @enderror"
                                   id="payment_date"
                                   name="payment_date"
                                   type="date"
                                   value="{{ old('payment_date', date('Y-m-d')) }}"
                                   required>

                            @error('payment_date')
                                <small class="text-danger">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-md-12 form-group mb-3">
                            <label for="import_file">Excel File</label>
                            <input class="form-control @error('import_file') is-invalid @enderror"
                                   id="import_file"
                                   name="import_file"
                                   type="file"
                                   accept=".xlsx,.xls,.csv"
                                   required>

                            <small class="form-text text-muted">
                                Accepted formats: Excel or CSV. The system will preview and validate before importing.
                            </small>

                            @error('import_file')
                                <small class="text-danger d-block">{{ $message }}</small>
                            @enderror
                        </div>

                        <div class="col-md-12">
                            <button class="btn btn-primary" type="submit">
                                Upload & Preview
                            </button>

                            <a href="{{ route('payroll.deductions.import.sample') }}"
                               class="btn btn-outline-secondary">
                                Download Sample
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-body">
                <div class="card-title mb-3">Column Format</div>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Column Example</th>
                                <th>Where It Goes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><strong>DEPOSITS - Monthly Deposit</strong></td>
                                <td>Member deposits account</td>
                            </tr>
                            <tr>
                                <td><strong>CAPITAL - Share Capital</strong></td>
                                <td>Member capital account</td>
                            </tr>
                            <tr>
                                <td><strong>OTHERS - Welfare</strong></td>
                                <td>FOSA / Others account</td>
                            </tr>
                            <tr>
                                <td><strong>LOAN - Normal Loan</strong></td>
                                <td>Loan repayment</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-info mb-0">
                    The word before the dash tells the system where to post the money.
                    For <strong>LOAN</strong> columns, the name after the dash must match a loan type.
                    For <strong>OTHERS</strong> columns, the name after the dash must match a FOSA/Others type.
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card mb-4">
            <div class="card-body">
                <div class="card-title mb-3">Simple Import Rules</div>

                <div class="mb-3">
                    <span class="badge bg-primary">1</span>
                    <p class="mb-0 mt-2">
                        Every payable row must have an <strong>ID Number</strong> that exists in active members.
                    </p>
                </div>

                <div class="mb-3">
                    <span class="badge bg-primary">2</span>
                    <p class="mb-0 mt-2">
                        Money columns must start with <strong>DEPOSITS</strong>, <strong>CAPITAL</strong>, <strong>OTHERS</strong>, or <strong>LOAN</strong>.
                    </p>
                </div>

                <div class="mb-3">
                    <span class="badge bg-primary">3</span>
                    <p class="mb-0 mt-2">
                        Loan names must match active loan types in the system.
                    </p>
                </div>

                <div class="mb-3">
                    <span class="badge bg-primary">4</span>
                    <p class="mb-0 mt-2">
                        For loan payments, the system first pays the latest outstanding loan of the same type.
                        If none is outstanding, it pays the latest loan of that type.
                    </p>
                </div>

                <div class="mb-3">
                    <span class="badge bg-primary">5</span>
                    <p class="mb-0 mt-2">
                        If one row fails validation, nothing is imported.
                    </p>
                </div>

                <div class="alert alert-light border mb-0">
                    Uploading only previews the file. The import starts only after you confirm on the preview page.
                </div>
            </div>
        </div>

        @if(!empty($preview))
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">Pending Preview</div>

                    <p class="mb-1">
                        <strong>File:</strong> {{ $preview['original_name'] ?? 'Uploaded file' }}
                    </p>

                    <p class="mb-1">
                        <strong>Period:</strong> {{ $preview['period'] ?? '' }}
                    </p>

                    <p class="mb-3">
                        <strong>Document:</strong> {{ $preview['doc_no'] ?? '' }}
                    </p>

                    <form method="POST" action="{{ route('payroll.deductions.import.cancel') }}">
                        @csrf
                        <button class="btn btn-danger btn-sm" type="submit">
                            Clear Pending Preview
                        </button>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>
@endsection