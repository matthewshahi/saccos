@extends('layouts.app')

@section('title', 'Member Financial Position')

@section('styles')
<style>
    /* Prevent wrapping + make wide tables usable */
    .mfp-table-wrap {
        overflow-x: auto;
        overflow-y: auto;
        max-height: 70vh;
        -webkit-overflow-scrolling: touch;
    }

    .mfp-table th,
    .mfp-table td {
        white-space: nowrap;
        vertical-align: middle;
    }

    .mfp-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        background: #fff;
    }

    .mfp-table tfoot th,
    .mfp-table tfoot td {
        position: sticky;
        bottom: 0;
        z-index: 2;
        background: #fff;
        border-top: 2px solid #dee2e6;
        font-weight: 700;
    }
</style>
@endsection

@section('content')
@php
    $periodValue = $currentPeriod ?? date('Ym');

    if (is_object($periodValue)) {
        $periodValue = $periodValue->period
            ?? $periodValue->value
            ?? $periodValue->currentPeriod
            ?? date('Ym');
    }

    $periodValue = (string) $periodValue;
@endphp

<div class="container-fluid">

    <div class="row">
        <div class="col-md-12">
            <div class="card o-hidden mb-4">

                {{-- HEADER --}}
                <div class="card-header d-flex align-items-center justify-content-between">
                    <h3 class="card-title m-0">
                        Member Financial Position (As At)
                    </h3>

                    <a
                        href="#"
                        id="exportBtn"
                        class="btn btn-sm btn-outline-success disabled"
                        aria-disabled="true">
                        Export CSV
                    </a>
                </div>

                <div class="card-body">

                    {{-- FILTER FORM --}}
                    <form id="filterForm" class="row g-3 align-items-end" onsubmit="return false;">

                        <div class="col-md-2">
                            <label class="form-label mb-1"><strong>Period (YYYYMM)</strong></label>
                            <input
                                type="text"
                                id="period"
                                name="period"
                                class="form-control"
                                value="{{ $periodValue }}"
                                placeholder="YYYYMM"
                                maxlength="6"
                                autocomplete="off">
                            <small class="text-muted">Example: 202601</small>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label mb-1"><strong>Search</strong></label>
                            <input
                                type="text"
                                id="pms_srch"
                                name="pms_srch"
                                class="form-control"
                                placeholder="Name, Sacco ID, National ID, Email, Company, Department..."
                                autocomplete="off">
                        </div>

                        <div class="col-md-6 text-end">
                            <button type="button" id="loadReport" class="btn btn-primary">
                                Load Report
                            </button>
                            <button type="button" id="clearBtn" class="btn btn-outline-secondary ms-2">
                                Clear
                            </button>
                        </div>
                    </form>

                    <hr class="my-3">

                    {{-- STATES --}}
                    <div id="loading" class="text-center text-muted d-none">
                        Loading report, please wait…
                    </div>

                    <div id="tableWrapper" class="mfp-table-wrap d-none">
                        <table class="table table-hover table-bordered text-center mfp-table mb-0">
                            <thead id="reportHead"></thead>
                            <tbody id="reportBody"></tbody>
                            <tfoot id="reportFoot"></tfoot>
                        </table>
                    </div>

                    <div id="emptyState" class="text-center text-muted d-none">
                        No data found for the selected period.
                    </div>

                </div>
            </div>
        </div>
    </div>

</div>
@endsection
