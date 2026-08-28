@extends('layouts.app')

@section('content')

<div class="container">

    <div class="row">

        {{-- =========================================================
             SEARCH
             ========================================================= --}}
        <div class="col-md-12 mb-3">

            <div class="card text-start">

                <div class="card-body">

                    <h4 class="card-title mb-3">
                        Loan Performance Report
                    </h4>

                    <p class="mb-3">
                        Search by member name, SACCO ID, national ID,
                        phone number or company.
                    </p>

                    <form
                        method="GET"
                        action="{{ route('reports.sasra.loanperformance', ['version' => $version]) }}"
                    >

                        <div class="row">

                            <div class="col-md-6 form-group mb-3">

                                <label for="search">
                                    Searchable Fields
                                </label>

                                <input
                                    class="form-control"
                                    id="search"
                                    type="text"
                                    name="search"
                                    placeholder="Name, SACCO ID, national ID, phone or company"
                                    value="{{ request('search') }}"
                                    autocomplete="off"
                                >

                            </div>

                            <div class="col-md-6 d-flex align-items-end mb-3">

                                <button
                                    type="submit"
                                    class="btn btn-primary me-2"
                                >
                                    Search
                                </button>

                                @if(request()->filled('search'))

                                    <a
                                        href="{{ route('reports.sasra.loanperformance', ['version' => $version]) }}"
                                        class="btn btn-outline-secondary"
                                    >
                                        Clear
                                    </a>

                                @endif

                            </div>

                        </div>

                    </form>

                </div>

            </div>

        </div>


        {{-- =========================================================
             REPORT
             ========================================================= --}}
        <div class="col-md-12 mb-3">

            <div class="card text-start">

                <div class="card-body">

                    <div
                        class="d-flex flex-wrap justify-content-between align-items-center mb-3"
                    >

                        <div>

                            <h4 class="card-title mb-1">
                                Loans Issued
                            </h4>

                            <small class="text-muted">
                                Reporting Period:
                                {{ substr($reportPeriod, 0, 4) }}/{{ substr($reportPeriod, 4, 2) }}
                            </small>

                        </div>

                        <div class="text-muted small">
                            {{ number_format($loans->count()) }}
                            loan{{ $loans->count() === 1 ? '' : 's' }}
                        </div>

                    </div>


                    {{-- =================================================
                         CLASSIFICATION LEGEND
                         ================================================= --}}
                    <div class="loan-performance-legend mb-3">

                        <strong>
                            Loan Classification Legend:
                        </strong>

                        <span class="badge bg-success ms-2">
                            Current
                        </span>

                        <small class="me-3">
                            ≤ 2 months
                        </small>


                        <span class="badge bg-info">
                            Watch
                        </span>

                        <small class="me-3">
                            3–4 months
                        </small>


                        <span class="badge bg-warning">
                            Substandard
                        </span>

                        <small class="me-3">
                            5–6 months
                        </small>


                        <span class="badge bg-danger">
                            Doubtful
                        </span>

                        <small class="me-3">
                            7–9 months
                        </small>


                        <span class="badge bg-dark">
                            Loss
                        </span>

                        <small>
                            &gt; 9 months / No valid period
                        </small>

                    </div>


                    <p class="mb-3">
                        Outstanding loans categorized according to
                        their current performance classification.
                    </p>


                    {{-- =================================================
                         TABLE

                         COLUMN INDEXES

                         0  #
                         1  Member
                         2  SACCO ID
                         3  National ID
                         4  Phone
                         5  Company
                         6  Position
                         7  Loan Type
                         8  Loan Amount
                         9  Loan Paid
                         10 Outstanding
                         11 Loan Taken Period
                         12 Last Payment Period
                         13 Loan Category
                         ================================================= --}}
                    <div class="table-responsive">

                        <table
                            class="table table-striped table-bordered"
                            id="loanPerformanceTable"
                            style="width:100%"
                        >

                            <thead>

                                <tr>

                                    <th scope="col">
                                        #
                                    </th>

                                    <th scope="col">
                                        Member Name
                                    </th>

                                    <th scope="col">
                                        Sacco ID
                                    </th>

                                    <th scope="col">
                                        National ID
                                    </th>

                                    <th scope="col">
                                        Phone No
                                    </th>

                                    <th scope="col">
                                        Company
                                    </th>

                                    <th scope="col">
                                        Position
                                    </th>

                                    <th scope="col">
                                        Loan Type
                                    </th>

                                    <th
                                        scope="col"
                                        class="text-end"
                                    >
                                        Loan Amount
                                    </th>

                                    <th
                                        scope="col"
                                        class="text-end"
                                    >
                                        Loan Paid
                                    </th>

                                    <th
                                        scope="col"
                                        class="text-end"
                                    >
                                        Outstanding Balance
                                    </th>

                                    <th scope="col">
                                        Loan Taken Period
                                    </th>

                                    <th scope="col">
                                        Last Payment Period
                                    </th>

                                    <th scope="col">
                                        Loan Category
                                    </th>

                                </tr>

                            </thead>


                            <tbody>

                                @forelse($loans as $index => $loan)

                                    <tr>

                                        {{-- 0 --}}
                                        <td>
                                            {{ $index + 1 }}
                                        </td>


                                        {{-- 1 --}}
                                        <td class="member-name-cell">

                                            {{ $loan->member_name ?: '—' }}

                                        </td>


                                        {{-- 2 --}}
                                        <td>

                                            {{ $loan->member_sacco_id ?: '—' }}

                                        </td>


                                        {{-- 3 --}}
                                        <td>

                                            {{ $loan->member_national_id ?: '—' }}

                                        </td>


                                        {{-- 4 --}}
                                        <td>

                                            {{ $loan->member_phone_no ?: '—' }}

                                        </td>


                                        {{-- 5 --}}
                                        <td class="company-cell">

                                            {{ $loan->company_name ?: '—' }}

                                        </td>


                                        {{-- 6 --}}
                                        <td>

                                            {{ $loan->position }}

                                        </td>


                                        {{-- 7 --}}
                                        <td class="loan-type-cell">

                                            {{ $loan->loan_type_name ?: '—' }}

                                        </td>


                                        {{-- 8 --}}
                                        <td class="text-end text-nowrap">

                                            {{ number_format(
                                                (float) ($loan->loan_amount ?? 0),
                                                2
                                            ) }}

                                        </td>


                                        {{-- 9 --}}
                                        <td class="text-end text-nowrap">

                                            {{ number_format(
                                                (float) ($loan->loan_loan_paid ?? 0),
                                                2
                                            ) }}

                                        </td>


                                        {{-- 10 --}}
                                        <td class="text-end text-nowrap">

                                            {{ number_format(
                                                (float) ($loan->outstanding_balance ?? 0),
                                                2
                                            ) }}

                                        </td>


                                        {{-- 11 --}}
                                        <td class="text-nowrap">

                                            {{ $loan->loan_taken_period ?: '—' }}

                                        </td>


                                        {{-- 12 --}}
                                        <td class="text-nowrap">

                                            {{ $loan->last_payment_period ?: '—' }}

                                        </td>


                                        {{-- 13 --}}
                                        <td class="text-nowrap">

                                            <span
                                                class="badge {{ $loan->loan_category['class'] }}"
                                            >
                                                {{ $loan->loan_category['category'] }}
                                            </span>

                                        </td>

                                    </tr>

                                @empty

                                    <tr>

                                        <td
                                            colspan="14"
                                            class="text-center py-4"
                                        >
                                            No matching outstanding loans found.
                                        </td>

                                    </tr>

                                @endforelse

                            </tbody>

                        </table>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>


{{-- ===============================================================
     REPORT-SCOPED STYLING
     =============================================================== --}}
<style>

    /*
     * Scope all report table styling to this table only.
     */
    #loanPerformanceTable {
        width: 100% !important;
        font-size: 12px;
    }


    #loanPerformanceTable thead th {
        vertical-align: middle;
        white-space: nowrap;
    }


    #loanPerformanceTable tbody td {
        vertical-align: middle;
    }


    /*
     * These fields are naturally long.
     *
     * Allow wrapping instead of forcing the whole browser table
     * unnecessarily wide.
     */
    #loanPerformanceTable .member-name-cell,
    #loanPerformanceTable .company-cell,
    #loanPerformanceTable .loan-type-cell {
        white-space: normal;
        min-width: 120px;
    }


    #loanPerformanceTable .company-cell {
        min-width: 140px;
    }


    .loan-performance-legend {
        line-height: 2;
    }


    /*
     * DataTables horizontal scrolling remains available on smaller
     * displays.
     */
    #loanPerformanceTable_wrapper .dataTables_scroll {
        width: 100%;
    }


    #loanPerformanceTable_wrapper .dt-buttons {
        margin-bottom: 10px;
    }


    #loanPerformanceTable_wrapper .dt-button {
        margin-right: 4px;
        margin-bottom: 4px;
    }


    @media (max-width: 767.98px) {

        #loanPerformanceTable {
            font-size: 11px;
        }

        .loan-performance-legend {
            line-height: 2.3;
        }

    }

</style>


{{-- ===============================================================
     DATATABLE / EXPORT DEPENDENCIES
     =============================================================== --}}

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.0.1/js/dataTables.buttons.min.js"></script>


{{-- Excel --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>


{{-- PDF --}}
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.70/pdfmake.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.70/vfs_fonts.js"></script>


{{-- DataTables exports --}}
<script src="https://cdn.datatables.net/buttons/2.0.1/js/buttons.html5.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.0.1/js/buttons.print.min.js"></script>


<script>

$(document).ready(function () {

    'use strict';


    const tableSelector = '#loanPerformanceTable';

    /*
     * Prevent accidental second initialization if this page or one of
     * its layout assets initializes DataTables more than once.
     */
    if (
        $.fn.DataTable
        && $.fn.DataTable.isDataTable(tableSelector)
    ) {
        return;
    }


    const reportTitle = 'Loan Performance Report';

    const reportFilename =
        'loan_performance_{{ $reportPeriod }}';

    const reportPeriod =
        '{{ substr($reportPeriod, 0, 4) }}/{{ substr($reportPeriod, 4, 2) }}';


    $(tableSelector).DataTable({

        paging: false,

        ordering: true,

        info: true,

        searching: true,

        scrollX: true,

        autoWidth: false,

        dom: 'Bfrtip',


        /*
         * ===========================================================
         * EXPORT BUTTONS
         * ===========================================================
         */
        buttons: [

            /*
             * COPY
             */
            {
                extend: 'copyHtml5',

                text: 'Copy',

                title: reportTitle,

                exportOptions: {
                    columns: ':visible'
                }
            },


            /*
             * CSV
             */
            {
                extend: 'csvHtml5',

                text: 'CSV',

                title: reportTitle,

                filename: reportFilename,

                exportOptions: {
                    columns: ':visible'
                }
            },


            /*
             * EXCEL
             */
            {
                extend: 'excelHtml5',

                text: 'Excel',

                title: reportTitle,

                filename: reportFilename,

                exportOptions: {
                    columns: ':visible'
                }
            },


            /*
             * =======================================================
             * PDF
             *
             * A3 LANDSCAPE is deliberate.
             *
             * There are now 14 columns. Landscape provides the
             * horizontal space required to keep the report inside
             * one printable page width.
             * =======================================================
             */
            {
                extend: 'pdfHtml5',

                text: 'PDF',

                title: reportTitle,

                filename: reportFilename,

                orientation: 'landscape',

                pageSize: 'A3',

                exportOptions: {
                    columns: ':visible'
                },


                customize: function (doc) {

                    /*
                     * A3 landscape has substantially more horizontal
                     * room than A4 landscape or any portrait layout.
                     */
                    doc.pageOrientation = 'landscape';

                    doc.pageSize = 'A3';


                    /*
                     * Keep margins small but printable.
                     */
                    doc.pageMargins = [
                        18,
                        25,
                        18,
                        25
                    ];


                    /*
                     * Compact typography for a 14-column regulatory
                     * style report.
                     */
                    doc.defaultStyle.fontSize = 7;


                    if (doc.styles.tableHeader) {

                        doc.styles.tableHeader.fontSize = 7;

                        doc.styles.tableHeader.bold = true;

                        doc.styles.tableHeader.alignment = 'center';

                    }


                    if (doc.styles.title) {

                        doc.styles.title.fontSize = 14;

                        doc.styles.title.bold = true;

                        doc.styles.title.alignment = 'center';

                        doc.styles.title.margin = [
                            0,
                            0,
                            0,
                            5
                        ];

                    }


                    /*
                     * Add reporting-period information underneath the
                     * main PDF title.
                     */
                    doc.content.splice(
                        1,
                        0,
                        {
                            text: 'Reporting Period: ' + reportPeriod,
                            alignment: 'center',
                            fontSize: 8,
                            margin: [
                                0,
                                0,
                                0,
                                10
                            ]
                        }
                    );


                    /*
                     * Find the actual DataTables-exported table.
                     *
                     * Do not rely on a fixed doc.content index because
                     * title/message nodes can change the position.
                     */
                    let exportedTable = null;


                    for (
                        let i = 0;
                        i < doc.content.length;
                        i++
                    ) {

                        if (
                            doc.content[i]
                            && doc.content[i].table
                            && doc.content[i].table.body
                        ) {

                            exportedTable = doc.content[i];

                            break;

                        }

                    }


                    if (exportedTable) {

                        /*
                         * Exact 14-column width allocation.
                         *
                         * 0  #
                         * 1  Member Name
                         * 2  Sacco ID
                         * 3  National ID
                         * 4  Phone
                         * 5  Company
                         * 6  Position
                         * 7  Loan Type
                         * 8  Loan Amount
                         * 9  Loan Paid
                         * 10 Outstanding
                         * 11 Taken Period
                         * 12 Payment Period
                         * 13 Category
                         */
                        exportedTable.table.widths = [
                            18,
                            100,
                            45,
                            60,
                            68,
                            105,
                            45,
                            85,
                            70,
                            70,
                            80,
                            55,
                            55,
                            65
                        ];


                        /*
                         * Tight but readable cell padding.
                         */
                        exportedTable.layout = {

                            paddingLeft: function () {
                                return 2;
                            },

                            paddingRight: function () {
                                return 2;
                            },

                            paddingTop: function () {
                                return 2;
                            },

                            paddingBottom: function () {
                                return 2;
                            }

                        };


                        /*
                         * Right-align the three financial columns in
                         * the generated PDF.
                         */
                        const body = exportedTable.table.body;


                        for (
                            let rowIndex = 1;
                            rowIndex < body.length;
                            rowIndex++
                        ) {

                            [
                                8,
                                9,
                                10
                            ].forEach(function (columnIndex) {

                                if (
                                    !body[rowIndex]
                                    || typeof body[rowIndex][columnIndex] === 'undefined'
                                ) {
                                    return;
                                }


                                const cell =
                                    body[rowIndex][columnIndex];


                                if (
                                    cell
                                    && typeof cell === 'object'
                                ) {

                                    cell.alignment = 'right';

                                } else {

                                    body[rowIndex][columnIndex] = {
                                        text: cell,
                                        alignment: 'right'
                                    };

                                }

                            });

                        }

                    }


                    /*
                     * Page numbering.
                     */
                    doc.footer = function (
                        currentPage,
                        pageCount
                    ) {

                        return {

                            text:
                                'Page '
                                + currentPage
                                + ' of '
                                + pageCount,

                            alignment: 'right',

                            fontSize: 7,

                            margin: [
                                0,
                                5,
                                18,
                                0
                            ]

                        };

                    };

                }
            },


            /*
             * =======================================================
             * PRINT
             *
             * Browser print is also forced to A3 landscape.
             * =======================================================
             */
            {
                extend: 'print',

                text: 'Print',

                title: reportTitle,

                exportOptions: {
                    columns: ':visible'
                },


                customize: function (win) {

                    /*
                     * Add reporting period below the print title.
                     */
                    $(win.document.body)
                        .find('h1')
                        .after(
                            '<div class="loan-report-print-period">'
                            + 'Reporting Period: '
                            + reportPeriod
                            + '</div>'
                        );


                    /*
                     * All styling is confined to the DataTables print
                     * window.
                     */
                    $('<style>')
                        .prop(
                            'type',
                            'text/css'
                        )
                        .html(`

                            @page {
                                size: A3 landscape;
                                margin: 8mm;
                            }


                            html,
                            body {
                                width: 100%;
                                margin: 0;
                                padding: 0;
                            }


                            body {
                                font-size: 8pt !important;
                            }


                            h1 {
                                margin: 0 0 3px 0 !important;
                                font-size: 14pt !important;
                                text-align: center !important;
                            }


                            .loan-report-print-period {
                                margin-bottom: 10px;
                                font-size: 8pt;
                                text-align: center;
                            }


                            table {
                                width: 100% !important;
                                table-layout: auto !important;
                                border-collapse: collapse !important;
                                font-size: 7pt !important;
                            }


                            thead {
                                display: table-header-group;
                            }


                            tr {
                                page-break-inside: avoid;
                            }


                            th,
                            td {
                                padding: 3px 3px !important;
                                border: 1px solid #777 !important;
                                vertical-align: middle !important;
                            }


                            th {
                                font-weight: bold !important;
                                white-space: nowrap !important;
                            }


                            td:nth-child(2),
                            td:nth-child(6),
                            td:nth-child(8) {
                                white-space: normal !important;
                            }


                            td:nth-child(9),
                            td:nth-child(10),
                            td:nth-child(11) {
                                text-align: right !important;
                                white-space: nowrap !important;
                            }

                        `)
                        .appendTo(
                            $(win.document.head)
                        );

                }
            }

        ],


        /*
         * ===========================================================
         * COLUMN BEHAVIOUR
         * ===========================================================
         */
        columnDefs: [

            /*
             * Sequential row number should not be sortable.
             */
            {
                orderable: false,
                targets: 0
            },


            /*
             * Financial columns.
             *
             * Company shifted the old numeric column indexes by one,
             * so these are now 8, 9 and 10.
             */
            {
                className: 'text-end',
                targets: [
                    8,
                    9,
                    10
                ]
            }

        ]

    });

});

</script>

@endsection