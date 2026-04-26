@extends('layouts.app')

@section('content')
@php
    $summary = $summary ?? [];
    $preview = $preview ?? [];
    $columns = $columns ?? [];
    $sampleRows = $sampleRows ?? [];
    $warnings = $warnings ?? [];
@endphp

<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Payroll Import Preview</h1>

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

@if(!empty($warnings))
    <div class="alert alert-warning">
        <strong>Please note:</strong>
        <ul class="mb-0 mt-2">
            @foreach($warnings as $warning)
                <li>{{ $warning }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        <strong>Import cannot continue:</strong>
        <ul class="mb-0 mt-2">
            @foreach($errors->all() as $error)
                <li>{!! $error !!}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row">
    <div class="col-md-12">
        <div class="card o-hidden mb-4">
            <div class="card-header d-flex align-items-center">
                <h3 class="w-50 float-start card-title m-0">Validation Summary</h3>

                <div class="dropdown dropleft text-end w-50 float-end">
                    <button class="btn bg-gray-100"
                            id="dropdownMenuButton_import_summary"
                            type="button"
                            data-bs-toggle="dropdown"
                            aria-haspopup="true"
                            aria-expanded="false">
                        <i class="nav-icon i-Gear-2"></i>
                    </button>

                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_import_summary">
                        <a class="dropdown-item" href="{{ route('payroll.deductions.import.index') }}">
                            Upload another file
                        </a>
                        <a class="dropdown-item" href="{{ route('payroll.deductions.import.sample') }}">
                            Download sample
                        </a>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <div class="row">
                    <div class="col-md-2 mb-3">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted d-block">Matched Rows</small>
                            <h4 class="mb-0">{{ number_format($summary['valid_rows'] ?? 0) }}</h4>
                        </div>
                    </div>

                    <div class="col-md-2 mb-3">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted d-block">Deposits</small>
                            <h4 class="mb-0">{{ number_format($summary['total_deposits'] ?? 0, 2) }}</h4>
                        </div>
                    </div>

                    <div class="col-md-2 mb-3">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted d-block">Capital</small>
                            <h4 class="mb-0">{{ number_format($summary['total_capital'] ?? 0, 2) }}</h4>
                        </div>
                    </div>

                    <div class="col-md-2 mb-3">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted d-block">Others / FOSA</small>
                            <h4 class="mb-0">{{ number_format($summary['total_others'] ?? 0, 2) }}</h4>
                        </div>
                    </div>

                    <div class="col-md-2 mb-3">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted d-block">Loans</small>
                            <h4 class="mb-0">{{ number_format($summary['total_loans'] ?? 0, 2) }}</h4>
                        </div>
                    </div>

                    <div class="col-md-2 mb-3">
                        <div class="border rounded p-3 h-100">
                            <small class="text-muted d-block">Grand Total</small>
                            <h4 class="mb-0">{{ number_format($summary['grand_total'] ?? 0, 2) }}</h4>
                        </div>
                    </div>

                    <div class="col-md-12 mt-2">
                        <div class="alert alert-success mb-0">
                            <strong>File validation passed.</strong>
                            No data has been imported yet. Click <strong>Confirm Import</strong> to send this file for background processing.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- File Details --}}
    <div class="col-md-5">
        <div class="card o-hidden mb-4">
            <div class="card-header d-flex align-items-center">
                <h3 class="w-50 float-start card-title m-0">File Details</h3>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <tbody>
                            <tr>
                                <th>File</th>
                                <td>{{ $preview['original_name'] ?? '-' }}</td>
                            </tr>
                            <tr>
                                <th>Institution</th>
                                <td>{{ $summary['company_name'] ?? ($preview['company_name'] ?? '-') }}</td>
                            </tr>
                            <tr>
                                <th>Period</th>
                                <td>{{ $summary['period'] ?? ($preview['period'] ?? '-') }}</td>
                            </tr>
                            <tr>
                                <th>Document No</th>
                                <td>{{ $summary['doc_no'] ?? ($preview['doc_no'] ?? '-') }}</td>
                            </tr>
                            <tr>
                                <th>Payment Date</th>
                                <td>{{ $summary['payment_date'] ?? ($preview['payment_date'] ?? '-') }}</td>
                            </tr>
                            <tr>
                                <th>Grand Total</th>
                                <td>
                                    <strong>{{ number_format($summary['grand_total'] ?? 0, 2) }}</strong>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="alert alert-light border">
                    Once confirmed, the import will run in the background. Do not upload the same document number again.
                </div>

                <form method="POST"
                      action="{{ route('payroll.deductions.import.process') }}"
                      class="d-inline">
                    @csrf

                    <button class="btn btn-primary" type="submit"
                            onclick="return confirm('Confirm import? This will send the validated file to background processing.')">
                        Confirm Import
                    </button>
                </form>

                <form method="POST"
                      action="{{ route('payroll.deductions.import.cancel') }}"
                      class="d-inline">
                    @csrf

                    <button class="btn btn-danger" type="submit">
                        Cancel
                    </button>
                </form>
            </div>
        </div>

        <div class="card o-hidden mb-4">
            <div class="card-header d-flex align-items-center">
                <h3 class="w-50 float-start card-title m-0">Loan Payment Rule</h3>
            </div>

            <div class="card-body">
                <p class="mb-2">
                    For each loan column, the system will first pay the latest outstanding loan of the same loan type.
                </p>

                <p class="mb-2">
                    If there is no outstanding loan, it will pay the latest loan of that same type.
                </p>

                <p class="mb-0">
                    If the member has no loan record for that loan type, the import will stop.
                </p>
            </div>
        </div>
    </div>

    {{-- Detected Columns --}}
    <div class="col-md-7">
        <div class="card o-hidden mb-4">
            <div class="card-header d-flex align-items-center">
                <h3 class="w-50 float-start card-title m-0">Detected Posting Columns</h3>

                <div class="text-end w-50 float-end">
                    <span class="badge bg-info">
                        Prefix-based matching
                    </span>
                </div>
            </div>

            <div class="card-body">
                @php
                    $detectedColumns = [];

                    foreach(($columns['deposits'] ?? []) as $column) {
                        $detectedColumns[] = [
                            'type' => 'Deposits',
                            'badge' => 'bg-primary',
                            'excel_column' => $column['excel_column'] ?? '-',
                            'matched_to' => $column['item_name'] ?? '-',
                        ];
                    }

                    foreach(($columns['capital'] ?? []) as $column) {
                        $detectedColumns[] = [
                            'type' => 'Capital',
                            'badge' => 'bg-secondary',
                            'excel_column' => $column['excel_column'] ?? '-',
                            'matched_to' => $column['item_name'] ?? '-',
                        ];
                    }

                    foreach(($columns['others'] ?? []) as $column) {
                        $detectedColumns[] = [
                            'type' => 'Others / FOSA',
                            'badge' => 'bg-info',
                            'excel_column' => $column['excel_column'] ?? '-',
                            'matched_to' => $column['fosa_type_name'] ?? ($column['item_name'] ?? '-'),
                        ];
                    }

                    foreach(($columns['loans'] ?? []) as $column) {
                        $detectedColumns[] = [
                            'type' => 'Loan',
                            'badge' => 'bg-warning text-dark',
                            'excel_column' => $column['excel_column'] ?? '-',
                            'matched_to' => $column['loan_type_name'] ?? '-',
                        ];
                    }
                @endphp

                @if(count($detectedColumns) > 0)
                    <div class="table-responsive">
                        <table class="table text-center">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Type</th>
                                    <th scope="col">Excel Column</th>
                                    <th scope="col">Matched To</th>
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($detectedColumns as $column)
                                    <tr>
                                        <th scope="row">{{ $loop->iteration }}</th>
                                        <td>
                                            <span class="badge {{ $column['badge'] }}">
                                                {{ $column['type'] }}
                                            </span>
                                        </td>
                                        <td>{{ $column['excel_column'] }}</td>
                                        <td>{{ $column['matched_to'] }}</td>
                                        <td>
                                            <span class="badge bg-success">OK</span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-warning mb-0">
                        No posting columns were detected. Check that your money columns start with
                        <strong>DEPOSITS</strong>, <strong>CAPITAL</strong>, <strong>OTHERS</strong>, or <strong>LOAN</strong>.
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Sample Rows --}}
    <div class="col-md-12">
        <div class="card o-hidden mb-4">
            <div class="card-header d-flex align-items-center">
                <h3 class="w-50 float-start card-title m-0">Sample Validated Rows</h3>

                <div class="text-end w-50 float-end">
                    <span class="badge bg-info">
                        Showing first {{ count($sampleRows ?? []) }} rows
                    </span>
                </div>
            </div>

            <div class="card-body">
                @if(!empty($sampleRows) && count($sampleRows) > 0)
                    <div class="table-responsive">
                        <table class="table text-center">
                            <thead>
                                <tr>
                                    <th scope="col">Excel Row</th>
                                    <th scope="col">Member</th>
                                    <th scope="col">National ID</th>
                                    <th scope="col">Deposits</th>
                                    <th scope="col">Capital</th>
                                    <th scope="col">Others/FOSA</th>
                                    <th scope="col">Loans</th>
                                    <th scope="col">Row Total</th>
                                </tr>
                            </thead>

                            <tbody>
                                @foreach($sampleRows as $row)
                                    @php
                                        $rowDeposits = collect($row['deposits'] ?? [])->filter(fn ($item) => isset($item['amount']) && (float) $item['amount'] > 0);
                                        $rowCapital  = collect($row['capital'] ?? [])->filter(fn ($item) => isset($item['amount']) && (float) $item['amount'] > 0);
                                        $rowOthers   = collect($row['others'] ?? [])->filter(fn ($item) => isset($item['amount']) && (float) $item['amount'] > 0);
                                        $rowLoans    = collect($row['loans'] ?? [])->filter(fn ($item) => isset($item['amount']) && (float) $item['amount'] > 0);
                                    @endphp

                                    <tr>
                                        <th scope="row">{{ $row['excel_row'] ?? '-' }}</th>

                                        <td class="text-start">
                                            {{ $row['member_name'] ?? '-' }}
                                        </td>

                                        <td>{{ $row['member_national_id'] ?? '-' }}</td>

                                        <td class="text-start">
                                            @if($rowDeposits->count() > 0)
                                                @foreach($rowDeposits as $item)
                                                    <div>
                                                        <span class="badge bg-light text-dark">
                                                            {{ $item['item_name'] ?? 'Deposit' }}
                                                        </span>
                                                        {{ number_format($item['amount'] ?? 0, 2) }}
                                                    </div>
                                                @endforeach
                                            @else
                                                <span class="text-muted">None</span>
                                            @endif
                                        </td>

                                        <td class="text-start">
                                            @if($rowCapital->count() > 0)
                                                @foreach($rowCapital as $item)
                                                    <div>
                                                        <span class="badge bg-light text-dark">
                                                            {{ $item['item_name'] ?? 'Capital' }}
                                                        </span>
                                                        {{ number_format($item['amount'] ?? 0, 2) }}
                                                    </div>
                                                @endforeach
                                            @else
                                                <span class="text-muted">None</span>
                                            @endif
                                        </td>

                                        <td class="text-start">
                                            @if($rowOthers->count() > 0)
                                                @foreach($rowOthers as $item)
                                                    <div>
                                                        <span class="badge bg-light text-dark">
                                                            {{ $item['fosa_type_name'] ?? ($item['item_name'] ?? 'Others') }}
                                                        </span>
                                                        {{ number_format($item['amount'] ?? 0, 2) }}
                                                    </div>
                                                @endforeach
                                            @else
                                                <span class="text-muted">None</span>
                                            @endif
                                        </td>

                                        <td class="text-start">
                                            @if($rowLoans->count() > 0)
                                                @foreach($rowLoans as $item)
                                                    <div>
                                                        <span class="badge bg-light text-dark">
                                                            {{ $item['loan_type_name'] ?? 'Loan' }}
                                                        </span>
                                                        {{ number_format($item['amount'] ?? 0, 2) }}
                                                    </div>
                                                @endforeach
                                            @else
                                                <span class="text-muted">None</span>
                                            @endif
                                        </td>

                                        <td>
                                            <strong>{{ number_format($row['row_total'] ?? 0, 2) }}</strong>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="alert alert-warning mb-0">
                        No sample rows available.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection