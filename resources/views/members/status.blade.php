@extends('layouts.app')

@section('content')

    @include('member_name')



    @php

        $saccoName = trim((string) ($defaultCompanyName ?? config('app.name', 'SACCO')));
        /*
    |--------------------------------------------------------------------------
    | Statement metadata
    |--------------------------------------------------------------------------
    */

        $statementGeneratedAt = now();

        $statementReference =
            'MFS-' . ($member->member_sacco_id ?: $member->member_id) . '-' . $statementGeneratedAt->format('YmdHis');

        /*
    |--------------------------------------------------------------------------
    | Outstanding loans
    |--------------------------------------------------------------------------
    |
    | Filter here once so:
    | - numbering is correct
    | - empty state is correct
    | - UI/PDF/print all use the same records
    |
    */

        $outstandingLoans = collect($loansTakenWithGuarantors)
            ->filter(function ($loan) use ($threshold_amount) {
                return (float) ($loan->loan_amount ?? 0) - (float) ($loan->loan_loan_paid ?? 0) > $threshold_amount;
            })
            ->values();

        /*
    |--------------------------------------------------------------------------
    | Active guarantees provided by this member
    |--------------------------------------------------------------------------
    */

        $activeGuaranteesProvided = collect($loansGuaranteed)
            ->filter(function ($loan) use ($threshold_amount) {
                $loanBalance = (float) ($loan->loan_amount ?? 0) - (float) ($loan->loan_loan_paid ?? 0);

                $guaranteeTied =
                    (float) ($loan->loan_guar_amount_guaranteed ?? 0) - (float) ($loan->loan_guar_amount_freed ?? 0);

                return $loanBalance > $threshold_amount && $guaranteeTied > $threshold_amount;
            })
            ->values();

        /*
    |--------------------------------------------------------------------------
    | Loan date formatter
    |--------------------------------------------------------------------------
    |
    | Prefer the actual loan/disbursement date when available.
    | Fall back to YYYYMM period for older records.
    |
    */

        $loanDateDisplay = function ($loan) {
            $rawDate = $loan->loan_on ?? null;

            if (!empty($rawDate) && $rawDate !== '0000-00-00' && $rawDate !== '0000-00-00 00:00:00') {
                try {
                    return [
                        'label' => 'Loan Date',
                        'value' => \Illuminate\Support\Carbon::parse($rawDate)->format('d M Y'),
                    ];
                } catch (\Throwable $e) {
                    // Fall through to period.
                }
            }

            return [
                'label' => 'Period Taken',
                'value' => $loan->loan_taken_period ?? '—',
            ];
        };
    @endphp


    <style>
        /*
        |--------------------------------------------------------------------------
        | PAGE
        |--------------------------------------------------------------------------
        */

        .member-financial-status-page {
            --mfs-border: #dfe3e8;
            --mfs-border-strong: #c8cdd3;
            --mfs-text: #1e2430;
            --mfs-muted: #687282;
            --mfs-soft: #f6f7f9;
            --mfs-soft-2: #fafbfc;
            --mfs-accent: #663399;
            --mfs-positive: #166534;
            --mfs-negative: #9f1239;

            max-width: 1420px;
            margin: 0 auto 30px;
            color: var(--mfs-text);
        }


        /*
        |--------------------------------------------------------------------------
        | SCREEN TOOLBAR
        |--------------------------------------------------------------------------
        */

        .mfs-toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 18px;
            margin-bottom: 12px;
            padding: 12px 14px;
            background: #fff;
            border: 1px solid var(--mfs-border);
            border-radius: 6px;
        }

        .mfs-toolbar-title {
            margin: 0;
            font-size: 14px;
            font-weight: 800;
            color: #20242b;
        }

        .mfs-toolbar-help {
            margin-top: 2px;
            font-size: 11px;
            color: var(--mfs-muted);
        }

        .mfs-toolbar-actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .mfs-toolbar-actions .btn {
            white-space: nowrap;
        }

        .mfs-pdf-loader {
            font-size: 11px;
            color: var(--mfs-muted);
            font-weight: 700;
        }


        /*
        |--------------------------------------------------------------------------
        | STATEMENT SHEET
        |--------------------------------------------------------------------------
        */

        .mfs-sheet {
            width: 100%;
            background: #fff;
            border: 1px solid var(--mfs-border);
            box-shadow: 0 2px 8px rgba(20, 25, 35, .045);
        }


        /*
        |--------------------------------------------------------------------------
        | INSTITUTION / DOCUMENT HEADER
        |--------------------------------------------------------------------------
        */

        .mfs-document-header {
            background: #fff;
        }

        .mfs-institution-row {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 30px;
            padding: 24px 26px 20px;
            border-bottom: 3px solid #24272d;
        }

        .mfs-institution-name {
            font-size: 21px;
            line-height: 1.1;
            font-weight: 900;
            letter-spacing: .03em;
            color: #17191d;
        }

        .mfs-document-title {
            margin-top: 9px;
            font-size: 15px;
            line-height: 1.25;
            font-weight: 900;
            letter-spacing: .055em;
            color: #252932;
        }

        .mfs-document-subtitle {
            margin-top: 4px;
            font-size: 11px;
            color: var(--mfs-muted);
        }

        .mfs-document-meta {
            min-width: 315px;
            font-size: 11px;
        }

        .mfs-meta-row {
            display: grid;
            grid-template-columns: 105px 1fr;
            gap: 14px;
            padding: 5px 0;
            border-bottom: 1px solid #eceef1;
        }

        .mfs-meta-row:last-child {
            border-bottom: 0;
        }

        .mfs-meta-row span {
            color: var(--mfs-muted);
        }

        .mfs-meta-row strong {
            color: #252932;
            text-align: right;
            font-weight: 800;
        }


        /*
        |--------------------------------------------------------------------------
        | MEMBER IDENTITY
        |--------------------------------------------------------------------------
        */

        .mfs-member-panel {
            display: grid;
            grid-template-columns: minmax(280px, 1.05fr) 2fr;
            gap: 25px;
            padding: 18px 26px;
            background: #f5f6f8;
            border-bottom: 1px solid var(--mfs-border-strong);
        }

        .mfs-member-label {
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .08em;
            color: var(--mfs-muted);
            text-transform: uppercase;
        }

        .mfs-member-name {
            margin-top: 4px;
            font-size: 19px;
            line-height: 1.25;
            font-weight: 900;
            color: #16191f;
        }

        .mfs-member-number {
            margin-top: 4px;
            font-size: 11px;
            color: #505968;
        }

        .mfs-member-details {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 16px;
            align-items: start;
        }

        .mfs-member-detail {
            padding-left: 14px;
            border-left: 1px solid #d5d9de;
        }

        .mfs-member-detail-label {
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .035em;
            color: var(--mfs-muted);
            text-transform: uppercase;
        }

        .mfs-member-detail-value {
            margin-top: 4px;
            font-size: 11px;
            line-height: 1.35;
            font-weight: 800;
            color: #262b34;
            overflow-wrap: anywhere;
        }


        /*
        |--------------------------------------------------------------------------
        | SECTIONS
        |--------------------------------------------------------------------------
        */

        .mfs-section {
            padding: 23px 26px;
            border-bottom: 1px solid var(--mfs-border);
        }

        .mfs-section:last-of-type {
            border-bottom: 0;
        }

        .mfs-section-head {
            margin-bottom: 14px;
        }

        .mfs-section-number {
            display: inline-block;
            margin-right: 6px;
            color: var(--mfs-muted);
            font-size: 10px;
            font-weight: 800;
        }

        .mfs-section-title {
            display: inline;
            margin: 0;
            color: #292e37;
            font-size: 13px;
            font-weight: 900;
            letter-spacing: .045em;
            text-transform: uppercase;
        }

        .mfs-section-description {
            margin-top: 4px;
            color: var(--mfs-muted);
            font-size: 11px;
            line-height: 1.4;
        }


        /*
        |--------------------------------------------------------------------------
        | FINANCIAL POSITION GROUPS
        |--------------------------------------------------------------------------
        */

        .mfs-position-groups {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            border: 1px solid var(--mfs-border-strong);
        }

        .mfs-position-group {
            min-width: 0;
            border-right: 1px solid var(--mfs-border-strong);
        }

        .mfs-position-group:last-child {
            border-right: 0;
        }

        .mfs-position-group-title {
            padding: 10px 13px;
            background: #edeff2;
            border-bottom: 1px solid var(--mfs-border-strong);
            color: #454b56;
            font-size: 10px;
            font-weight: 900;
            letter-spacing: .04em;
            text-transform: uppercase;
        }

        .mfs-position-item {
            padding: 12px 14px;
            border-bottom: 1px solid #e9ebee;
        }

        .mfs-position-item:last-child {
            border-bottom: 0;
        }

        .mfs-position-label {
            color: var(--mfs-muted);
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .025em;
            text-transform: uppercase;
        }

        .mfs-position-value {
            margin-top: 4px;
            color: #171a20;
            font-size: 17px;
            line-height: 1.2;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
            white-space: nowrap;
        }

        .mfs-position-note {
            margin-top: 3px;
            color: #858d98;
            font-size: 9px;
        }


        /*
        |--------------------------------------------------------------------------
        | LOAN BLOCK
        |--------------------------------------------------------------------------
        */

        .mfs-loan {
            margin-bottom: 20px;
            border: 1px solid var(--mfs-border-strong);
            background: #fff;
        }

        .mfs-loan:last-child {
            margin-bottom: 0;
        }

        .mfs-loan-overview {
            background: #fff;
        }

        .mfs-loan-head {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 18px;
            padding: 13px 16px;
            background: #f1f2f4;
            border-bottom: 1px solid var(--mfs-border-strong);
        }

        .mfs-loan-type {
            color: #171a1f;
            font-size: 15px;
            font-weight: 900;
        }

        .mfs-loan-id {
            margin-top: 3px;
            color: var(--mfs-muted);
            font-size: 10px;
        }

        .mfs-loan-actions {
            flex: 0 0 auto;
        }

        .mfs-loan-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            border-bottom: 1px solid var(--mfs-border);
        }

        .mfs-loan-stat {
            min-width: 0;
            padding: 12px 16px;
            border-right: 1px solid var(--mfs-border);
            border-bottom: 1px solid var(--mfs-border);
        }

        .mfs-loan-stat:nth-child(3n) {
            border-right: 0;
        }

        .mfs-loan-stat:nth-last-child(-n+3) {
            border-bottom: 0;
        }

        .mfs-loan-stat-label {
            color: var(--mfs-muted);
            font-size: 9px;
            font-weight: 800;
            letter-spacing: .025em;
            text-transform: uppercase;
        }

        .mfs-loan-stat-value {
            margin-top: 4px;
            color: #22262e;
            font-size: 13px;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
        }

        .mfs-loan-stat-value.is-negative {
            color: var(--mfs-negative);
        }

        .mfs-loan-description {
            padding: 10px 16px;
            border-bottom: 1px solid var(--mfs-border);
            color: #505865;
            font-size: 11px;
            line-height: 1.45;
        }

        .mfs-loan-description strong {
            color: #30353d;
        }


        /*
        |--------------------------------------------------------------------------
        | GUARANTEE POSITION
        |--------------------------------------------------------------------------
        */

        .mfs-guarantee-area {
            padding: 14px 16px 16px;
        }

        .mfs-guarantee-head {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
            margin-bottom: 9px;
        }

        .mfs-guarantee-title {
            font-size: 10px;
            font-weight: 900;
            letter-spacing: .04em;
            color: #333842;
            text-transform: uppercase;
        }

        .mfs-guarantee-total {
            color: var(--mfs-muted);
            font-size: 10px;
        }

        .mfs-guarantee-total strong {
            color: #242933;
            font-weight: 900;
            font-variant-numeric: tabular-nums;
        }


        /*
        |--------------------------------------------------------------------------
        | TABLES
        |--------------------------------------------------------------------------
        */

        .mfs-table-wrap {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .mfs-table {
            width: 100%;
            margin: 0 !important;
            border-collapse: collapse !important;
            font-size: 11px;
        }

        .mfs-table th,
        .mfs-table td {
            padding: 8px 9px !important;
            vertical-align: middle;
            border: 1px solid #d7dbe0 !important;
        }

        .mfs-table thead th {
            background: #eceef1 !important;
            color: #4d5562;
            font-size: 9px;
            font-weight: 900;
            letter-spacing: .025em;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .mfs-table tbody td {
            background: #fff;
            color: #272c35;
        }

        .mfs-table tbody tr:nth-child(even) td {
            background: #fbfbfc;
        }

        .mfs-money {
            text-align: right;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }

        .mfs-nowrap {
            white-space: nowrap;
        }

        .mfs-member-link {
            font-weight: 700;
        }


        /*
        |--------------------------------------------------------------------------
        | EMPTY STATE
        |--------------------------------------------------------------------------
        */

        .mfs-empty {
            padding: 18px;
            color: var(--mfs-muted);
            background: #fafafa;
            border: 1px dashed #c9ced5;
            font-size: 11px;
            text-align: center;
        }


        /*
        |--------------------------------------------------------------------------
        | FOOTER
        |--------------------------------------------------------------------------
        */

        .mfs-footer {
            padding: 14px 26px;
            background: #f7f8f9;
            border-top: 1px solid var(--mfs-border-strong);
            color: #666f7c;
            font-size: 9px;
            line-height: 1.5;
        }

        .mfs-footer-main {
            display: flex;
            justify-content: space-between;
            gap: 20px;
        }

        .mfs-footer strong {
            color: #333943;
        }

        .mfs-footer-note {
            margin-top: 5px;
            color: #818895;
        }

.mfs-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.mfs-action-icon {
    width: 14px;
    height: 14px;
    flex: 0 0 14px;
}

        /*
        |--------------------------------------------------------------------------
        | PDF EXPORT MODE
        |--------------------------------------------------------------------------
        |
        | Fixed width means a PDF downloaded from a phone looks like the same
        | formal document downloaded from desktop.
        |
        */

        #memberFinancialStatusPrintable.mfs-export-mode {
            width: 1200px !important;
            max-width: 1200px !important;
            min-width: 1200px !important;
            border: 0 !important;
            box-shadow: none !important;
            background: #fff !important;
        }

        #memberFinancialStatusPrintable.mfs-export-mode .mfs-position-groups {
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        }

        #memberFinancialStatusPrintable.mfs-export-mode .mfs-member-panel {
            grid-template-columns: minmax(280px, 1.05fr) 2fr !important;
        }

        #memberFinancialStatusPrintable.mfs-export-mode .mfs-member-details {
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        }

        #memberFinancialStatusPrintable.mfs-export-mode .mfs-loan-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr)) !important;
        }

        #memberFinancialStatusPrintable.mfs-export-mode .mfs-loan-actions,
        #memberFinancialStatusPrintable.mfs-export-mode .mfs-admin-only {
            display: none !important;
        }

        #memberFinancialStatusPrintable.mfs-export-mode .mfs-table-wrap,
        #memberFinancialStatusPrintable.mfs-export-mode .table-responsive {
            overflow: visible !important;
        }

        #memberFinancialStatusPrintable.mfs-export-mode a {
            color: inherit !important;
            text-decoration: none !important;
        }


        /*
        |--------------------------------------------------------------------------
        | RESPONSIVE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 991.98px) {

            .mfs-institution-row {
                gap: 18px;
            }

            .mfs-document-meta {
                min-width: 280px;
            }

            .mfs-member-panel {
                grid-template-columns: 1fr;
            }

            .mfs-member-details {
                border-top: 1px solid #ddd;
                padding-top: 14px;
            }

            .mfs-member-detail:first-child {
                padding-left: 0;
                border-left: 0;
            }
        }


        @media (max-width: 767.98px) {

            .member-financial-status-page {
                margin-left: -3px;
                margin-right: -3px;
            }

            .mfs-toolbar {
                display: block;
                margin-left: 8px;
                margin-right: 8px;
            }

            .mfs-toolbar-actions {
                display: grid;
                grid-template-columns: 1fr 1fr;
                margin-top: 10px;
            }

            .mfs-toolbar-actions .btn {
                width: 100%;
            }

            .mfs-pdf-loader {
                grid-column: 1 / -1;
            }

            .mfs-sheet {
                border-left: 0;
                border-right: 0;
            }

            .mfs-institution-row {
                display: block;
                padding: 17px 14px;
            }

            .mfs-document-meta {
                min-width: 0;
                margin-top: 16px;
            }

            .mfs-member-panel {
                padding: 15px 14px;
            }

            .mfs-member-details {
                grid-template-columns: 1fr 1fr;
                gap: 13px;
            }

            .mfs-member-detail {
                padding-left: 0;
                border-left: 0;
            }

            .mfs-section {
                padding: 18px 14px;
            }

            .mfs-position-groups {
                grid-template-columns: 1fr;
            }

            .mfs-position-group {
                border-right: 0;
                border-bottom: 1px solid var(--mfs-border-strong);
            }

            .mfs-position-group:last-child {
                border-bottom: 0;
            }

            .mfs-loan-grid {
                grid-template-columns: 1fr 1fr;
            }

            .mfs-loan-stat,
            .mfs-loan-stat:nth-child(3n) {
                border-right: 1px solid var(--mfs-border);
                border-bottom: 1px solid var(--mfs-border);
            }

            .mfs-loan-stat:nth-child(2n) {
                border-right: 0;
            }

            .mfs-loan-head {
                display: block;
            }

            .mfs-loan-actions {
                margin-top: 10px;
            }

            .mfs-loan-actions .btn {
                width: 100%;
            }

            .mfs-footer {
                padding-left: 14px;
                padding-right: 14px;
            }

            .mfs-footer-main {
                display: block;
            }
        }


        @media (max-width: 480px) {

            .mfs-toolbar-actions {
                grid-template-columns: 1fr;
            }

            .mfs-member-details {
                grid-template-columns: 1fr;
            }

            .mfs-loan-grid {
                grid-template-columns: 1fr;
            }

            .mfs-loan-stat,
            .mfs-loan-stat:nth-child(2n),
            .mfs-loan-stat:nth-child(3n) {
                border-right: 0;
            }

            .mfs-position-value {
                font-size: 16px;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | PRINT
        |--------------------------------------------------------------------------
        */

        @page {
            size: A4 landscape;
            margin: 9mm 8mm 11mm;
        }

        @media print {

            html,
            body {
                margin: 0 !important;
                padding: 0 !important;
                background: #fff !important;
            }

            /*
             * Hide absolutely everything in the application first.
             */
            body * {
                visibility: hidden !important;
            }

            /*
             * Reveal only this financial statement.
             */
            #memberFinancialStatusPrintable,
            #memberFinancialStatusPrintable * {
                visibility: visible !important;
            }

            #memberFinancialStatusPrintable {
                position: absolute;
                left: 0;
                top: 0;

                width: 100% !important;
                max-width: none !important;
                min-width: 0 !important;

                margin: 0 !important;
                padding: 0 !important;

                border: 0 !important;
                box-shadow: none !important;

                background: #fff !important;
            }

            #memberFinancialStatusPrintable .mfs-admin-only,
            #memberFinancialStatusPrintable .mfs-loan-actions {
                display: none !important;
            }

            #memberFinancialStatusPrintable a {
                color: #000 !important;
                text-decoration: none !important;
            }

            #memberFinancialStatusPrintable .mfs-institution-row {
                display: flex !important;
                padding: 4mm 4mm 3.5mm !important;
                border-bottom: 2px solid #000 !important;
            }

            #memberFinancialStatusPrintable .mfs-document-meta {
                min-width: 75mm !important;
            }

            #memberFinancialStatusPrintable .mfs-member-panel {
                display: grid !important;
                grid-template-columns: 1fr 2fr !important;
                padding: 3mm 4mm !important;
            }

            #memberFinancialStatusPrintable .mfs-member-details {
                display: grid !important;
                grid-template-columns: repeat(3, 1fr) !important;
            }

            #memberFinancialStatusPrintable .mfs-position-groups {
                display: grid !important;
                grid-template-columns: repeat(3, 1fr) !important;
            }

            #memberFinancialStatusPrintable .mfs-loan-grid {
                display: grid !important;
                grid-template-columns: repeat(3, 1fr) !important;
            }

            #memberFinancialStatusPrintable .mfs-section {
                padding: 4mm !important;
            }

            #memberFinancialStatusPrintable .mfs-position-item {
                padding: 2.5mm 3mm !important;
            }

            #memberFinancialStatusPrintable .mfs-position-value {
                font-size: 10.5pt !important;
            }

            #memberFinancialStatusPrintable .mfs-loan {
                margin-bottom: 4mm !important;
            }

            #memberFinancialStatusPrintable .mfs-loan-head {
                padding: 2.5mm 3mm !important;
            }

            #memberFinancialStatusPrintable .mfs-loan-stat {
                padding: 2.5mm 3mm !important;
            }

            #memberFinancialStatusPrintable .mfs-guarantee-area {
                padding: 3mm !important;
            }

            #memberFinancialStatusPrintable .mfs-table-wrap,
            #memberFinancialStatusPrintable .table-responsive {
                overflow: visible !important;
            }

            #memberFinancialStatusPrintable .mfs-table {
                width: 100% !important;
                font-size: 7.5pt !important;
            }

            #memberFinancialStatusPrintable .mfs-table th,
            #memberFinancialStatusPrintable .mfs-table td {
                padding: 1.6mm 1.8mm !important;
                border: 1px solid #aaa !important;
            }

            #memberFinancialStatusPrintable .mfs-table thead {
                display: table-header-group;
            }

            #memberFinancialStatusPrintable .mfs-table tr {
                break-inside: avoid;
                page-break-inside: avoid;
            }

            #memberFinancialStatusPrintable .mfs-loan-overview {
                break-inside: avoid;
                page-break-inside: avoid;
            }

            #memberFinancialStatusPrintable .mfs-loan-head,
            #memberFinancialStatusPrintable .mfs-section-head,
            #memberFinancialStatusPrintable .mfs-guarantee-head {
                break-after: avoid;
                page-break-after: avoid;
            }

            #memberFinancialStatusPrintable .mfs-loan {
                break-inside: auto;
                page-break-inside: auto;
            }

            #memberFinancialStatusPrintable .mfs-institution-row,
            #memberFinancialStatusPrintable .mfs-member-panel,
            #memberFinancialStatusPrintable .mfs-position-group-title,
            #memberFinancialStatusPrintable .mfs-loan-head,
            #memberFinancialStatusPrintable .mfs-table thead th {
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            #memberFinancialStatusPrintable .mfs-footer {
                padding: 3mm 4mm !important;
                background: #fff !important;
                border-top: 1px solid #777 !important;
            }
        }
    </style>


    <div class="member-financial-status-page">

        {{-- ================================================================
         SCREEN MESSAGES
         ================================================================ --}}

        @if (session('success'))
            <div class="alert alert-success mfs-screen-only">
                {{ session('success') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger mfs-screen-only">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif


        {{-- ================================================================
         SCREEN ACTIONS
         ================================================================ --}}

        <div class="mfs-toolbar mfs-screen-only">

            <div>
                <div class="mfs-toolbar-title">
                    Member Financial Status
                </div>

                <div class="mfs-toolbar-help">
                    Current member balances, loans and guarantee exposure.
                </div>
            </div>


            <div class="mfs-toolbar-actions">

           <button
    type="button"
    id="printMemberFinancialStatus"
    class="btn btn-outline-secondary btn-sm mfs-action-btn"
>
    <svg
        class="mfs-action-icon"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path d="M6 9V3h12v6"></path>
        <path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"></path>
        <rect x="6" y="14" width="12" height="7"></rect>
    </svg>

    <span>Print</span>
</button>


<button
    type="button"
    id="downloadMemberFinancialStatusPdf"
    class="btn btn-primary btn-sm mfs-action-btn"
>
    <svg
        class="mfs-action-icon"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.8"
        stroke-linecap="round"
        stroke-linejoin="round"
        aria-hidden="true"
    >
        <path d="M12 3v12"></path>
        <path d="m7 10 5 5 5-5"></path>
        <path d="M5 21h14"></path>
    </svg>

    <span>Download PDF</span>
</button>


                <span id="memberFinancialStatusPdfLoader" class="mfs-pdf-loader d-none">
                    <i class="fas fa-spinner fa-spin me-1"></i>
                    Preparing statement...
                </span>

            </div>

        </div>


        {{-- ================================================================
         OFFICIAL PRINTABLE STATEMENT
         ================================================================ --}}

        <div class="mfs-sheet" id="memberFinancialStatusPrintable">

            {{-- ============================================================
             DOCUMENT HEADER
             ============================================================ --}}

            <header class="mfs-document-header">

                <div class="mfs-institution-row">

                    <div>

                        <div class="mfs-institution-name">
                            {{ $saccoName }}
                        </div>

                        <div class="mfs-document-title">
                            MEMBER FINANCIAL STATUS STATEMENT
                        </div>

                        <div class="mfs-document-subtitle">
                            Member balances, loan obligations and guarantee exposure
                        </div>

                    </div>


                    <div class="mfs-document-meta">

                        <div class="mfs-meta-row">
                            <span>Statement Ref.</span>

                            <strong>
                                {{ $statementReference }}
                            </strong>
                        </div>


                        <div class="mfs-meta-row">
                            <span>Position Date</span>

                            <strong>
                                {{ $statementGeneratedAt->format('d M Y') }}
                            </strong>
                        </div>


                        <div class="mfs-meta-row">
                            <span>Generated</span>

                            <strong>
                                {{ $statementGeneratedAt->format('d M Y H:i') }}
                            </strong>
                        </div>


                        <div class="mfs-meta-row">
                            <span>Currency</span>

                            <strong>
                                Kenya Shillings (KES)
                            </strong>
                        </div>

                    </div>

                </div>


                {{-- MEMBER DETAILS --}}

                <div class="mfs-member-panel">

                    <div>

                        <div class="mfs-member-label">
                            Member
                        </div>

                        <div class="mfs-member-name">
                            {{ $member->member_name }}
                        </div>

                        <div class="mfs-member-number">
                            Member No.
                            <strong>
                                {{ $member->member_sacco_id }}
                            </strong>
                        </div>

                    </div>


                    <div class="mfs-member-details">

                        <div class="mfs-member-detail">

                            <div class="mfs-member-detail-label">
                                Company / Institution
                            </div>

                            <div class="mfs-member-detail-value">
                                {{ $member->company_name ?: '—' }}
                            </div>

                        </div>


                        <div class="mfs-member-detail">

                            <div class="mfs-member-detail-label">
                                Department
                            </div>

                            <div class="mfs-member-detail-value">
                                {{ $member->department_name ?: '—' }}
                            </div>

                        </div>


                        <div class="mfs-member-detail">

                            <div class="mfs-member-detail-label">
                                Financial Position
                            </div>

                            <div class="mfs-member-detail-value">
                                As at {{ $statementGeneratedAt->format('d M Y') }}
                            </div>

                        </div>

                    </div>

                </div>

            </header>


            {{-- ============================================================
             SECTION 1 — FINANCIAL POSITION
             ============================================================ --}}

            <section class="mfs-section">

                <div class="mfs-section-head">

                    <span class="mfs-section-number">
                        01
                    </span>

                    <h2 class="mfs-section-title">
                        Financial Position Summary
                    </h2>

                    <div class="mfs-section-description">
                        Current balances and financial exposure recorded against
                        this member.
                    </div>

                </div>


                <div class="mfs-position-groups">

                    {{-- MEMBER BALANCES --}}

                    <div class="mfs-position-group">

                        <div class="mfs-position-group-title">
                            Member Balances
                        </div>


                        <div class="mfs-position-item">

                            <div class="mfs-position-label">
                                Savings / Deposits
                            </div>

                            <div class="mfs-position-value">
                                KES
                                {{ number_format($memberFinancials['total_share_deposit'], 2) }}
                            </div>

                        </div>


                        <div class="mfs-position-item">

                            <div class="mfs-position-label">
                                Share Capital
                            </div>

                            <div class="mfs-position-value">
                                KES
                                {{ number_format($memberFinancials['total_capital_shares'], 2) }}
                            </div>

                        </div>


                        <div class="mfs-position-item">

                            <div class="mfs-position-label">
                                FOSA Deposits
                            </div>

                            <div class="mfs-position-value">
                                KES
                                {{ number_format($memberFinancials['total_fosa_deposits'], 2) }}
                            </div>

                        </div>

                    </div>


                    {{-- CREDIT EXPOSURE --}}

                    <div class="mfs-position-group">

                        <div class="mfs-position-group-title">
                            Credit Exposure
                        </div>


                        <div class="mfs-position-item">

                            <div class="mfs-position-label">
                                Outstanding Loan Principal
                            </div>

                            <div class="mfs-position-value">
                                KES
                                {{ number_format($memberFinancials['unpaid_loan'], 2) }}
                            </div>

                        </div>


                        <div class="mfs-position-item">

                            <div class="mfs-position-label">
                                Outstanding Loans
                            </div>

                            <div class="mfs-position-value">
                                {{ number_format($outstandingLoans->count()) }}
                            </div>

                            <div class="mfs-position-note">
                                Active loan account(s)
                            </div>

                        </div>

                    </div>


                    {{-- GUARANTEE EXPOSURE --}}

                    <div class="mfs-position-group">

                        <div class="mfs-position-group-title">
                            Guarantee Exposure
                        </div>


                        <div class="mfs-position-item">

                            <div class="mfs-position-label">
                                Savings Tied — Other Members
                            </div>

                            <div class="mfs-position-value">
                                KES
                                {{ number_format($memberFinancials['tied_shares_others'], 2) }}
                            </div>

                        </div>


                        <div class="mfs-position-item">

                            <div class="mfs-position-label">
                                Savings Tied — Self
                            </div>

                            <div class="mfs-position-value">
                                KES
                                {{ number_format($memberFinancials['tied_shares_self'], 2) }}
                            </div>

                        </div>


                        <div class="mfs-position-item">

                            <div class="mfs-position-label">
                                Loans Currently Guaranteed
                            </div>

                            <div class="mfs-position-value">
                                {{ number_format($activeGuaranteesProvided->count()) }}
                            </div>

                        </div>

                    </div>

                </div>

            </section>


            {{-- ============================================================
             SECTION 2 — LOANS TAKEN
             ============================================================ --}}

            <section class="mfs-section">

                <div class="mfs-section-head">

                    <span class="mfs-section-number">
                        02
                    </span>

                    <h2 class="mfs-section-title">
                        Loans Taken & Security Position
                    </h2>

                    <div class="mfs-section-description">
                        Outstanding member loans together with the guarantors
                        currently securing each facility.
                    </div>

                </div>


                @if ($outstandingLoans->isNotEmpty())
                    @foreach ($outstandingLoans as $loan)
                        @php
                            $loanBalance = (float) ($loan->loan_amount ?? 0) - (float) ($loan->loan_loan_paid ?? 0);

                            $principalPaid = (float) ($loan->loan_loan_paid ?? 0);

                            $activeGuarantee = collect($loan->guarantors ?? [])->sum(function ($guarantor) {
                                return max(
                                    0,
                                    (float) ($guarantor->loan_guar_amount_guaranteed ?? 0) -
                                        (float) ($guarantor->loan_guar_amount_freed ?? 0),
                                );
                            });

                            $loanDate = $loanDateDisplay($loan);
                        @endphp


                        <article class="mfs-loan">

                            <div class="mfs-loan-overview">

                                {{-- LOAN HEADER --}}

                                <div class="mfs-loan-head">

                                    <div>

                                        <div class="mfs-loan-type">
                                            {{ $loan->loan_type_name }}
                                        </div>

                                        <div class="mfs-loan-id">
                                            Loan No.
                                            <strong>
                                                {{ $loan->loan_id }}
                                            </strong>
                                        </div>

                                    </div>


                                    @if ($showHyperlinks)
                                        <div class="mfs-loan-actions">

                                            <button type="button" class="btn btn-primary btn-sm js-add-guarantor"
                                                data-loan-id="{{ $loan->loan_id }}">
                                                <i class="i-Add-User me-1"></i>
                                                Add Guarantor
                                            </button>

                                        </div>
                                    @endif

                                </div>


                                {{-- LOAN FINANCIAL POSITION --}}

                                <div class="mfs-loan-grid">

                                    <div class="mfs-loan-stat">

                                        <div class="mfs-loan-stat-label">
                                            Original Loan
                                        </div>

                                        <div class="mfs-loan-stat-value">
                                            KES
                                            {{ number_format($loan->loan_amount, 2) }}
                                        </div>

                                    </div>


                                    <div class="mfs-loan-stat">

                                        <div class="mfs-loan-stat-label">
                                            Principal Paid / Adjustment
                                        </div>

                                        <div
                                            class="mfs-loan-stat-value
                                        {{ $principalPaid < 0 ? 'is-negative' : '' }}">
                                            KES
                                            {{ number_format($principalPaid, 2) }}
                                        </div>

                                    </div>


                                    <div class="mfs-loan-stat">

                                        <div class="mfs-loan-stat-label">
                                            Outstanding Principal
                                        </div>

                                        <div class="mfs-loan-stat-value">
                                            KES
                                            {{ number_format($loanBalance, 2) }}
                                        </div>

                                    </div>


                                    <div class="mfs-loan-stat">

                                        <div class="mfs-loan-stat-label">
                                            {{ $loanDate['label'] }}
                                        </div>

                                        <div class="mfs-loan-stat-value">
                                            {{ $loanDate['value'] }}
                                        </div>

                                    </div>


                                    <div class="mfs-loan-stat">

                                        <div class="mfs-loan-stat-label">
                                            Commission
                                        </div>

                                        <div class="mfs-loan-stat-value">
                                            KES
                                            {{ number_format($loan->loan_commision ?? 0, 2) }}
                                        </div>

                                    </div>


                                    <div class="mfs-loan-stat">

                                        <div class="mfs-loan-stat-label">
                                            Insurance
                                        </div>

                                        <div class="mfs-loan-stat-value">
                                            KES
                                            {{ number_format($loan->loan_insurance ?? 0, 2) }}
                                        </div>

                                    </div>

                                </div>


                                @if (!empty($loan->loan_description))
                                    <div class="mfs-loan-description">

                                        <strong>
                                            Description:
                                        </strong>

                                        {{ $loan->loan_description }}

                                    </div>
                                @endif

                            </div>


                            {{-- GUARANTORS --}}

                            <div class="mfs-guarantee-area">

                                <div class="mfs-guarantee-head">

                                    <div class="mfs-guarantee-title">
                                        Guarantors / Security
                                    </div>


                                    <div class="mfs-guarantee-total">

                                        Active guarantee secured:

                                        <strong>
                                            KES
                                            {{ number_format($activeGuarantee, 2) }}
                                        </strong>

                                    </div>

                                </div>


                                @if (collect($loan->guarantors ?? [])->count())
                                    <div class="mfs-table-wrap">

                                        <table class="table mfs-table">

                                            <thead>
                                                <tr>

                                                    <th>
                                                        #
                                                    </th>

                                                    <th>
                                                        Guarantor
                                                    </th>

                                                    <th>
                                                        SACCO No.
                                                    </th>

                                                    <th class="mfs-money">
                                                        Guaranteed
                                                    </th>

                                                    <th class="mfs-money">
                                                        Freed
                                                    </th>

                                                    <th class="mfs-money">
                                                        Currently Tied
                                                    </th>

                                                    @if ($showHyperlinks)
                                                        <th class="mfs-admin-only">
                                                            Action
                                                        </th>
                                                    @endif

                                                </tr>
                                            </thead>


                                            <tbody>

                                                @foreach ($loan->guarantors as $guarantor)
                                                    @php
                                                        $guarantorTied = max(
                                                            0,
                                                            (float) $guarantor->loan_guar_amount_guaranteed -
                                                                (float) $guarantor->loan_guar_amount_freed,
                                                        );
                                                    @endphp

                                                    <tr>

                                                        <td>
                                                            {{ $loop->iteration }}
                                                        </td>


                                                        <td>

                                                            @if ($showHyperlinks)
                                                                <a class="mfs-member-link"
                                                                    href="{{ route('changeGuarantors', [
                                                                        'member_id' => $guarantor->member_id,
                                                                    
                                                                        'guarantor_id' => $guarantor->loan_guar_id,
                                                                    ]) }}">
                                                                    {{ $guarantor->member_name }}
                                                                </a>
                                                            @else
                                                                {{ $guarantor->member_name }}
                                                            @endif

                                                        </td>


                                                        <td class="mfs-nowrap">
                                                            {{ $guarantor->member_sacco_id }}
                                                        </td>


                                                        <td class="mfs-money">
                                                            {{ number_format($guarantor->loan_guar_amount_guaranteed, 2) }}
                                                        </td>


                                                        <td class="mfs-money">
                                                            {{ number_format($guarantor->loan_guar_amount_freed, 2) }}
                                                        </td>


                                                        <td class="mfs-money">
                                                            {{ number_format($guarantorTied, 2) }}
                                                        </td>


                                                        @if ($showHyperlinks)
                                                            <td class="mfs-admin-only">

                                                                <form
                                                                    action="{{ route('deleteGuarantor', [
                                                                        'member_id' => $member->member_id,
                                                                    
                                                                        'guarantor_id' => $guarantor->loan_guar_id,
                                                                    ]) }}"
                                                                    method="POST"
                                                                    onsubmit="
                                                                    return confirm(
                                                                        'Are you sure you want to delete this guarantor?'
                                                                    );
                                                                ">

                                                                    @csrf
                                                                    @method('DELETE')

                                                                    <button type="submit"
                                                                        class="btn btn-outline-danger btn-sm"
                                                                        title="Remove guarantor">
                                                                        <i class="i-Close-Window"></i>
                                                                    </button>

                                                                </form>

                                                            </td>
                                                        @endif

                                                    </tr>
                                                @endforeach

                                            </tbody>

                                        </table>

                                    </div>
                                @else
                                    <div class="mfs-empty">
                                        No guarantors are currently attached to this loan.
                                    </div>
                                @endif

                            </div>

                        </article>
                    @endforeach
                @else
                    <div class="mfs-empty">
                        This member does not currently have an outstanding loan.
                    </div>
                @endif

            </section>


            {{-- ============================================================
             SECTION 3 — GUARANTEES PROVIDED
             ============================================================ --}}

            <section class="mfs-section">

                <div class="mfs-section-head">

                    <span class="mfs-section-number">
                        03
                    </span>

                    <h2 class="mfs-section-title">
                        Guarantees Provided by This Member
                    </h2>

                    <div class="mfs-section-description">
                        Outstanding loan obligations for which this member's
                        savings are currently pledged as security.
                    </div>

                </div>


                @if ($activeGuaranteesProvided->isNotEmpty())
                    <div class="mfs-table-wrap">

                        <table class="table mfs-table">

                            <thead>
                                <tr>

                                    <th>
                                        #
                                    </th>

                                    <th>
                                        Borrower
                                    </th>

                                    <th>
                                        Loan Type
                                    </th>

                                    <th>
                                        Loan Date / Period
                                    </th>

                                    <th class="mfs-money">
                                        Original Loan
                                    </th>

                                    <th class="mfs-money">
                                        Loan Outstanding
                                    </th>

                                    <th class="mfs-money">
                                        Guaranteed
                                    </th>

                                    <th class="mfs-money">
                                        Freed
                                    </th>

                                    <th class="mfs-money">
                                        Currently Tied
                                    </th>

                                </tr>
                            </thead>


                            <tbody>

                                @foreach ($activeGuaranteesProvided as $loan)
                                    @php
                                        $guaranteeTied = max(
                                            0,
                                            (float) $loan->loan_guar_amount_guaranteed -
                                                (float) $loan->loan_guar_amount_freed,
                                        );

                                        $borrowerLoanBalance =
                                            (float) $loan->loan_amount - (float) $loan->loan_loan_paid;

                                        $guaranteedLoanDate = $loanDateDisplay($loan);
                                    @endphp


                                    <tr>

                                        <td>
                                            {{ $loop->iteration }}
                                        </td>


                                        <td>
                                            <strong>
                                                {{ $loan->member_name }}
                                            </strong>

                                            @if (!empty($loan->member_sacco_id))
                                                <div class="text-muted small">
                                                    Member No.
                                                    {{ $loan->member_sacco_id }}
                                                </div>
                                            @endif
                                        </td>


                                        <td>
                                            {{ $loan->loan_type_name }}
                                        </td>


                                        <td class="mfs-nowrap">
                                            {{ $guaranteedLoanDate['value'] }}
                                        </td>


                                        <td class="mfs-money">
                                            {{ number_format($loan->loan_amount, 2) }}
                                        </td>


                                        <td class="mfs-money">
                                            {{ number_format($borrowerLoanBalance, 2) }}
                                        </td>


                                        <td class="mfs-money">
                                            {{ number_format($loan->loan_guar_amount_guaranteed, 2) }}
                                        </td>


                                        <td class="mfs-money">
                                            {{ number_format($loan->loan_guar_amount_freed, 2) }}
                                        </td>


                                        <td class="mfs-money">
                                            <strong>
                                                {{ number_format($guaranteeTied, 2) }}
                                            </strong>
                                        </td>

                                    </tr>
                                @endforeach

                            </tbody>

                        </table>

                    </div>
                @else
                    <div class="mfs-empty">
                        This member is not currently guaranteeing an outstanding loan.
                    </div>
                @endif

            </section>


            {{-- ============================================================
             DOCUMENT FOOTER
             ============================================================ --}}

            <footer class="mfs-footer">

                <div class="mfs-footer-main">

                    <div>
                        <strong>
                            {{ $saccoName }} — Member Financial Status Statement
                        </strong>
                    </div>


                    <div>
                        Statement Ref:
                        <strong>
                            {{ $statementReference }}
                        </strong>
                    </div>

                </div>


                <div class="mfs-footer-note">

                    Generated on
                    {{ $statementGeneratedAt->format('d M Y H:i:s') }}.

                    Financial balances shown reflect the current records in
                    the SACCO system at the time this statement was generated.

                    This is a system-generated financial statement.

                </div>

            </footer>

        </div>

    </div>


    {{-- ====================================================================
     EXISTING WORKING GUARANTOR MODAL
     ==================================================================== --}}

    @if ($showHyperlinks)
        @include('guarantors.partials.add-existing-loan-modal')
    @endif


    {{-- ====================================================================
     PDF LIBRARIES
     Same mechanism already used by Member Statement
     ==================================================================== --}}

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            'use strict';

            const printable =
                document.getElementById(
                    'memberFinancialStatusPrintable'
                );

            const printButton =
                document.getElementById(
                    'printMemberFinancialStatus'
                );

            const pdfButton =
                document.getElementById(
                    'downloadMemberFinancialStatusPdf'
                );

            const pdfLoader =
                document.getElementById(
                    'memberFinancialStatusPdfLoader'
                );


            /*
            |--------------------------------------------------------------------------
            | PRINT
            |--------------------------------------------------------------------------
            */

            if (printButton) {

                printButton.addEventListener(
                    'click',
                    function() {
                        window.print();
                    }
                );

            }


            /*
            |--------------------------------------------------------------------------
            | PDF
            |--------------------------------------------------------------------------
            */

            if (!pdfButton || !printable) {
                return;
            }


            /*
            |--------------------------------------------------------------------------
            | Find elements that should preferably remain together
            |--------------------------------------------------------------------------
            |
            | The PDF generator uses these DOM positions to avoid cutting through:
            |
            | - section headings
            | - financial summary
            | - member details
            | - loan summary/details
            | - guarantee headings
            | - individual table rows
            |
            | A very large element which is taller than a whole PDF page is still
            | allowed to split, otherwise PDF generation could become impossible.
            |
            */

            function getKeepTogetherRanges(canvas, maxPageHeightPx) {

                const rootRect =
                    printable.getBoundingClientRect();

                const renderedHeight =
                    printable.scrollHeight;

                const canvasScaleY =
                    canvas.height / renderedHeight;


                const selectors = [
                    '.mfs-institution-row',
                    '.mfs-member-panel',
                    '.mfs-section-head',
                    '.mfs-position-groups',
                    '.mfs-loan-overview',
                    '.mfs-guarantee-head',
                    '.mfs-table thead',
                    '.mfs-table tbody tr'
                ];


                const ranges = [];


                selectors.forEach(function(selector) {

                    printable
                        .querySelectorAll(selector)
                        .forEach(function(element) {

                            const rect =
                                element.getBoundingClientRect();


                            const top =
                                (
                                    rect.top -
                                    rootRect.top
                                ) *
                                canvasScaleY;


                            const bottom =
                                (
                                    rect.bottom -
                                    rootRect.top
                                ) *
                                canvasScaleY;


                            const height =
                                bottom - top;


                            /*
                             * Only protect blocks which can reasonably fit on
                             * one PDF page.
                             */
                            if (
                                height > 0 &&
                                height <
                                (
                                    maxPageHeightPx *
                                    0.92
                                )
                            ) {

                                ranges.push({
                                    top: Math.max(
                                        0,
                                        top
                                    ),

                                    bottom: Math.min(
                                        canvas.height,
                                        bottom
                                    )
                                });

                            }

                        });

                });


                /*
                 * Sort from the top of the document downward.
                 */
                ranges.sort(function(a, b) {
                    return a.top - b.top;
                });


                return ranges;
            }


            /*
            |--------------------------------------------------------------------------
            | Calculate safest page ending
            |--------------------------------------------------------------------------
            */

            function findSafePageEnd(
                startY,
                proposedEndY,
                keepRanges,
                canvasHeight,
                targetPageHeight
            ) {

                if (
                    proposedEndY >= canvasHeight
                ) {
                    return canvasHeight;
                }


                let safeEnd =
                    proposedEndY;


                /*
                 * Is our proposed page break cutting directly through a protected
                 * element?
                 */
                for (
                    let i = 0; i < keepRanges.length; i++
                ) {

                    const range =
                        keepRanges[i];


                    if (
                        range.top <
                        proposedEndY &&
                        range.bottom >
                        proposedEndY
                    ) {

                        /*
                         * If enough content has already been placed on this page,
                         * move the whole protected element to the next page.
                         */
                        const contentAlreadyUsed =
                            range.top -
                            startY;


                        if (
                            contentAlreadyUsed >
                            targetPageHeight *
                            0.20
                        ) {

                            safeEnd =
                                range.top;

                        }

                        break;
                    }

                }


                /*
                 * Prevent an accidental zero-height/tiny page.
                 */
                const minimumUsefulPage =
                    targetPageHeight *
                    0.15;


                if (
                    safeEnd -
                    startY <
                    minimumUsefulPage
                ) {

                    safeEnd =
                        proposedEndY;

                }


                return Math.min(
                    safeEnd,
                    canvasHeight
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Create cropped canvas for ONE PDF page
            |--------------------------------------------------------------------------
            */

            function createPageCanvas(
                sourceCanvas,
                startY,
                endY
            ) {

                const sliceHeight =
                    Math.max(
                        1,
                        Math.round(
                            endY - startY
                        )
                    );


                const pageCanvas =
                    document.createElement(
                        'canvas'
                    );


                pageCanvas.width =
                    sourceCanvas.width;

                pageCanvas.height =
                    sliceHeight;


                const context =
                    pageCanvas.getContext(
                        '2d'
                    );


                /*
                 * White page background.
                 */
                context.fillStyle =
                    '#ffffff';

                context.fillRect(
                    0,
                    0,
                    pageCanvas.width,
                    pageCanvas.height
                );


                context.drawImage(
                    sourceCanvas,

                    0,
                    Math.round(startY),

                    sourceCanvas.width,
                    sliceHeight,

                    0,
                    0,

                    sourceCanvas.width,
                    sliceHeight
                );


                return pageCanvas;
            }


            /*
            |--------------------------------------------------------------------------
            | Add page footer
            |--------------------------------------------------------------------------
            */

            function addPageFooter(
                pdf,
                pageNumber,
                totalPages,
                saccoName,
                statementReference,
                pageWidth,
                pageHeight,
                marginLeft,
                marginRight
            ) {

                pdf.setDrawColor(
                    185,
                    185,
                    185
                );


                pdf.line(
                    marginLeft,
                    pageHeight - 7,
                    pageWidth - marginRight,
                    pageHeight - 7
                );


                pdf.setFontSize(
                    7
                );


                pdf.setTextColor(
                    90,
                    90,
                    90
                );


                pdf.text(
                    saccoName +
                    '  |  ' +
                    statementReference,
                    marginLeft,
                    pageHeight - 3.5
                );


                pdf.text(
                    'Page ' +
                    pageNumber +
                    ' of ' +
                    totalPages,
                    pageWidth - marginRight,
                    pageHeight - 3.5, {
                        align: 'right'
                    }
                );

            }


            /*
            |--------------------------------------------------------------------------
            | DOWNLOAD PDF
            |--------------------------------------------------------------------------
            */

            pdfButton.addEventListener(
                'click',
                async function() {

                    if (
                        !window.jspdf ||
                        typeof window.jspdf.jsPDF !==
                        'function' ||
                        typeof window.html2canvas !==
                        'function'
                    ) {

                        alert(
                            'PDF tools did not load. Please refresh the page and try again.'
                        );

                        return;
                    }


                    const {
                        jsPDF
                    } =
                    window.jspdf;


                    pdfButton.disabled =
                        true;


                    if (printButton) {
                        printButton.disabled =
                            true;
                    }


                    if (pdfLoader) {

                        pdfLoader.classList.remove(
                            'd-none'
                        );

                    }


                    /*
                     * Apply desktop/document layout before capture.
                     */
                    printable.classList.add(
                        'mfs-export-mode'
                    );


                    try {

                        /*
                         * Give browser two layout frames to settle.
                         */
                        await new Promise(
                            function(resolve) {

                                requestAnimationFrame(
                                    function() {

                                        requestAnimationFrame(
                                            resolve
                                        );

                                    }
                                );

                            }
                        );


                        /*
                         * ---------------------------------------------------------
                         * CAPTURE FULL STATEMENT
                         * ---------------------------------------------------------
                         */

                        const canvas =
                            await window.html2canvas(
                                printable, {
                                    scale: 2,

                                    useCORS: true,

                                    allowTaint: false,

                                    backgroundColor: '#ffffff',

                                    logging: false,

                                    width: printable.scrollWidth,

                                    height: printable.scrollHeight,

                                    windowWidth: printable.scrollWidth,

                                    scrollX: 0,

                                    scrollY: -window.scrollY
                                }
                            );


                        /*
                         * ---------------------------------------------------------
                         * A4 LANDSCAPE
                         * ---------------------------------------------------------
                         */

                        const pdf =
                            new jsPDF(
                                'l',
                                'mm',
                                'a4'
                            );


                        const pageWidth =
                            297;

                        const pageHeight =
                            210;


                        /*
                         * Clean banking-document margins.
                         */
                        const marginLeft =
                            7;

                        const marginRight =
                            7;

                        const marginTop =
                            7;


                        /*
                         * Reserve this area exclusively for:
                         *
                         * separator
                         * reference
                         * page number
                         *
                         * Statement content can NEVER enter this zone.
                         */
                        const footerReserve =
                            12;


                        const usableWidth =
                            pageWidth -
                            marginLeft -
                            marginRight;


                        const usableHeight =
                            pageHeight -
                            marginTop -
                            footerReserve;


                        /*
                         * ---------------------------------------------------------
                         * Convert PDF dimensions into canvas pixels
                         * ---------------------------------------------------------
                         *
                         * The entire canvas width is scaled to usableWidth mm.
                         */

                        const pixelsPerMm =
                            canvas.width /
                            usableWidth;


                        const targetPageHeightPx =
                            usableHeight *
                            pixelsPerMm;


                        /*
                         * Find DOM elements which should not be broken.
                         */
                        const keepRanges =
                            getKeepTogetherRanges(
                                canvas,
                                targetPageHeightPx
                            );


                        /*
                         * ---------------------------------------------------------
                         * BUILD LOGICAL PAGES
                         * ---------------------------------------------------------
                         */

                        const pages = [];

                        let startY =
                            0;


                        while (
                            startY <
                            canvas.height - 1
                        ) {

                            let proposedEndY =
                                Math.min(
                                    startY +
                                    targetPageHeightPx,

                                    canvas.height
                                );


                            const endY =
                                findSafePageEnd(
                                    startY,
                                    proposedEndY,
                                    keepRanges,
                                    canvas.height,
                                    targetPageHeightPx
                                );


                            /*
                             * Ultimate safety check.
                             */
                            if (
                                endY <=
                                startY
                            ) {

                                proposedEndY =
                                    Math.min(
                                        startY +
                                        targetPageHeightPx,

                                        canvas.height
                                    );


                                pages.push({
                                    start: startY,

                                    end: proposedEndY
                                });


                                startY =
                                    proposedEndY;

                                continue;
                            }


                            pages.push({
                                start: startY,

                                end: endY
                            });


                            startY =
                                endY;

                        }


                        /*
                         * ---------------------------------------------------------
                         * RENDER EACH PAGE AS ITS OWN IMAGE
                         * ---------------------------------------------------------
                         *
                         * This is the critical improvement.
                         *
                         * We are NOT inserting the original huge image repeatedly
                         * with increasingly negative positions.
                         *
                         * Every PDF page gets an independent cropped image.
                         */

                        pages.forEach(
                            function(
                                page,
                                index
                            ) {

                                if (index > 0) {
                                    pdf.addPage();
                                }


                                const pageCanvas =
                                    createPageCanvas(
                                        canvas,
                                        page.start,
                                        page.end
                                    );


                                const pageImage =
                                    pageCanvas.toDataURL(
                                        'image/jpeg',
                                        0.92
                                    );


                                const pageImageHeightMm =
                                    pageCanvas.height /
                                    pixelsPerMm;


                                pdf.addImage(
                                    pageImage,
                                    'JPEG',

                                    marginLeft,
                                    marginTop,

                                    usableWidth,
                                    pageImageHeightMm,

                                    undefined,
                                    'FAST'
                                );

                            }
                        );


                        /*
 * ---------------------------------------------------------
 * FOOTERS
 * ---------------------------------------------------------
 */

const saccoName =
    @json($saccoName);

const statementReference =
    @json($statementReference);

const totalPages =
    pdf.getNumberOfPages();


for (
    let pageNumber = 1;
    pageNumber <= totalPages;
    pageNumber++
) {

    pdf.setPage(
        pageNumber
    );


    addPageFooter(
        pdf,
        pageNumber,
        totalPages,
        saccoName,
        statementReference,
        pageWidth,
        pageHeight,
        marginLeft,
        marginRight
    );

}


                        /*
                         * ---------------------------------------------------------
                         * FILE NAME
                         * ---------------------------------------------------------
                         */

                        const memberName =
                            @json($member->member_name);


                        const memberNo =
                            @json($member->member_sacco_id);


                        const safeName =
                            String(
                                memberName ||
                                'member'
                            )
                            .replace(
                                /[^a-z0-9]+/gi,
                                '_'
                            )
                            .replace(
                                /^_+|_+$/g,
                                ''
                            )
                            .toLowerCase();


                        const safeMemberNo =
                            String(
                                memberNo ||
                                ''
                            )
                            .replace(
                                /[^a-z0-9_-]+/gi,
                                ''
                            );


                        const fileName =
                            safeName +
                            (
                                safeMemberNo ?
                                '_' +
                                safeMemberNo :
                                ''
                            ) +
                            '_financial_status_statement.pdf';


                        pdf.save(
                            fileName
                        );


                    } catch (error) {

                        console.error(
                            'Member financial status PDF generation failed:',
                            error
                        );


                        alert(
                            'The financial statement PDF could not be generated. Please refresh the page and try again.'
                        );

                    } finally {

                        printable.classList.remove(
                            'mfs-export-mode'
                        );


                        pdfButton.disabled =
                            false;


                        if (printButton) {
                            printButton.disabled =
                                false;
                        }


                        if (pdfLoader) {

                            pdfLoader.classList.add(
                                'd-none'
                            );

                        }

                    }

                }
            );

        });
    </script>

@endsection
