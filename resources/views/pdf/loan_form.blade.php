<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <title>
        Loan Appraisal {{ $loan->batch_trans_id }}
    </title>

    <style>
        @page {
            margin: 16mm 12mm 18mm 12mm;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: "DejaVu Sans", sans-serif;
            font-size: 8.5px;
            line-height: 1.35;
            color: #222;
        }

        .header-table,
        .info-table,
        .data-table,
        .summary-table {
            width: 100%;
            border-collapse: collapse;
        }

        .header-table {
            margin-bottom: 8px;
        }

        .header-table td {
            vertical-align: top;
        }

        .company-name {
            font-size: 18px;
            font-weight: bold;
            color: #24344d;
            margin-bottom: 2px;
        }

        .document-title {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .sub-title {
            color: #666;
            margin-top: 2px;
        }

        .document-meta {
            text-align: right;
            font-size: 8px;
        }

        .copy-label {
            display: inline-block;
            margin-bottom: 4px;
            padding: 3px 7px;
            border: 1px solid #555;
            font-weight: bold;
            font-size: 7.5px;
        }

        .section {
            margin-top: 9px;
        }

        .section-title {
            padding: 5px 7px;
            background: #24344d;
            color: #fff;
            font-weight: bold;
            font-size: 9px;
            text-transform: uppercase;
        }

        .info-table,
        .data-table,
        .summary-table {
            margin-top: 0;
        }

        .info-table td,
        .data-table td,
        .data-table th,
        .summary-table td,
        .summary-table th {
            border: 1px solid #cfd4da;
            padding: 4px 5px;
            vertical-align: top;
        }

        .info-table .label {
            width: 17%;
            background: #f3f4f6;
            font-weight: bold;
            color: #444;
        }

        .info-table .value {
            width: 33%;
        }

        .data-table th,
        .summary-table th {
            background: #eef1f4;
            font-weight: bold;
            text-align: left;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .muted {
            color: #777;
        }

        .small {
            font-size: 7.5px;
        }

        .strong {
            font-weight: bold;
        }

        .status {
            display: inline-block;
            padding: 2px 5px;
            font-weight: bold;
            font-size: 7px;
            border: 1px solid;
        }

        .status-pass {
            color: #176b37;
            border-color: #80b996;
            background: #edf8f1;
        }

        .status-fail {
            color: #9b2c2c;
            border-color: #dda0a0;
            background: #fff1f1;
        }

        .status-pending {
            color: #835d00;
            border-color: #d9bd6c;
            background: #fff8df;
        }

        .status-info {
            color: #1e526e;
            border-color: #9ac4da;
            background: #eff8fc;
        }

        .outcome-box {
            margin-top: 5px;
            padding: 8px;
            border: 1.5px solid;
            font-weight: bold;
            font-size: 9px;
            text-align: center;
        }

        .outcome-success {
            background: #edf8f1;
            border-color: #4f9969;
            color: #176b37;
        }

        .outcome-warning {
            background: #fff8df;
            border-color: #c7a331;
            color: #765500;
        }

        .outcome-danger {
            background: #fff1f1;
            border-color: #c76868;
            color: #8c2626;
        }

        .outcome-info {
            background: #eff8fc;
            border-color: #6fa9c7;
            color: #245a75;
        }

        .note {
            margin-top: 5px;
            padding: 5px 6px;
            border-left: 3px solid #9ba8b5;
            background: #f6f7f8;
            font-size: 7.5px;
        }

        .declaration {
            padding: 7px;
            border: 1px solid #cfd4da;
        }

        .signature-table {
            width: 100%;
            margin-top: 14px;
            border-collapse: collapse;
        }

        .signature-table td {
            width: 50%;
            padding-right: 20px;
            vertical-align: bottom;
        }

        .signature-line {
            border-bottom: 1px solid #555;
            height: 18px;
        }

        .avoid-break {
            page-break-inside: avoid;
        }

        .footer {
            position: fixed;
            left: 0;
            right: 0;
            bottom: -12mm;
            border-top: 1px solid #ccc;
            padding-top: 3px;
            color: #666;
            font-size: 6.8px;
        }

        .footer-left {
            width: 78%;
            display: inline-block;
        }
    </style>
</head>

<body>

@php
    $money = function ($value) {
        return 'KES ' . number_format((float) $value, 2);
    };

    $dateTime = function ($value) {
        if (empty($value)) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($value)
                ->format('d/m/Y H:i');
        } catch (\Throwable $e) {
            return '-';
        }
    };

    $dateOnly = function ($value) {
        if (empty($value)) {
            return '-';
        }

        try {
            return \Carbon\Carbon::parse($value)
                ->format('d/m/Y');
        } catch (\Throwable $e) {
            return '-';
        }
    };

    $effectLabel = function ($effect) {
        return strtoupper((string) $effect)
            === 'DEDUCT_FROM_DISBURSEMENT'
            ? 'Deduct from disbursement'
            : 'Add to loan';
    };

    $systemClass =
        $appraisal['system_outcome_class']
        ?? 'info';

    $outcomeClass =
        $systemClass === 'success'
        ? 'outcome-success'
        : (
            $systemClass === 'warning'
            ? 'outcome-warning'
            : (
                $systemClass === 'danger'
                ? 'outcome-danger'
                : 'outcome-info'
            )
        );

    $copyLabel =
        $isOfficialCopy
        ? 'OFFICIAL APPRAISAL COPY'
        : 'MEMBER COPY';
@endphp


{{-- HEADER --}}
<table class="header-table">
    <tr>
        <td style="width:65%;">
            <div class="company-name">
                {{ $companyName }}
            </div>

            <div class="document-title">
                Loan Application & Automated Appraisal Report
            </div>

            <div class="sub-title">
                System-generated credit assessment
            </div>
        </td>

        <td style="width:35%;" class="document-meta">
            <div class="copy-label">
                {{ $copyLabel }}
            </div>

            <br>

            <strong>Application No:</strong>
            {{ $loan->batch_trans_id }}

            <br>

            <strong>Generated:</strong>
            {{ $generatedAt->format('d/m/Y H:i') }}

            <br>

            <strong>Application Status:</strong>
            {{ $appraisal['application_status'] }}
        </td>
    </tr>
</table>


{{-- 1. APPLICANT PROFILE --}}
<div class="section avoid-break">
    <div class="section-title">
        Section 1 - Applicant Profile
    </div>

    <table class="info-table">
        <tr>
            <td class="label">Full Name</td>
            <td class="value">
                {{ $loan->member_name ?: '-' }}
            </td>

            <td class="label">Member No.</td>
            <td class="value">
                {{ $loan->member_sacco_id ?: '-' }}
            </td>
        </tr>

        <tr>
            <td class="label">National ID</td>
            <td class="value">
                {{ $loan->member_national_id ?: '-' }}
            </td>

            <td class="label">KRA PIN</td>
            <td class="value">
                {{ $loan->member_kra_pin ?: '-' }}
            </td>
        </tr>

        <tr>
            <td class="label">Phone</td>
            <td class="value">
                {{ $loan->member_phone_no ?: '-' }}
            </td>

            <td class="label">Email</td>
            <td class="value">
                {{ $loan->member_email ?: '-' }}
            </td>
        </tr>

        <tr>
            <td class="label">Gender</td>
            <td class="value">
                {{ $loan->member_gender ?: '-' }}
            </td>

            <td class="label">Date of Birth</td>
            <td class="value">
                {{ $dateOnly($loan->member_dob) }}
            </td>
        </tr>

        <tr>
            <td class="label">Date Joined</td>
            <td class="value">
                {{ $dateOnly($loan->member_date_joined) }}
            </td>

            <td class="label">Member Status</td>
            <td class="value">
                @if (
                    strtoupper((string) $loan->member_active) === 'Y'
                    &&
                    strtoupper((string) $loan->member_deleted) !== 'Y'
                )
                    Active
                @else
                    Inactive
                @endif
            </td>
        </tr>

        <tr>
            <td class="label">Postal Address</td>
            <td class="value">
                {{ $loan->member_postal_address ?: '-' }}
            </td>

            <td class="label">Payroll / Employee No.</td>
            <td class="value">
                {{ $loan->batch_trans_payroll_number ?? '-' }}
            </td>
        </tr>

        <tr>
            <td class="label">Designation</td>
            <td class="value">
                {{ $loan->batch_trans_present_designation ?? '-' }}
            </td>

            <td class="label">Employment Terms</td>
            <td class="value">
                {{ $loan->batch_trans_terms_of_employment ?? '-' }}
            </td>
        </tr>

        <tr>
            <td class="label">Bank</td>
            <td class="value">
                {{ $loan->bank_name ?: '-' }}
            </td>

            <td class="label">Bank Branch</td>
            <td class="value">
                {{ $loan->bank_branch ?: '-' }}
            </td>
        </tr>

        <tr>
            <td class="label">Bank Account</td>
            <td class="value">
                {{ $loan->bank_account_number ?: '-' }}
            </td>

            <td class="label">Application Date</td>
            <td class="value">
                {{ $dateTime($loan->loan_created_at) }}
            </td>
        </tr>
    </table>
</div>


{{-- 2. LOAN APPLICATION --}}
<div class="section avoid-break">
    <div class="section-title">
        Section 2 - Loan Application
    </div>

    <table class="info-table">
        <tr>
            <td class="label">Loan Product</td>
            <td class="value">
                {{ $loan->loan_type_name ?: '-' }}
            </td>

            <td class="label">Loan Category</td>
            <td class="value">
                {{ $loan->loan_category_name ?: '-' }}
            </td>
        </tr>

        <tr>
            <td class="label">Requested Amount</td>
            <td class="value strong">
                {{ $money($loan->batch_trans_loan_amount) }}
            </td>

            <td class="label">Repayment Period</td>
            <td class="value">
                {{ (int) $loan->batch_trans_loan_duration }}
                month(s)
            </td>
        </tr>

        <tr>
            <td class="label">Interest Rate</td>
            <td class="value">
                {{ number_format((float) $loan->loan_type_interest, 2) }}%
            </td>

            <td class="label">Interest Method</td>
            <td class="value">
                {{ $loan->loan_type_interest_type ?: '-' }}
            </td>
        </tr>

        <tr>
            <td class="label">Expected Interest</td>
            <td class="value">
                {{ $money($loan->batch_trans_expected_interest ?? 0) }}
            </td>

            <td class="label">Monthly Repayment</td>
            <td class="value strong">
                {{ $money($loan->batch_trans_monthly_payment ?? 0) }}
            </td>
        </tr>

        <tr>
            <td class="label">Monthly Principal</td>
            <td class="value">
                {{ $money($loan->batch_trans_monthly_payment_principal ?? 0) }}
            </td>

            <td class="label">Document No.</td>
            <td class="value">
                {{ $loan->batch_trans_doc_no ?: '-' }}
            </td>
        </tr>

        <tr>
            <td class="label">Purpose / Reason</td>
            <td colspan="3">
                {{ $loan->batch_trans_description ?: '-' }}
            </td>
        </tr>

        @if (
            (float) ($loan->batch_trans_loan_to_top_up_amount ?? 0)
            > 0
        )
            <tr>
                <td class="label">Top-up Loan</td>
                <td>
                    Loan #{{ $loan->batch_trans_loan_to_top_up }}
                </td>

                <td class="label">Existing Top-up Balance</td>
                <td>
                    {{ $money($loan->batch_trans_loan_to_top_up_amount) }}
                </td>
            </tr>
        @endif
    </table>
</div>


{{-- 3. AUTOMATED APPRAISAL --}}
<div class="section">
    <div class="section-title">
        Section 3 - Automated Credit Appraisal
    </div>

    <div class="outcome-box {{ $outcomeClass }}">
        SYSTEM APPRAISAL:
        {{ $appraisal['system_outcome'] }}
    </div>

    <table class="data-table" style="margin-top:6px;">
        <thead>
            <tr>
                <th style="width:25%;">
                    Assessment
                </th>

                <th style="width:13%;">
                    Result
                </th>

                <th>
                    System Finding
                </th>
            </tr>
        </thead>

        <tbody>
            @foreach ($appraisal['rules'] as $rule)
                <tr>
                    <td>
                        <strong>
                            {{ $rule['label'] }}
                        </strong>
                    </td>

                    <td>
                        @if ($rule['pass'])
                            <span class="status status-pass">
                                PASS
                            </span>
                        @else
                            <span class="status status-fail">
                                REVIEW
                            </span>
                        @endif
                    </td>

                    <td>
                        {{ $rule['detail'] }}
                    </td>
                </tr>
            @endforeach

            <tr>
                <td>
                    <strong>
                        Guarantor Requirement
                    </strong>
                </td>

                <td>
                    @if ($appraisal['guarantee']['is_sufficient'])
                        <span class="status status-pass">
                            PASS
                        </span>
                    @else
                        <span class="status status-pending">
                            PENDING
                        </span>
                    @endif
                </td>

                <td>
                    Required:
                    {{ $money($appraisal['guarantee']['required']) }}

                    |

                    Approved:
                    {{ $money($appraisal['guarantee']['approved']) }}
                </td>
            </tr>
        </tbody>
    </table>


    {{-- CAPACITY --}}
    <table class="summary-table" style="margin-top:6px;">
        <thead>
            <tr>
                <th colspan="4">
                    Lending Capacity & Existing Exposure
                </th>
            </tr>
        </thead>

        <tbody>
            <tr>
                <td>
                    <strong>Member Shares</strong><br>
                    {{ $money($appraisal['capacity']['member_shares']) }}
                </td>

                <td>
                    <strong>Share Capital</strong><br>
                    {{ $money($appraisal['capacity']['share_capital']) }}
                </td>

                <td>
                    <strong>Share Factor</strong><br>
                    {{ number_format(
                        (float) $appraisal['capacity']['share_factor'],
                        2
                    ) }} x
                </td>

                <td>
                    <strong>Gross Share Capacity</strong><br>
                    {{ $money(
                        $appraisal['capacity']['indicative_gross_share_capacity']
                    ) }}
                </td>
            </tr>

            <tr>
                <td>
                    <strong>Existing Loan Exposure</strong><br>
                    {{ $money($appraisal['capacity']['outstanding_loans']) }}
                </td>

                <td>
                    <strong>Indicative Available Capacity</strong><br>
                    {{ $money(
                        $appraisal['capacity']['indicative_available_share_capacity']
                    ) }}
                </td>

                <td>
                    <strong>Product Ceiling</strong><br>
                    {{ $money($appraisal['capacity']['product_maximum']) }}
                </td>

                <td>
                    <strong>Individual Ceiling</strong><br>

                    @if ($appraisal['capacity']['individual_limit'] !== null)
                        {{ $money(
                            $appraisal['capacity']['individual_limit']
                        ) }}
                    @else
                        Not separately configured
                    @endif
                </td>
            </tr>

            <tr>
                <td colspan="2">
                    <strong>
                        Effective Configured Ceiling
                    </strong><br>

                    @if (
                        $appraisal['capacity']['effective_configured_ceiling']
                        !== null
                    )
                        {{ $money(
                            $appraisal['capacity']['effective_configured_ceiling']
                        ) }}
                    @else
                        -
                    @endif
                </td>

                <td colspan="2">
                    <strong>
                        Membership Age
                    </strong><br>

                    @if ($appraisal['membership']['instant'])
                        Instant qualification applies
                    @elseif (
                        $appraisal['membership']['actual_months']
                        !== null
                    )
                        {{ $appraisal['membership']['actual_months'] }}
                        month(s);

                        minimum
                        {{ $appraisal['membership']['required_months'] }}
                        month(s)
                    @else
                        Not determinable
                    @endif
                </td>
            </tr>
        </tbody>
    </table>

    <div class="note">
        Share-factor capacity is shown as an appraisal indicator.
        The platform's MemberLoanLimitService remains the authoritative
        validation for the member-specific amount limit.
    </div>
</div>


{{-- CHARGES --}}
<div class="section">
    <div class="section-title">
        Section 4 - Charges & Estimated Disbursement
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>
                    Charge
                </th>

                <th style="width:22%;">
                    Treatment
                </th>

                <th style="width:18%;" class="text-right">
                    Amount
                </th>
            </tr>
        </thead>

        <tbody>
            @forelse ($charges as $charge)
                <tr>
                    <td>
                        <strong>
                            {{ $charge->batch_trans_deduction_name ?: 'Charge' }}
                        </strong>

                        @if (!empty($charge->batch_trans_deduction_description))
                            <br>
                            <span class="muted small">
                                {{ $charge->batch_trans_deduction_description }}
                            </span>
                        @endif
                    </td>

                    <td>
                        {{ $effectLabel(
                            $charge->batch_trans_deduction_effect
                        ) }}
                    </td>

                    <td class="text-right">
                        {{ $money(
                            $charge->batch_trans_deduction_amount
                        ) }}
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="3" class="text-center muted">
                        No additional application charge rows.
                    </td>
                </tr>
            @endforelse

            @if (
                (float) $appraisal['financials']['commission']
                > 0
            )
                <tr>
                    <td>
                        <strong>Commission</strong>
                    </td>

                    <td>
                        {{ $effectLabel(
                            $appraisal['financials']['commission_effect']
                        ) }}
                    </td>

                    <td class="text-right">
                        {{ $money(
                            $appraisal['financials']['commission']
                        ) }}
                    </td>
                </tr>
            @endif

            @if (
                (float) $appraisal['financials']['insurance']
                > 0
            )
                <tr>
                    <td>
                        <strong>Loan Insurance</strong>
                    </td>

                    <td>
                        {{ $effectLabel(
                            $appraisal['financials']['insurance_effect']
                        ) }}
                    </td>

                    <td class="text-right">
                        {{ $money(
                            $appraisal['financials']['insurance']
                        ) }}
                    </td>
                </tr>
            @endif
        </tbody>
    </table>


    <table class="summary-table" style="margin-top:6px;">
        <tbody>
            <tr>
                <th>
                    Requested Principal
                </th>

                <td class="text-right">
                    {{ $money(
                        $appraisal['financials']['requested_amount']
                    ) }}
                </td>

                <th>
                    Expected Interest
                </th>

                <td class="text-right">
                    {{ $money(
                        $appraisal['financials']['expected_interest']
                    ) }}
                </td>
            </tr>

            <tr>
                <th>
                    Add-to-Loan Charges
                </th>

                <td class="text-right">
                    {{ $money(
                        $appraisal['financials']['other_add_to_loan']
                    ) }}
                </td>

                <th>
                    Deduct-from-Disbursement Charges
                </th>

                <td class="text-right">
                    {{ $money(
                        $appraisal['financials']['other_deduct_from_disbursement']
                    ) }}
                </td>
            </tr>

            <tr>
                <th>
                    Loan Commitment
                </th>

                <td class="text-right strong">
                    {{ $money(
                        $appraisal['financials']['loan_commitment']
                    ) }}
                </td>

                <th>
                    Estimated Net Disbursement
                </th>

                <td class="text-right strong">
                    {{ $money(
                        $appraisal['financials']['net_disbursement']
                    ) }}
                </td>
            </tr>

            @if (
                $appraisal['financials']['top_up_outstanding']
                > 0
            )
                <tr>
                    <th>
                        Existing Top-up Balance
                    </th>

                    <td class="text-right">
                        {{ $money(
                            $appraisal['financials']['top_up_outstanding']
                        ) }}
                    </td>

                    <td colspan="2" class="small muted">
                        Shown separately. Top-up settlement is handled
                        by the loan-processing workflow and is not
                        subtracted a second time by this appraisal.
                    </td>
                </tr>
            @endif
        </tbody>
    </table>


    <table class="summary-table" style="margin-top:6px;">
        <tr>
            <th style="width:22%;">
                CRB Requirement
            </th>

            <td style="width:28%;">
                {{ $appraisal['crb']['required'] ? 'Required' : 'Not Required' }}
            </td>

            <th style="width:22%;">
                Configured CRB Charge
            </th>

            <td style="width:28%;">
                {{ $money($appraisal['crb']['charge']) }}

                @if (!empty($appraisal['crb']['effect']))
                    <br>
                    <span class="small muted">
                        {{ $effectLabel($appraisal['crb']['effect']) }}
                    </span>
                @endif
            </td>
        </tr>
    </table>

    <div class="note">
        The CRB line reports the product requirement and configured
        charge only. It does not claim that a CRB clearance was
        completed unless such a result is separately recorded by the system.
    </div>
</div>


{{-- GUARANTORS --}}
<div class="section">
    <div class="section-title">
        Section 5 - Guarantor Assessment
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width:4%;">
                    #
                </th>

                <th>
                    Guarantor
                </th>

                <th style="width:12%;">
                    Member No.
                </th>

                <th style="width:18%;">
                    Amount
                </th>

                <th style="width:12%;">
                    Status
                </th>

                <th style="width:20%;">
                    Approval Date
                </th>
            </tr>
        </thead>

        <tbody>
            @forelse ($guarantors as $index => $guarantor)
                @php
                    $approved =
                        strtoupper(
                            (string) $guarantor->guarantors_approved
                        ) === 'Y';
                @endphp

                <tr>
                    <td class="text-center">
                        {{ $index + 1 }}
                    </td>

                    <td>
                        <strong>
                            {{ $guarantor->member_name }}
                        </strong>

                        @if (!empty($guarantor->member_phone_no))
                            <br>
                            <span class="small muted">
                                {{ $guarantor->member_phone_no }}
                            </span>
                        @endif
                    </td>

                    <td>
                        {{ $guarantor->member_sacco_id ?: '-' }}
                    </td>

                    <td class="text-right">
                        {{ $money(
                            $guarantor->guarantors_amount_guaranteed
                        ) }}
                    </td>

                    <td>
                        @if ($approved)
                            <span class="status status-pass">
                                APPROVED
                            </span>
                        @else
                            <span class="status status-pending">
                                PENDING
                            </span>
                        @endif
                    </td>

                    <td>
                        @if (
                            $approved
                            &&
                            !empty($guarantor->guarantors_approved_on)
                        )
                            {{ $dateTime(
                                $guarantor->guarantors_approved_on
                            ) }}
                        @elseif ($approved)
                            <span class="muted">
                                Legacy approval - timestamp unavailable
                            </span>
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="text-center muted">
                        No guarantors recorded.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <table class="summary-table" style="margin-top:6px;">
        <tr>
            <th>
                Guarantee %
            </th>

            <td>
                {{ number_format(
                    (float) $appraisal['guarantee']['percentage'],
                    2
                ) }}%
            </td>

            <th>
                Required
            </th>

            <td class="text-right">
                {{ $money(
                    $appraisal['guarantee']['required']
                ) }}
            </td>
        </tr>

        <tr>
            <th>
                Submitted
            </th>

            <td class="text-right">
                {{ $money(
                    $appraisal['guarantee']['submitted']
                ) }}
            </td>

            <th>
                Approved
            </th>

            <td class="text-right">
                {{ $money(
                    $appraisal['guarantee']['approved']
                ) }}
            </td>
        </tr>

        <tr>
            <th>
                Outstanding Guarantee
            </th>

            <td class="text-right">
                {{ $money(
                    $appraisal['guarantee']['difference']
                ) }}
            </td>

            <th>
                Guarantee Status
            </th>

            <td>
                @if ($appraisal['guarantee']['is_sufficient'])
                    <span class="status status-pass">
                        SATISFIED
                    </span>
                @else
                    <span class="status status-pending">
                        PENDING
                    </span>
                @endif
            </td>
        </tr>
    </table>
</div>


{{-- CREDIT COMMITTEE --}}
<div class="section">
    <div class="section-title">
        Section 6 - Credit Committee Review
    </div>

    <table class="summary-table">
        <tr>
            <th style="width:24%;">
                Committee Outcome
            </th>

            <td>
                @if ($committee['status'] === 'APPROVED')
                    <span class="status status-pass">
                        APPROVED
                    </span>
                @elseif ($committee['status'] === 'DECLINED')
                    <span class="status status-fail">
                        NOT APPROVED
                    </span>
                @elseif ($committee['status'] === 'NOT_REQUIRED')
                    <span class="status status-info">
                        NOT REQUIRED
                    </span>
                @else
                    <span class="status status-pending">
                        {{ strtoupper($committee['display_status']) }}
                    </span>
                @endif
            </td>

            <th style="width:22%;">
                Decision Date
            </th>

            <td>
                @if (!empty($committee['overall_decided_at']))
                    {{ $committee['overall_decided_at']->format('d/m/Y H:i') }}
                @else
                    -
                @endif
            </td>
        </tr>
    </table>


    @if ($isOfficialCopy)
        <table class="summary-table" style="margin-top:6px;">
            <tr>
                <th>
                    Required Yes Decisions
                </th>

                <td>
                    {{ $committee['required_approvals'] }}
                </td>

                <th>
                    Yes Recorded
                </th>

                <td>
                    {{ $committee['yes_count'] }}
                </td>

                <th>
                    No Recorded
                </th>

                <td>
                    {{ $committee['no_count'] }}
                </td>
            </tr>
        </table>

        <table class="data-table" style="margin-top:6px;">
            <thead>
                <tr>
                    <th>
                        Committee Member
                    </th>

                    <th>
                        Role at Decision
                    </th>

                    <th style="width:16%;">
                        Decision
                    </th>

                    <th style="width:22%;">
                        Decision Date
                    </th>
                </tr>
            </thead>

            <tbody>
                @forelse ($committee['decisions'] as $decision)
                    <tr>
                        <td>
                            {{ $decision['member_name'] }}
                        </td>

                        <td>
                            {{
                                $decision['classification_name']
                                ?: (
                                    $decision['classification_code']
                                    ?: '-'
                                )
                            }}
                        </td>

                        <td>
                            @if ($decision['decision'] === 'Y')
                                <span class="status status-pass">
                                    APPROVE
                                </span>
                            @else
                                <span class="status status-fail">
                                    NOT APPROVE
                                </span>
                            @endif
                        </td>

                        <td>
                            {{ $dateTime($decision['decided_at']) }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="text-center muted">
                            No Credit Committee decisions recorded.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="note">
            This section is visible because this PDF was generated
            by a user with the downloadloanpdf right. Committee roles
            shown here are the roles stored at the time each decision
            was recorded.
        </div>
    @else
        <div class="note">
            Member copies show the overall Credit Committee outcome only.
            Individual committee members and their individual decisions
            are restricted to authorized official copies.
        </div>
    @endif
</div>


{{-- APPLICANT DECLARATION & DIGITAL CONSENT --}}
<div class="section avoid-break">
    <div class="section-title">
        Section 7 - Applicant Declaration & Digital Consent
    </div>

    <div class="declaration">
        <p style="margin:0 0 8px 0;">
            By submitting this loan application electronically, the applicant
            confirmed that the information provided was true and complete to
            the best of their knowledge and agreed to the applicable SACCO
            loan terms, repayment obligations, deductions, charges, guarantees
            and recovery conditions associated with the selected loan product.
        </p>

        <table class="summary-table">
            <tr>
                <th style="width:25%;">
                    Consent Status
                </th>

                <td>
                    <span class="status status-pass">
                        ACCEPTED ELECTRONICALLY
                    </span>
                </td>
            </tr>

            <tr>
                <th>
                    Application Submitted
                </th>

                <td>
                    {{ $dateTime($loan->loan_created_at) }}
                </td>
            </tr>

            <tr>
                <th>
                    IP Address
                </th>

                <td>
                    {{ $loan->batch_trans_ip ?: '-' }}
                </td>
            </tr>
        </table>
    </div>
</div>


<div class="footer">
    <span class="footer-left">
        This report was generated automatically from the SACCO loan
        application system. Values reflect system records at the time
        the PDF was generated.
    </span>
</div>


{{-- Correct DomPDF page numbering --}}
<script type="text/php">
    if (isset($pdf)) {
        $font = $fontMetrics->get_font(
            "DejaVu Sans",
            "normal"
        );

        $pdf->page_text(
            500,
            820,
            "Page {PAGE_NUM} of {PAGE_COUNT}",
            $font,
            7,
            array(0.35, 0.35, 0.35)
        );
    }
</script>

</body>
</html>