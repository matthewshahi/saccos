@extends('layouts.app')

@section('content')

<div class="container">

    {{-- ============================================================
         SEARCH
         ============================================================ --}}
    <div class="row">

        <div class="col-md-12 mb-3">

            <div class="card text-start">

                <div class="card-body">

                    <h4 class="card-title mb-3">
                        Loan Performance Report
                    </h4>

                    <p>
                        Search loans by member name, SACCO ID, national ID,
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
                                >

                            </div>

                            <div class="col-md-6">

                                <button
                                    type="submit"
                                    class="btn btn-primary mt-4"
                                >
                                    Search
                                </button>

                            </div>

                        </div>

                    </form>

                </div>

            </div>

        </div>


        {{-- ========================================================
             REPORT
             ======================================================== --}}
        <div class="col-md-12 mb-3">

            <div class="card text-start">

                <div class="card-body">

                    <h4 class="card-title mb-3">
                        Loans Issued
                    </h4>


                    {{-- ==================================================
                         CLASSIFICATION LEGEND
                         ================================================== --}}
                    <div class="mb-3 loan-performance-legend">

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
                            &gt; 9 months / No valid payment
                        </small>

                    </div>


                    <p>
                        Below is a table of outstanding loans categorized
                        by their status.
                    </p>


                    {{-- ==================================================
                         LOAN TABLE
                         ================================================== --}}
                    <div class="table-responsive">

                        <table
                            class="table table-striped table-bordered nowrap"
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

                                        <td>
                                            {{ $index + 1 }}
                                        </td>

                                        <td>
                                            {{ $loan->member_name }}
                                        </td>

                                        <td>
                                            {{ $loan->member_sacco_id }}
                                        </td>

                                        <td>
                                            {{ $loan->member_national_id }}
                                        </td>

                                        <td>
                                            {{ $loan->member_phone_no }}
                                        </td>

                                        <td>
                                            {{ $loan->company_name ?: '—' }}
                                        </td>

                                        <td>
                                            {{ $loan->position }}
                                        </td>

                                        <td>
                                            {{ $loan->loan_type_name }}
                                        </td>

                                        <td class="text-end">
                                            {{ number_format((float) ($loan->loan_amount ?? 0), 2) }}
                                        </td>

                                        <td class="text-end">
                                            {{ number_format((float) ($loan->loan_loan_paid ?? 0), 2) }}
                                        </td>

                                        <td class="text-end">
                                            {{ number_format((float) ($loan->outstanding_balance ?? 0), 2) }}
                                        </td>

                                        <td>
                                            {{ $loan->loan_taken_period }}
                                        </td>

                                        <td>
                                            {{ $loan->last_payment_period ?: '—' }}
                                        </td>

                                        <td>
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


{{-- ================================================================
     PAGE-SCOPED STYLES
     ================================================================ --}}
<style>

    /*
     * Keep this report compact because it has many columns.
     * This only targets the Loan Performance table.
     */
    #loanPerformanceTable {
        font-size: 12px;
    }

    #loanPerformanceTable th,
    #loanPerformanceTable td {
        vertical-align: middle;
    }

    #loanPerformanceTable th {
        white-space: nowrap;
    }

    /*
     * Names and companies may wrap on screen rather than forcing the
     * complete report to become excessively wide.
     */
    #loanPerformanceTable th:nth-child(2),
    #loanPerformanceTable td:nth-child(2),
    #loanPerformanceTable th:nth-child(6),
    #loanPerformanceTable td:nth-child(6),
    #loanPerformanceTable th:nth-child(8),
    #loanPerformanceTable td:nth-child(8) {
        white-space: normal;
        min-width: 120px;
    }

    .loan-performance-legend {
        line-height: 2;
    }

</style>


{{-- ================================================================
     EXISTING DATATABLE DEPENDENCIES
     ================================================================ --}}

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.0.1/js/dataTables.buttons.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.0.1/js/buttons.html5.min.js"></script>

<script src="https://cdn.datatables.net/buttons/2.0.1/js/buttons.print.min.js"></script>


<script>

$(document).ready(function () {

    const reportTitle = 'Loan Performance Report';
    const reportFilename = 'loan_performance_{{ $currentPeriod }}';

    $('#loanPerformanceTable').DataTable({

        /*
         * Preserve existing behaviour.
         */
        paging: false,
        ordering: true,
        info: true,
        searching: true,
        scrollX: true,
        autoWidth: false,

        dom: 'Bfrtip',

        buttons: [

            /*
             * COPY
             */
            {
                extend: 'copyHtml5',
                title: reportTitle,
                exportOptions: {
                    columns: ':visible'
                }
            },


            /*
             * CSV
             *
             * Company is automatically included because it is now a
             * normal table column.
             */
            {
                extend: 'csvHtml5',
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
                title: reportTitle,
                filename: reportFilename,
                exportOptions: {
                    columns: ':visible'
                }
            },


            /*
             * PDF
             *
             * IMPORTANT:
             * This is intentionally A3 LANDSCAPE.
             *
             * The report now contains 14 columns. A4 portrait or A3
             * portrait gives less usable horizontal space and is therefore
             * more likely to cut the table.
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
                     * Compact margins leave more horizontal room while still
                     * maintaining a proper printable border area.
                     */
                    doc.pageMargins = [
                        18,
                        20,
                        18,
                        20
                    ];

                    /*
                     * Wide regulatory report: use compact readable type.
                     */
                    doc.defaultStyle.fontSize = 7;

                    if (doc.styles.tableHeader) {
                        doc.styles.tableHeader.fontSize = 7;
                        doc.styles.tableHeader.bold = true;
                    }

                    if (doc.styles.title) {
                        doc.styles.title.fontSize = 13;
                        doc.styles.title.bold = true;
                        doc.styles.title.alignment = 'center';
                        doc.styles.title.margin = [
                            0,
                            0,
                            0,
                            10
                        ];
                    }

                    /*
                     * Find the exported table safely.
                     *
                     * We do not assume it is always doc.content[1],
                     * because DataTables can add title/message nodes.
                     */
                    let exportedTable = null;

                    for (
                        let i = 0;
                        i < doc.content.length;
                        i++
                    ) {

                        if (doc.content[i].table) {
                            exportedTable = doc.content[i];
                            break;
                        }

                    }


                    if (exportedTable) {

                        /*
                         * 14 widths matching the 14 report columns:
                         *
                         *  0  #
                         *  1  Member Name
                         *  2  Sacco ID
                         *  3  National ID
                         *  4  Phone
                         *  5  Company
                         *  6  Position
                         *  7  Loan Type
                         *  8  Loan Amount
                         *  9  Loan Paid
                         * 10  Outstanding
                         * 11  Taken Period
                         * 12  Payment Period
                         * 13  Category
                         *
                         * These widths fit comfortably on A3 landscape.
                         */
                        exportedTable.table.widths = [
                            18,
                            100,
                            45,
                            60,
                            65,
                            105,
                            45,
                            85,
                            65,
                            65,
                            75,
                            55,
                            55,
                            65
                        ];

                        /*
                         * Reduce internal cell padding slightly so content
                         * stays inside the page rather than being clipped.
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

                    }

                }
            },


            /*
             * PRINT
             *
             * Use the same A3 landscape paper model when users print the
             * report directly from the browser.
             */
            {
                extend: 'print',
                title: reportTitle,

                exportOptions: {
                    columns: ':visible'
                },

                customize: function (win) {

                    /*
                     * Inject print-specific styling directly into the
                     * DataTables print window.
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

                            body {
                                font-size: 8pt !important;
                            }

                            table {
                                width: 100% !important;
                                border-collapse: collapse !important;
                                font-size: 7pt !important;
                            }

                            th,
                            td {
                                padding: 3px 4px !important;
                                vertical-align: middle !important;
                            }

                            th {
                                white-space: nowrap !important;
                            }

                            td:nth-child(2),
                            td:nth-child(6),
                            td:nth-child(8) {
                                white-space: normal !important;
                            }

                            h1 {
                                font-size: 14pt !important;
                                text-align: center !important;
                                margin-bottom: 10px !important;
                            }
                        `)
                        .appendTo(
                            $(win.document.head)
                        );

                }
            }

        ],


        /*
         * Column indexes AFTER Company has been added:
         *
         * 0  #
         * 1  Member
         * 2  Sacco ID
         * 3  National ID
         * 4  Phone
         * 5  Company
         * 6  Position
         * 7  Loan Type
         * 8  Loan Amount
         * 9  Loan Paid
         * 10 Outstanding
         */
        columnDefs: [

            {
                orderable: false,
                targets: 0
            },

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