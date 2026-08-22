@extends('layouts.app')

@section('content')
<div class="breadcrumb">
    <h1>CRB / Credit Information Sharing</h1>
    <ul>
        <li><a href="{{ url('/dashboard') }}">Dashboard</a></li>
        <li>CRB Reports</li>
    </ul>
</div>

<div class="separator-breadcrumb border-top"></div>

@include('crb.partials.nav')

@if (session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger">
        <strong>Please correct the following:</strong>
        <ul class="mb-0 mt-2">
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="row">
    <div class="col-lg-8">
        <div class="card o-hidden mb-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <h3 class="card-title m-0">CRB Readiness</h3>
                @if (!empty($readiness['ready']))
                    <span class="badge bg-success">Configuration Ready</span>
                @else
                    <span class="badge bg-danger">Configuration Incomplete</span>
                @endif
            </div>

            <div class="card-body">
                @if (!empty($readiness['ready']))
                    <div class="alert alert-success mb-3">
                        The required CRB institution settings are configured. Draft reports can be validated and finalised once their record-level errors are cleared.
                    </div>
                @else
                    <div class="alert alert-warning mb-3">
                        <strong>Finalisation is blocked until the required CRB settings are completed.</strong>
                        <ul class="mb-0 mt-2">
                            @foreach ($readiness['issues'] as $issue)
                                <li>{{ $issue['message'] ?? 'Configuration issue' }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <div class="table-responsive">
                    <table class="table table-bordered table-sm mb-0">
                        <tbody>
                            <tr>
                                <th style="width: 260px;">Registered Name</th>
                                <td>{{ $readiness['settings']['registered_name'] ?: 'Not configured' }}</td>
                            </tr>
                            <tr>
                                <th>Trading Name</th>
                                <td>{{ $readiness['settings']['trading_name'] ?: 'Not configured' }}</td>
                            </tr>
                            <tr>
                                <th>Institution Code</th>
                                <td>{{ $readiness['settings']['institution_code'] ?: 'Not configured' }}</td>
                            </tr>
                            <tr>
                                <th>Branch</th>
                                <td>
                                    {{ $readiness['settings']['branch_name'] ?: 'Not configured' }}
                                    @if (!empty($readiness['settings']['branch_code']))
                                        ({{ $readiness['settings']['branch_code'] }})
                                    @endif
                                </td>
                            </tr>
                            <tr>
                                <th>DST Specification Code</th>
                                <td>{{ $readiness['settings']['specification_code'] ?: 'Not configured' }}</td>
                            </tr>
                            <tr>
                                <th>Risk Classification Basis</th>
                                <td>SACCO statutory delinquency classification</td>
                            </tr>
                            <tr>
                                <th>Currency / Nationality</th>
                                <td>{{ $readiness['settings']['currency'] }} / {{ $readiness['settings']['nationality'] }}</td>
                            </tr>
                            <tr>
                                <th>Database Timezone</th>
                                <td>{{ $readiness['settings']['db_timezone'] }}</td>
                            </tr>
                            <tr>
                                <th>Negative Information Threshold</th>
                                <td>
                                    KES {{ number_format((float) $readiness['settings']['negative_information_threshold'], 2) }}
                                    <span class="text-muted">
                                        — review warning only; contractual arrears are not altered
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card o-hidden mb-4">
            <div class="card-header">
                <h3 class="card-title m-0">Report Status</h3>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 mb-3">
                        <div class="text-muted small">Total Runs</div>
                        <div class="h4 mb-0">{{ number_format($stats['total']) }}</div>
                    </div>
                    <div class="col-6 mb-3">
                        <div class="text-muted small">Draft / Validated</div>
                        <div class="h4 mb-0">{{ number_format($stats['draft']) }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Finalised</div>
                        <div class="h4 mb-0">{{ number_format($stats['finalised']) }}</div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted small">Submitted</div>
                        <div class="h4 mb-0">{{ number_format($stats['submitted']) }}</div>
                    </div>
                </div>

                @if (($stats['with_errors'] ?? 0) > 0)
                    <div class="alert alert-warning mt-3 mb-0">
                        {{ number_format($stats['with_errors']) }} report run(s) currently contain record-level errors.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="card o-hidden mb-4">
    <div class="card-header">
        <h3 class="card-title m-0">Preview or Generate a CRB Report</h3>
    </div>

    <div class="card-body">
        <p class="text-muted">
            Preview reads the live source data without storing anything. Generate freezes the current transformed records into a versioned draft for audit, validation and finalisation.
        </p>

        <form method="GET" action="{{ route('reports.crb.preview') }}" class="row g-3 align-items-end" id="crb-preview-form">
            <div class="col-md-5">
                <label for="report_code" class="form-label">Report Type</label>
                <select name="report_code" id="report_code" class="form-control" required>
                    @foreach ($types as $code => $type)
                        <option value="{{ $code }}" @selected(old('report_code', 'CE') === $code)>
                            {{ $code }} — {{ $type['title'] }} ({{ ucfirst(strtolower($type['frequency'])) }})
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-3">
                <label for="report_date" class="form-label">Reporting / Snapshot Date</label>
                <input type="date"
                       name="report_date"
                       id="report_date"
                       class="form-control"
                       value="{{ old('report_date', $defaultDates['CE'] ?? now()->format('Y-m-d')) }}"
                       required>
                <div class="form-text">Monthly types are normalised to month-end.</div>
            </div>

            <div class="col-md-4">
                <button type="submit" class="btn btn-primary">
                    Preview
                </button>

                <button type="submit"
                        class="btn btn-outline-success"
                        formaction="{{ route('reports.crb.generate') }}"
                        formmethod="POST"
                        onclick="return prepareGenerate(this.form);">
                    Generate Draft
                </button>
            </div>
        </form>

        <form id="crb-generate-helper" method="POST" action="{{ route('reports.crb.generate') }}" class="d-none">
            @csrf
            <input type="hidden" name="report_code" id="helper_report_code">
            <input type="hidden" name="report_date" id="helper_report_date">
        </form>
    </div>
</div>

<div class="card o-hidden mb-4">
    <div class="card-header">
        <h3 class="card-title m-0">Supported DST Files</h3>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-sm mb-0">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Frequency</th>
                        <th>File</th>
                        <th>Implementation Scope</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($types as $code => $type)
                        <tr>
                            <td><strong>{{ $code }}</strong></td>
                            <td>{{ $type['frequency'] }}</td>
                            <td>{{ $type['title'] }}</td>
                            <td>{{ $type['description'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="alert alert-info mt-3 mb-0">
            The module intentionally implements CE, GI, CA, DP and MF first. It does not fabricate unsupported DST files or silently infer CRB onboarding rules that still require institution-specific confirmation.
        </div>
    </div>
</div>

<div class="card o-hidden mb-4">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title m-0">Recent CRB Report Runs</h3>
        <a href="{{ route('reports.crb.history') }}" class="btn btn-sm btn-outline-primary">View Full History</a>
    </div>

    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover table-sm mb-0">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Report Date</th>
                        <th>Version</th>
                        <th>Status</th>
                        <th class="text-end">Records</th>
                        <th class="text-end">Warnings</th>
                        <th class="text-end">Errors</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($recentReports as $report)
                        <tr>
                            <td>{{ $report->id }}</td>
                            <td><strong>{{ $report->code }}</strong></td>
                            <td>{{ optional($report->report_date)->format('d M Y') }}</td>
                            <td>{{ str_pad((string) $report->version, 3, '0', STR_PAD_LEFT) }}</td>
                            <td>
                                @php
                                    $badge = in_array($report->status, ['FINALISED', 'FINALIZED', 'SUBMITTED'], true)
                                        ? 'bg-success'
                                        : ($report->error_records > 0 ? 'bg-danger' : 'bg-warning');
                                @endphp
                                <span class="badge {{ $badge }}">{{ $report->status }}</span>
                            </td>
                            <td class="text-end">{{ number_format($report->total_records) }}</td>
                            <td class="text-end">{{ number_format($report->warning_records) }}</td>
                            <td class="text-end">{{ number_format($report->error_records) }}</td>
                            <td class="text-end">
                                <a href="{{ route('reports.crb.show', $report->id) }}" class="btn btn-sm btn-outline-primary">Open</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center text-muted">No CRB report drafts have been generated yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    (function () {
        const typeSelect = document.getElementById('report_code');
        const dateInput = document.getElementById('report_date');
        const dates = @json($defaultDates);

        if (typeSelect && dateInput) {
            typeSelect.addEventListener('change', function () {
                if (dates[this.value]) {
                    dateInput.value = dates[this.value];
                }
            });
        }
    })();

    function prepareGenerate(form) {
        if (!form.reportValidity()) {
            return false;
        }

        if (!confirm('Generate and freeze a new CRB draft version from the current source data?')) {
            return false;
        }

        document.getElementById('helper_report_code').value = document.getElementById('report_code').value;
        document.getElementById('helper_report_date').value = document.getElementById('report_date').value;
        document.getElementById('crb-generate-helper').submit();

        return false;
    }
</script>
@endsection
