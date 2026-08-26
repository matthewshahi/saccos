@extends('layouts.app')

@section('content')

@include('member_name')

<style>
    .member-statement {
        --statement-border: #e3e6ea;
        --statement-muted: #69707a;
        --statement-bg: #f7f8fa;
        --statement-heading: #222;
        --statement-accent: #663399;
        max-width: 1400px;
        margin: 0 auto;
    }

    .member-statement .statement-sheet {
        background: #fff;
        border: 1px solid var(--statement-border);
        box-shadow: 0 2px 8px rgba(0, 0, 0, .04);
    }

    .member-statement .statement-header {
        padding: 24px 26px 20px;
        border-bottom: 2px solid var(--statement-accent);
    }

    .member-statement .statement-title {
        margin: 0;
        font-size: 23px;
        line-height: 1.2;
        font-weight: 800;
        color: var(--statement-heading);
    }

    .member-statement .statement-subtitle {
        margin-top: 5px;
        color: var(--statement-muted);
        font-size: 13px;
        text-transform: uppercase;
        letter-spacing: .05em;
    }

    .member-statement .member-name {
        margin-top: 18px;
        margin-bottom: 4px;
        font-size: 20px;
        font-weight: 800;
    }

    .member-statement .member-meta {
        display: flex;
        flex-wrap: wrap;
        gap: 8px 24px;
        color: var(--statement-muted);
        font-size: 13px;
    }

    .member-statement .statement-section {
        padding: 22px 26px;
        border-bottom: 1px solid var(--statement-border);
    }

    .member-statement .statement-section:last-child {
        border-bottom: 0;
    }

    .member-statement .section-heading {
        margin: 0 0 15px;
        font-size: 15px;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .045em;
        color: #343a40;
    }

    .member-statement .summary-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        border: 1px solid var(--statement-border);
        background: #fff;
    }

    .member-statement .summary-item {
        min-width: 0;
        padding: 16px 18px;
        border-right: 1px solid var(--statement-border);
        border-bottom: 1px solid var(--statement-border);
    }

    .member-statement .summary-item:nth-child(3n) {
        border-right: 0;
    }

    .member-statement .summary-item:nth-last-child(-n+3) {
        border-bottom: 0;
    }

    .member-statement .summary-label {
        color: var(--statement-muted);
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .03em;
    }

    .member-statement .summary-value {
        margin-top: 5px;
        font-size: 18px;
        line-height: 1.25;
        font-weight: 800;
        color: #212529;
    }

    .member-statement .loan-block {
        margin-bottom: 22px;
        border: 1px solid var(--statement-border);
    }

    .member-statement .loan-block:last-child {
        margin-bottom: 0;
    }

    .member-statement .loan-heading {
        display: flex;
        justify-content: space-between;
        gap: 20px;
        align-items: flex-start;
        padding: 15px 18px;
        background: var(--statement-bg);
        border-bottom: 1px solid var(--statement-border);
    }

    .member-statement .loan-name {
        font-size: 16px;
        font-weight: 800;
        color: #252525;
    }

    .member-statement .loan-number {
        margin-top: 3px;
        color: var(--statement-muted);
        font-size: 12px;
    }

    .member-statement .loan-actions {
        flex: 0 0 auto;
    }

    .member-statement .loan-details {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        border-bottom: 1px solid var(--statement-border);
    }

    .member-statement .loan-detail {
        padding: 14px 18px;
        border-right: 1px solid var(--statement-border);
        border-bottom: 1px solid var(--statement-border);
    }

    .member-statement .loan-detail:nth-child(3n) {
        border-right: 0;
    }

    .member-statement .loan-detail-label {
        color: var(--statement-muted);
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
    }

    .member-statement .loan-detail-value {
        margin-top: 4px;
        font-size: 14px;
        font-weight: 700;
        color: #252525;
    }

    .member-statement .loan-description {
        padding: 13px 18px;
        background: #fff;
        border-bottom: 1px solid var(--statement-border);
        color: #444;
        font-size: 13px;
    }

    .member-statement .loan-description strong {
        color: #222;
    }

    .member-statement .guarantee-wrap {
        padding: 17px 18px;
    }

    .member-statement .guarantee-heading {
        margin-bottom: 10px;
        font-size: 13px;
        font-weight: 800;
        text-transform: uppercase;
    }

    .member-statement table {
        margin-bottom: 0;
        font-size: 13px;
    }

    .member-statement table thead th {
        background: #f7f8fa;
        border-bottom-width: 1px;
        white-space: nowrap;
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: .025em;
        color: #565d65;
    }

    .member-statement .money {
        white-space: nowrap;
        text-align: right;
        font-variant-numeric: tabular-nums;
    }

    .member-statement .empty-state {
        padding: 22px;
        border: 1px dashed #ccd1d6;
        color: var(--statement-muted);
        text-align: center;
        font-size: 13px;
        background: #fafafa;
    }

    .member-statement .statement-footer {
        padding: 14px 26px;
        color: var(--statement-muted);
        background: #fafafa;
        border-top: 1px solid var(--statement-border);
        font-size: 11px;
    }

    @media (max-width: 767.98px) {
        .member-statement .statement-header,
        .member-statement .statement-section {
            padding-left: 14px;
            padding-right: 14px;
        }

        .member-statement .summary-grid,
        .member-statement .loan-details {
            grid-template-columns: 1fr 1fr;
        }

        .member-statement .summary-item,
        .member-statement .summary-item:nth-child(3n),
        .member-statement .loan-detail,
        .member-statement .loan-detail:nth-child(3n) {
            border-right: 1px solid var(--statement-border);
            border-bottom: 1px solid var(--statement-border);
        }

        .member-statement .summary-item:nth-child(2n),
        .member-statement .loan-detail:nth-child(2n) {
            border-right: 0;
        }

        .member-statement .loan-heading {
            display: block;
        }

        .member-statement .loan-actions {
            margin-top: 12px;
        }

        .member-statement .loan-actions .btn {
            width: 100%;
        }
    }

    @media (max-width: 480px) {
        .member-statement .summary-grid,
        .member-statement .loan-details {
            grid-template-columns: 1fr;
        }

        .member-statement .summary-item,
        .member-statement .summary-item:nth-child(2n),
        .member-statement .summary-item:nth-child(3n),
        .member-statement .loan-detail,
        .member-statement .loan-detail:nth-child(2n),
        .member-statement .loan-detail:nth-child(3n) {
            border-right: 0;
        }

        .member-statement .summary-value {
            font-size: 17px;
        }
    }

    @media print {
        .sidebar-panel,
        .main-header,
        footer,
        .loan-actions,
        .btn {
            display: none !important;
        }

        .main-content-wrap {
            margin: 0 !important;
            padding: 0 !important;
        }

        .member-statement {
            max-width: none;
        }

        .member-statement .statement-sheet {
            border: 0;
            box-shadow: none;
        }
    }
</style>


<div class="member-statement">

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            @foreach($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif


    <div class="statement-sheet">

        {{-- REPORT HEADER --}}
        <div class="statement-header">

            <div class="statement-title">
                Member Financial Status Statement
            </div>

            <div class="statement-subtitle">
                Savings, Loans & Guarantee Exposure
            </div>

            <div class="member-name">
                {{ $member->member_name }}
            </div>

            <div class="member-meta">
                <span>
                    <strong>Member No:</strong>
                    {{ $member->member_sacco_id }}
                </span>

                <span>
                    <strong>Company:</strong>
                    {{ $member->company_name }}
                </span>

                <span>
                    <strong>Department:</strong>
                    {{ $member->department_name }}
                </span>

                <span>
                    <strong>Generated:</strong>
                    {{ now()->format('d/m/Y H:i') }}
                </span>
            </div>
        </div>


        {{-- FINANCIAL SUMMARY --}}
        <section class="statement-section">

            <h2 class="section-heading">
                Financial Summary
            </h2>

            <div class="summary-grid">

                <div class="summary-item">
                    <div class="summary-label">
                        Savings / Deposits
                    </div>

                    <div class="summary-value">
                        KES {{ number_format(
                            $memberFinancials['total_share_deposit'],
                            2
                        ) }}
                    </div>
                </div>

                <div class="summary-item">
                    <div class="summary-label">
                        Share Capital
                    </div>

                    <div class="summary-value">
                        KES {{ number_format(
                            $memberFinancials['total_capital_shares'],
                            2
                        ) }}
                    </div>
                </div>

                <div class="summary-item">
                    <div class="summary-label">
                        FOSA Deposits
                    </div>

                    <div class="summary-value">
                        KES {{ number_format(
                            $memberFinancials['total_fosa_deposits'],
                            2
                        ) }}
                    </div>
                </div>

                <div class="summary-item">
                    <div class="summary-label">
                        Outstanding Loan Principal
                    </div>

                    <div class="summary-value">
                        KES {{ number_format(
                            $memberFinancials['unpaid_loan'],
                            2
                        ) }}
                    </div>
                </div>

                <div class="summary-item">
                    <div class="summary-label">
                        Savings Tied — Others
                    </div>

                    <div class="summary-value">
                        KES {{ number_format(
                            $memberFinancials['tied_shares_others'],
                            2
                        ) }}
                    </div>
                </div>

                <div class="summary-item">
                    <div class="summary-label">
                        Savings Tied — Self
                    </div>

                    <div class="summary-value">
                        KES {{ number_format(
                            $memberFinancials['tied_shares_self'],
                            2
                        ) }}
                    </div>
                </div>

            </div>

        </section>


        {{-- LOANS --}}
        <section class="statement-section">

            <h2 class="section-heading">
                Loans Taken & Guarantee Position
            </h2>

            @forelse($loansTakenWithGuarantors as $loan)

                @php
                    $loanBalance =
                        (float) $loan->loan_amount
                        -
                        (float) ($loan->loan_loan_paid ?? 0);

                    $activeGuarantee =
                        $loan->guarantors->sum(function ($guarantor) {
                            return max(
                                0,
                                (float) $guarantor->loan_guar_amount_guaranteed
                                -
                                (float) $guarantor->loan_guar_amount_freed
                            );
                        });
                @endphp

                @if($loanBalance > $threshold_amount)

                    <article class="loan-block">

                        <div class="loan-heading">

                            <div>
                                <div class="loan-name">
                                    {{ $loan->loan_type_name }}
                                </div>

                                <div class="loan-number">
                                    Loan No. {{ $loan->loan_id }}
                                </div>
                            </div>

                            @if($showHyperlinks)
                                <div class="loan-actions">

                                    <button
                                        type="button"
                                        class="btn btn-primary btn-sm js-add-guarantor"
                                        data-loan-id="{{ $loan->loan_id }}"
                                    >
                                        <i class="i-Add-User me-1"></i>
                                        Add Guarantor
                                    </button>

                                </div>
                            @endif

                        </div>


                        <div class="loan-details">

                            <div class="loan-detail">
                                <div class="loan-detail-label">
                                    Original Loan
                                </div>

                                <div class="loan-detail-value">
                                    KES {{ number_format(
                                        $loan->loan_amount,
                                        2
                                    ) }}
                                </div>
                            </div>


                            <div class="loan-detail">
                                <div class="loan-detail-label">
                                    Principal Paid
                                </div>

                                <div class="loan-detail-value">
                                    KES {{ number_format(
                                        $loan->loan_loan_paid,
                                        2
                                    ) }}
                                </div>
                            </div>


                            <div class="loan-detail">
                                <div class="loan-detail-label">
                                    Outstanding Balance
                                </div>

                                <div class="loan-detail-value">
                                    KES {{ number_format(
                                        $loanBalance,
                                        2
                                    ) }}
                                </div>
                            </div>


                            <div class="loan-detail">
                                <div class="loan-detail-label">
                                    Period Taken
                                </div>

                                <div class="loan-detail-value">
                                    {{ $loan->loan_taken_period }}
                                </div>
                            </div>


                            <div class="loan-detail">
                                <div class="loan-detail-label">
                                    Commission
                                </div>

                                <div class="loan-detail-value">
                                    KES {{ number_format(
                                        $loan->loan_commision,
                                        2
                                    ) }}
                                </div>
                            </div>


                            <div class="loan-detail">
                                <div class="loan-detail-label">
                                    Insurance
                                </div>

                                <div class="loan-detail-value">
                                    KES {{ number_format(
                                        $loan->loan_insurance,
                                        2
                                    ) }}
                                </div>
                            </div>

                        </div>


                        @if(!empty($loan->loan_description))
                            <div class="loan-description">
                                <strong>Description:</strong>
                                {{ $loan->loan_description }}
                            </div>
                        @endif


                        <div class="guarantee-wrap">

                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">

                                <div class="guarantee-heading mb-0">
                                    Guarantors / Security
                                </div>

                                <div class="small text-muted">
                                    Active guarantee:
                                    <strong>
                                        KES {{ number_format(
                                            $activeGuarantee,
                                            2
                                        ) }}
                                    </strong>
                                </div>

                            </div>


                            @if($loan->guarantors->count())

                                <div class="table-responsive">

                                    <table class="table table-bordered align-middle">

                                        <thead>
                                            <tr>
                                                <th>#</th>
                                                <th>Guarantor</th>
                                                <th>SACCO No.</th>
                                                <th class="text-end">
                                                    Guaranteed
                                                </th>
                                                <th class="text-end">
                                                    Freed
                                                </th>
                                                <th class="text-end">
                                                    Currently Tied
                                                </th>

                                                @if($showHyperlinks)
                                                    <th>
                                                        Action
                                                    </th>
                                                @endif
                                            </tr>
                                        </thead>

                                        <tbody>

                                            @foreach(
                                                $loan->guarantors
                                                as $guarantor
                                            )

                                                <tr>

                                                    <td>
                                                        {{ $loop->iteration }}
                                                    </td>

                                                    <td>
                                                        @if($showHyperlinks)

                                                            <a href="{{
                                                                route(
                                                                    'changeGuarantors',
                                                                    [
                                                                        'member_id' =>
                                                                            $guarantor->member_id,

                                                                        'guarantor_id' =>
                                                                            $guarantor->loan_guar_id
                                                                    ]
                                                                )
                                                            }}">
                                                                {{
                                                                    $guarantor
                                                                    ->member_name
                                                                }}
                                                            </a>

                                                        @else

                                                            {{
                                                                $guarantor
                                                                ->member_name
                                                            }}

                                                        @endif
                                                    </td>

                                                    <td>
                                                        {{
                                                            $guarantor
                                                            ->member_sacco_id
                                                        }}
                                                    </td>

                                                    <td class="money">
                                                        {{
                                                            number_format(
                                                                $guarantor
                                                                ->loan_guar_amount_guaranteed,
                                                                2
                                                            )
                                                        }}
                                                    </td>

                                                    <td class="money">
                                                        {{
                                                            number_format(
                                                                $guarantor
                                                                ->loan_guar_amount_freed,
                                                                2
                                                            )
                                                        }}
                                                    </td>

                                                    <td class="money">
                                                        {{
                                                            number_format(
                                                                $guarantor
                                                                ->loan_guar_amount_guaranteed
                                                                -
                                                                $guarantor
                                                                ->loan_guar_amount_freed,
                                                                2
                                                            )
                                                        }}
                                                    </td>

                                                    @if($showHyperlinks)
                                                        <td>

                                                            <form
                                                                action="{{
                                                                    route(
                                                                        'deleteGuarantor',
                                                                        [
                                                                            'member_id' =>
                                                                                $member->member_id,

                                                                            'guarantor_id' =>
                                                                                $guarantor->loan_guar_id
                                                                        ]
                                                                    )
                                                                }}"
                                                                method="POST"
                                                                onsubmit="
                                                                    return confirm(
                                                                        'Are you sure you want to delete this guarantor?'
                                                                    );
                                                                "
                                                            >

                                                                @csrf
                                                                @method('DELETE')

                                                                <button
                                                                    type="submit"
                                                                    class="btn btn-outline-danger btn-sm"
                                                                    title="Remove guarantor"
                                                                >
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

                                <div class="empty-state">
                                    No guarantors are currently attached to this loan.
                                </div>

                            @endif

                        </div>

                    </article>

                @endif

            @empty

                <div class="empty-state">
                    No outstanding loans were found for this member.
                </div>

            @endforelse

        </section>


        {{-- LOANS GUARANTEED BY MEMBER --}}
        <section class="statement-section">

            <h2 class="section-heading">
                Loans Guaranteed by This Member
            </h2>

            @if($loansGuaranteed->count())

                <div class="table-responsive">

                    <table class="table table-bordered align-middle">

                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Borrower</th>
                                <th>Loan Type</th>
                                <th>Period</th>
                                <th class="text-end">
                                    Original Loan
                                </th>
                                <th class="text-end">
                                    Guaranteed
                                </th>
                                <th class="text-end">
                                    Freed
                                </th>
                                <th class="text-end">
                                    Currently Tied
                                </th>
                            </tr>
                        </thead>

                        <tbody>

                            @foreach($loansGuaranteed as $loan)

                                @php
                                    $guaranteeTied =
                                        (float) $loan
                                            ->loan_guar_amount_guaranteed
                                        -
                                        (float) $loan
                                            ->loan_guar_amount_freed;
                                @endphp

                                @if(
                                    (
                                        $loan->loan_amount
                                        -
                                        $loan->loan_loan_paid
                                    ) > $threshold_amount
                                    &&
                                    $guaranteeTied > $threshold_amount
                                )

                                    <tr>

                                        <td>
                                            {{ $loop->iteration }}
                                        </td>

                                        <td>
                                            {{ $loan->member_name }},
                                            {{ $loan->member_sacco_id }}
                                        </td>

                                        <td>
                                            {{ $loan->loan_type_name }}
                                        </td>

                                        <td>
                                            {{ $loan->loan_taken_period }}
                                        </td>

                                        <td class="money">
                                            {{
                                                number_format(
                                                    $loan->loan_amount,
                                                    2
                                                )
                                            }}
                                        </td>

                                        <td class="money">
                                            {{
                                                number_format(
                                                    $loan
                                                    ->loan_guar_amount_guaranteed,
                                                    2
                                                )
                                            }}
                                        </td>

                                        <td class="money">
                                            {{
                                                number_format(
                                                    $loan
                                                    ->loan_guar_amount_freed,
                                                    2
                                                )
                                            }}
                                        </td>

                                        <td class="money">
                                            {{
                                                number_format(
                                                    $guaranteeTied,
                                                    2
                                                )
                                            }}
                                        </td>

                                    </tr>

                                @endif

                            @endforeach

                        </tbody>

                    </table>

                </div>

            @else

                <div class="empty-state">
                    This member is not currently guaranteeing an outstanding loan.
                </div>

            @endif

        </section>


        <div class="statement-footer">

            Generated by KASS SACCO on
            {{ now()->format('d/m/Y H:i:s') }}.

            Financial balances shown are based on the current system records.

        </div>

    </div>

</div>


{{-- Existing-loan guarantor modal will sit here --}}
@if($showHyperlinks)
    @include('guarantors.partials.add-existing-loan-modal')
@endif

@endsection