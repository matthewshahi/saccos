@extends('layouts.app')

@section('content')
    @php
        $migrationMode = ($data['migrationMode'] ?? 'N') === 'Y';

        $selectedSections = $data['sections'] ?? ['capital', 'savings', 'fosa', 'special_savings', 'loans'];

        $selectedFosaTypes = $data['fosaTypeIds'] ?? [];
        $selectedLoanTypes = $data['loanTypeIds'] ?? [];
        $selectedLoanId = $data['selectedLoanId'] ?? null;

        /*
         * Officials use the staff statement route.
         * Ordinary members stay on the self-statement URL so applying filters
         * cannot accidentally send them through staff-only middleware.
         */
        $statementFilterAction =
            (int) (auth()->user()->member_position ?? 0) === 2
                ? route('members.statement', ['id' => $data['member']->member_id])
                : url('/members/statement/self');

        $periodFromLabel =
            ($data['period_from'] ?? '000000') === '000000' ? 'Beginning' : $data['period_from'] ?? '000000';

        $periodToLabel = ($data['period_to'] ?? '999999') === '999999' ? 'Latest' : $data['period_to'] ?? '999999';
    @endphp

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    {{-- ================= NON-PRINTABLE SECTION ================= --}}
    <div id="statementPageTop"></div>

    <div id="nonPrintable">
        <div class="statement-header d-flex justify-content-between align-items-center gap-3 mb-4">
            <h2 class="fw-bold text-primary">
                Member Statement — {{ $data['member']->member_name }}
            </h2>

            <div class="statement-download-actions">
                <button type="button" id="downloadPDF" class="btn btn-primary">
                    <i class="fas fa-file-pdf"></i> Download PDF
                </button>

                <button type="button" id="downloadExcel" class="btn btn-success">
                    <i class="fas fa-file-excel"></i> Download Excel
                </button>

                <span id="loadingIndicator" class="loading-indicator">
                    <i class="fas fa-spinner fa-spin"></i> Generating PDF...
                </span>
            </div>
        </div>

        @if (session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @if ($migrationMode)
            <div class="alert alert-warning">
                <strong>Migration Mode is ON.</strong> Red X buttons are enabled for deleting loans and loan repayments.
            </div>
        @endif

        <div class="card statement-filter-card mb-4">
            <div class="card-body">
                <form method="get" action="{{ $statementFilterAction }}" id="statementFilterForm">

                    {{-- Preserve member-view context where applicable --}}
                    @if (request()->query('view_as_member') === 'y')
                        <input type="hidden" name="view_as_member" value="y">
                    @endif

                    @if (request()->filled('jaccount'))
                        <input type="hidden" name="jaccount" value="{{ request()->query('jaccount') }}">
                    @endif

                    <div class="filter-group">
                        <div class="filter-group-heading">
                            <span class="filter-step">1</span>
                            <div>
                                <div class="filter-title">Statement Period</div>
                                <div class="filter-help">Choose the historical period to include in the statement.</div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-lg-4 col-md-6">
                                <label class="form-label fw-bold" for="periodFrom">
                                    Period From
                                </label>

                                <input type="text" inputmode="numeric" maxlength="6" name="period_from" id="periodFrom"
                                    class="form-control"
                                    value="{{ request('period_from', $data['period_from'] ?? '000000') }}"
                                    placeholder="YYYYMM" autocomplete="off">

                                <div class="form-text">Use YYYYMM. Example: 202601.</div>
                            </div>

                            <div class="col-lg-4 col-md-6">
                                <label class="form-label fw-bold" for="periodTo">
                                    Period To
                                </label>

                                <input type="text" inputmode="numeric" maxlength="6" name="period_to" id="periodTo"
                                    class="form-control" value="{{ request('period_to', $data['period_to'] ?? '999999') }}"
                                    placeholder="YYYYMM" autocomplete="off">

                                <div class="form-text">Use YYYYMM. Example: 202608.</div>
                            </div>

                            <div class="col-lg-4 col-md-6">
                                <label class="form-label fw-bold" for="clearedLoans">
                                    Loan Status
                                </label>

                                <select name="cleared_loans" id="clearedLoans" class="form-select">
                                    <option value="uncleared"
                                        {{ request('cleared_loans', $data['cleared_loans'] ?? 'uncleared') === 'uncleared' ? 'selected' : '' }}>
                                        Outstanding Only
                                    </option>

                                    <option value="all"
                                        {{ request('cleared_loans', $data['cleared_loans'] ?? 'uncleared') === 'all' ? 'selected' : '' }}>
                                        All Loans
                                    </option>

                                    <option value="cleared"
                                        {{ request('cleared_loans', $data['cleared_loans'] ?? 'uncleared') === 'cleared' ? 'selected' : '' }}>
                                        Cleared Only
                                    </option>
                                </select>

                                <div class="form-text">
                                    Ignored when one specific loan is selected.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="filter-group">
                        <div class="filter-group-heading">
                            <span class="filter-step">2</span>
                            <div>
                                <div class="filter-title">Statement Contents</div>
                                <div class="filter-help">Choose one or more statement sections.</div>
                            </div>
                        </div>

                        <div class="section-choice-grid">
                            <label class="section-choice" for="sectionCapital">
                                <input class="form-check-input" type="checkbox" name="sections[]" value="capital"
                                    id="sectionCapital"
                                    {{ in_array('capital', $selectedSections, true) ? 'checked' : '' }}>
                                <span>
                                    <strong>Share Capital</strong>
                                    <small>Capital contribution ledger</small>
                                </span>
                            </label>

                            <label class="section-choice" for="sectionSavings">
                                <input class="form-check-input" type="checkbox" name="sections[]" value="savings"
                                    id="sectionSavings"
                                    {{ in_array('savings', $selectedSections, true) ? 'checked' : '' }}>
                                <span>
                                    <strong>Savings / Deposits</strong>
                                    <small>Member deposits and running balance</small>
                                </span>
                            </label>

                            <label class="section-choice" for="sectionFosa">
                                <input class="form-check-input" type="checkbox" name="sections[]" value="fosa"
                                    id="sectionFosa" {{ in_array('fosa', $selectedSections, true) ? 'checked' : '' }}>
                                <span>
                                    <strong>FOSA</strong>
                                    <small>FOSA ledgers grouped by FOSA type</small>
                                </span>
                            </label>

                            <label class="section-choice" for="sectionSpecialSavings">
                                <input class="form-check-input" type="checkbox" name="sections[]"
                                    value="special_savings" id="sectionSpecialSavings"
                                    {{ in_array('special_savings', $selectedSections, true) ? 'checked' : '' }}>
                                <span>
                                    <strong>Special Savings</strong>
                                    <small>Special savings product accounts</small>
                                </span>
                            </label>

                            <label class="section-choice" for="sectionLoans">
                                <input class="form-check-input" type="checkbox" name="sections[]" value="loans"
                                    id="sectionLoans" {{ in_array('loans', $selectedSections, true) ? 'checked' : '' }}>
                                <span>
                                    <strong>Loans</strong>
                                    <small>Loan principal, interest and balances</small>
                                </span>
                            </label>
                        </div>
                    </div>

                    <div class="filter-group">
                        <div class="filter-group-heading">
                            <span class="filter-step">3</span>
                            <div>
                                <div class="filter-title">FOSA Options</div>
                                <div class="filter-help">
                                    Leave every type unchecked to include all FOSA types.
                                </div>
                            </div>
                        </div>

                        @if (($data['memberFosaTypes'] ?? collect())->count() > 0)
                            <div class="filter-checkbox-panel">
                                @foreach ($data['memberFosaTypes'] ?? collect() as $fosaType)
                                    <label class="filter-checkbox-item" for="fosaType{{ $fosaType->type_id }}">
                                        <input class="form-check-input" type="checkbox" name="fosa_types[]"
                                            value="{{ $fosaType->type_id }}" id="fosaType{{ $fosaType->type_id }}"
                                            {{ in_array((int) $fosaType->type_id, $selectedFosaTypes, true) ? 'checked' : '' }}>

                                        <span>
                                            <strong>{{ $fosaType->type_name }}</strong>

                                            @if (!empty($fosaType->type_prefix))
                                                <small>{{ $fosaType->type_prefix }}</small>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        @else
                            <div class="filter-empty-state">
                                No FOSA types are available for this member as at the selected ending period.
                            </div>
                        @endif
                    </div>

                    <div class="filter-group">
                        <div class="filter-group-heading">
                            <span class="filter-step">4</span>
                            <div>
                                <div class="filter-title">Loan Options</div>
                                <div class="filter-help">
                                    Filter by one or more loan types, or select one exact loan.
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-lg-6">
                                <label class="form-label fw-bold d-block">
                                    Loan Types
                                </label>

                                @if (($data['memberLoanTypes'] ?? collect())->count() > 0)
                                    <div class="filter-checkbox-panel loan-type-panel">
                                        @foreach ($data['memberLoanTypes'] ?? collect() as $loanType)
                                            <label class="filter-checkbox-item"
                                                for="loanType{{ $loanType->loan_type_id }}">
                                                <input class="form-check-input" type="checkbox" name="loan_types[]"
                                                    value="{{ $loanType->loan_type_id }}"
                                                    id="loanType{{ $loanType->loan_type_id }}"
                                                    {{ in_array((int) $loanType->loan_type_id, $selectedLoanTypes, true) ? 'checked' : '' }}>

                                                <span>
                                                    <strong>{{ $loanType->loan_type_name }}</strong>
                                                </span>
                                            </label>
                                        @endforeach
                                    </div>
                                @else
                                    <div class="filter-empty-state">
                                        No loan types are available for this member as at the selected ending period.
                                    </div>
                                @endif

                                <div class="form-text">
                                    Leave every loan type unchecked to include all matching loan types.
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <label class="form-label fw-bold" for="specificLoan">
                                    Specific Loan
                                </label>

                                <select name="loan_id" id="specificLoan" class="form-select">
                                    <option value="">All Matching Loans</option>

                                    @foreach ($data['memberLoanChoices'] ?? collect() as $loanChoice)
                                        <option value="{{ $loanChoice->loan_id }}"
                                            data-loan-type="{{ $loanChoice->loan_loan_type }}"
                                            {{ (int) $selectedLoanId === (int) $loanChoice->loan_id ? 'selected' : '' }}>
                                            {{ $loanChoice->loan_type_name }}
                                            — Loan #{{ $loanChoice->loan_id }}
                                            — Ksh {{ number_format($loanChoice->loan_amount, 2) }}
                                            @if (!empty($loanChoice->loan_taken_period))
                                                — {{ $loanChoice->loan_taken_period }}
                                            @endif
                                        </option>
                                    @endforeach
                                </select>

                                <div class="form-text">
                                    Selecting one loan produces that individual loan statement.
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="statement-filter-actions">
                        <button type="submit" class="btn btn-success statement-apply-btn">
                            <i class="fas fa-filter"></i>
                            Apply Statement Filters
                        </button>

                        <a href="{{ $statementFilterAction }}" class="btn btn-outline-secondary statement-reset-btn">
                            <i class="fas fa-rotate-left"></i>
                            Reset to Full Statement
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <div class="statement-jump-nav" aria-label="Statement section navigation">
            <span class="statement-jump-label">Jump to:</span>

            @if ($data['showCapital'])
                <a href="#statement-capital">Capital</a>
            @endif

            @if ($data['showSavings'])
                <a href="#statement-savings">Savings</a>
            @endif

            @if ($data['showFosa'])
                <a href="#statement-fosa">FOSA</a>
            @endif

            @if ($data['showSpecialSavings'])
                <a href="#statement-special-savings">Special Savings</a>
            @endif

            @if ($data['showLoans'])
                <a href="#statement-loans">Loans</a>
            @endif
        </div>
    </div>

    {{-- ================= PRINTABLE SECTION ================= --}}
    <div id="printableArea" class="statement-sections">

        {{-- MEMBER INFO --}}
        <div class="text-center mb-4">
            <h3 class="fw-bold text-primary mb-1">Member Statement</h3>
            <h5 class="fw-normal mb-0">{{ $data['member']->member_name }}</h5>

            <p class="small text-muted mb-0">
                Member Sacco ID: <strong>{{ $data['member']->member_sacco_id }}</strong> |
                National ID: <strong>{{ $data['member']->member_national_id ?? 'N/A' }}</strong>
            </p>

            <p class="small text-muted mb-1">
                Email: {{ $data['member']->member_email ?? 'N/A' }} |
                Phone: {{ $data['member']->member_phone_no ?? 'N/A' }}
            </p>

            <p class="statement-period-line mb-0">
                Statement Period:
                <strong>{{ $periodFromLabel }}</strong>
                to
                <strong>{{ $periodToLabel }}</strong>
            </p>

            <hr>
        </div>

        <div class="statement-sections-inner">

            {{-- SHARE CAPITAL --}}
            @if ($data['showCapital'])
                <div class="card mb-4 statement-section-card" id="statement-capital">
                    <div class="card-header bg-primary text-white fw-bold">Share Capital Statement</div>

                    <div class="card-body p-0">
                        <div class="statement-table-wrap">
                            <table class="table table-striped table-sm mb-0 statement-ledger-table">
                                <thead class="bg-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Period</th>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Doc No</th>
                                        <th class="text-end">Debit</th>
                                        <th class="text-end">Credit</th>
                                        <th class="text-end">Balance</th>
                                    </tr>

                                    <tr class="table-secondary">
                                        <td colspan="7" class="text-end fw-bold">Opening Balance</td>
                                        <td class="text-end fw-bold">
                                            {{ number_format($data['openingBalanceCapital'], 2) }}
                                        </td>
                                    </tr>
                                </thead>

                                <tbody>
                                    @php $total_capital = $data['openingBalanceCapital']; @endphp

                                    @foreach ($data['capitalContributions'] as $i => $r)
                                        @php $total_capital += $r->share_capitalamount_paying; @endphp

                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>{{ $r->share_capitalperiod }}</td>
                                            <td>{{ \Carbon\Carbon::parse($r->share_capitaldate_paid)->format('d-m-Y') }}
                                            </td>
                                            <td>{{ $r->share_capitaldescription }}</td>
                                            <td>{{ $r->share_capitaldoc_no }}</td>

                                            <td class="text-end">
                                                {{ $r->share_capitalamount_paying < 0 ? number_format(-$r->share_capitalamount_paying, 2) : '' }}
                                            </td>

                                            <td class="text-end">
                                                {{ $r->share_capitalamount_paying > 0 ? number_format($r->share_capitalamount_paying, 2) : '' }}
                                            </td>

                                            <td class="text-end fw-bold">
                                                {{ number_format($total_capital, 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            {{-- MEMBER DEPOSITS --}}
            @if ($data['showSavings'])
                <div class="card mb-4 statement-section-card" id="statement-savings">
                    <div class="card-header bg-success text-white fw-bold">Deposit (Shares) Statement</div>

                    <div class="card-body p-0">
                        <div class="statement-table-wrap">
                            <table class="table table-striped table-sm mb-0 statement-ledger-table">
                                <thead class="bg-light">
                                    <tr>
                                        <th>#</th>
                                        <th>Period</th>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Doc No</th>
                                        <th class="text-end">Debit</th>
                                        <th class="text-end">Credit</th>
                                        <th class="text-end">Balance</th>
                                    </tr>

                                    <tr class="table-secondary">
                                        <td colspan="7" class="text-end fw-bold">Opening Balance</td>
                                        <td class="text-end fw-bold">
                                            {{ number_format($data['openingBalanceShares'], 2) }}
                                        </td>
                                    </tr>
                                </thead>

                                <tbody>
                                    @php $total_shares = $data['openingBalanceShares']; @endphp

                                    @foreach ($data['shareContributions'] as $i => $r)
                                        @php $total_shares += $r->share_amount_paying; @endphp

                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>{{ $r->share_period }}</td>
                                            <td>{{ \Carbon\Carbon::parse($r->share_date_paid)->format('d-m-Y') }}</td>
                                            <td>{{ $r->share_description }}</td>
                                            <td>{{ $r->share_doc_no }}</td>

                                            <td class="text-end">
                                                {{ $r->share_amount_paying < 0 ? number_format(-$r->share_amount_paying, 2) : '' }}
                                            </td>

                                            <td class="text-end">
                                                {{ $r->share_amount_paying > 0 ? number_format($r->share_amount_paying, 2) : '' }}
                                            </td>

                                            <td class="text-end fw-bold">
                                                {{ number_format($total_shares, 2) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif

            {{-- FOSA --}}
            @if ($data['showFosa'])
                <div class="card mb-4 statement-section-card" id="statement-fosa">
                    <div class="card-header bg-warning fw-bold">
                        FOSA Statement
                    </div>

                    <div class="card-body p-0">
                        @forelse ($data['statementFosaTypes'] ?? collect() as $fosaType)
                            @php
                                /*
                                 * Each FOSA type is its own ledger.
                                 * The controller supplies the historical opening balance
                                 * before period_from and only the movements inside the
                                 * selected statement period.
                                 */
                                $rows = ($data['fosaContributions'] ?? collect())
                                    ->filter(function ($row) use ($fosaType) {
                                        /*
                                         * Virtual FOSA type 0 = Other Deposits.
                                         *
                                         * matched_fosa_type_id is NULL when:
                                         * - fosa_type_id is NULL
                                         * - fosa_type_id is 0 with no configured type
                                         * - fosa_type_id references a deleted/missing FOSA type
                                         */
                                        if ((int) $fosaType->type_id === 0) {
                                            return $row->matched_fosa_type_id === null;
                                        }

                                        /*
                                         * Normal configured FOSA types.
                                         */
                                        return (int) $row->matched_fosa_type_id === (int) $fosaType->type_id;
                                    })
                                    ->values();

                                $running = (float) ($data['fosaOpeningBalances'][$fosaType->type_id] ?? 0);
                            @endphp

                            <div class="fosa-type-block">
                                <div class="fosa-type-heading">
                                    <div>
                                        <strong>{{ $fosaType->type_name }}</strong>

                                        @if (!empty($fosaType->type_prefix))
                                            <span class="fosa-type-prefix">{{ $fosaType->type_prefix }}</span>
                                        @endif
                                    </div>

                                    <span class="fosa-type-caption">FOSA Type Ledger</span>
                                </div>

                                <div class="statement-table-wrap">
                                    <table class="table table-striped table-sm mb-0 statement-ledger-table">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Period</th>
                                                <th>Date</th>
                                                <th>Description</th>
                                                <th>Doc No</th>
                                                <th class="text-end">Debit</th>
                                                <th class="text-end">Credit</th>
                                                <th class="text-end">Balance</th>
                                            </tr>

                                            <tr class="table-secondary">
                                                <td colspan="7" class="text-end fw-bold">
                                                    Opening Balance before {{ $periodFromLabel }}
                                                </td>

                                                <td class="text-end fw-bold">
                                                    {{ number_format($running, 2) }}
                                                </td>
                                            </tr>
                                        </thead>

                                        <tbody>
                                            @forelse ($rows as $i => $r)
                                                @php
                                                    $running += (float) $r->fosa_amount_paying;
                                                @endphp

                                                <tr>
                                                    <td>{{ $i + 1 }}</td>
                                                    <td>{{ $r->fosa_period }}</td>
                                                    <td>
                                                        {{ !empty($r->fosa_date_paid) ? \Carbon\Carbon::parse($r->fosa_date_paid)->format('d-m-Y') : '' }}
                                                    </td>
                                                    <td>{{ $r->fosa_description }}</td>
                                                    <td>{{ $r->fosa_doc_no }}</td>

                                                    <td class="text-end">
                                                        {{ $r->fosa_amount_paying < 0 ? number_format(-$r->fosa_amount_paying, 2) : '' }}
                                                    </td>

                                                    <td class="text-end">
                                                        {{ $r->fosa_amount_paying > 0 ? number_format($r->fosa_amount_paying, 2) : '' }}
                                                    </td>

                                                    <td class="text-end fw-bold">
                                                        {{ number_format($running, 2) }}
                                                    </td>
                                                </tr>
                                            @empty
                                                <tr>
                                                    <td colspan="8" class="text-center text-muted py-3">
                                                        No FOSA movements in the selected period.
                                                    </td>
                                                </tr>
                                            @endforelse

                                            <tr class="table-secondary fw-bold">
                                                <td colspan="7" class="text-end">
                                                    Closing Balance — {{ $fosaType->type_name }}
                                                </td>

                                                <td class="text-end">
                                                    {{ number_format($running, 2) }}
                                                </td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @empty
                            <div class="statement-empty-state">
                                No FOSA records were found for the selected period and filters.
                            </div>
                        @endforelse
                    </div>
                </div>
            @endif

            {{-- SPECIAL SAVINGS --}}
            @if ($data['showSpecialSavings'])
                <div class="card mb-4 financial-section-card statement-section-card" id="statement-special-savings">
                    <div class="card-header financial-section-header">
                        <div>
                            <div class="financial-section-title">Special Savings Statement</div>
                            <div class="financial-section-subtitle">Special savings product and account ledger movements
                            </div>
                        </div>
                    </div>

                    <div class="card-body financial-section-body">
                        @if (isset($data['specialSavings']) && $data['specialSavings']->count() > 0)
                            @foreach ($data['specialSavings'] as $specialSaving)
                                @php
                                    $account = $specialSaving->account;
                                    $transactions = $specialSaving->transactions ?? collect();

                                    $openingPrincipal = (float) $specialSaving->opening_principal;
                                    $openingInterest =
                                        (float) $specialSaving->opening_accrued_interest +
                                        (float) $specialSaving->opening_available_interest;
                                    $openingTotal = (float) $specialSaving->opening_total;

                                    $totalDebit = 0;
                                    $totalCredit = 0;

                                    $principalCredit = 0;
                                    $principalDebit = 0;

                                    $interestCredit = 0;
                                    $interestDebit = 0;

                                    foreach ($transactions as $summaryTxn) {
                                        $summaryDirection = strtoupper(
                                            (string) $summaryTxn->special_saving_transaction_direction,
                                        );
                                        $summaryAmount = (float) $summaryTxn->special_saving_transaction_amount;

                                        $summaryPrincipalAmount = abs(
                                            (float) ($summaryTxn->special_saving_transaction_principal_amount ?? 0),
                                        );
                                        $summaryInterestAmount = abs(
                                            (float) ($summaryTxn->special_saving_transaction_interest_amount ?? 0),
                                        );

                                        if ($summaryDirection === 'DEBIT') {
                                            $totalDebit += $summaryAmount;
                                            $principalDebit += $summaryPrincipalAmount;
                                            $interestDebit += $summaryInterestAmount;
                                        } else {
                                            $totalCredit += $summaryAmount;
                                            $principalCredit += $summaryPrincipalAmount;
                                            $interestCredit += $summaryInterestAmount;
                                        }
                                    }

                                    $closingTxn = $transactions->last();

                                    $closingPrincipal = $closingTxn
                                        ? (float) $closingTxn->special_saving_transaction_principal_balance_after
                                        : $openingPrincipal;

                                    $closingInterest = $closingTxn
                                        ? (float) $closingTxn->special_saving_transaction_accrued_interest_after +
                                            (float) $closingTxn->special_saving_transaction_available_interest_after
                                        : $openingInterest;

                                    $closingTotal = $closingTxn
                                        ? (float) $closingTxn->special_saving_transaction_total_balance_after
                                        : $openingTotal;

                                    $expectedClosingTotal = $openingTotal + $totalCredit - $totalDebit;
                                    $difference = round($expectedClosingTotal - $closingTotal, 2);

                                    $productName = $account->special_saving_product_name ?? 'Special Savings';
                                    $accountNumber = $account->special_saving_account_number ?? '';
                                @endphp

                                <div class="financial-product-panel">
                                    <div class="financial-product-header">
                                        <div>
                                            <div class="financial-product-title">
                                                {{ $productName }}
                                                @if (!empty($accountNumber))
                                                    <span class="financial-account-number">{{ $accountNumber }}</span>
                                                @endif
                                            </div>

                                            <div class="financial-product-meta">
                                                Status:
                                                <span class="financial-status-badge">
                                                    {{ $account->special_saving_account_status }}
                                                </span>
                                            </div>
                                        </div>

                                        <div class="financial-closing-box">
                                            <span>Current Account Total</span>
                                            <strong>{{ number_format((float) $account->special_saving_account_total_balance, 2) }}</strong>
                                        </div>
                                    </div>

                                    <div class="financial-summary-grid">
                                        <div class="financial-summary-item">
                                            <span>Opening Total</span>
                                            <strong>{{ number_format($openingTotal, 2) }}</strong>
                                        </div>

                                        <div class="financial-summary-item">
                                            <span>Principal Credits Posted</span>
                                            <strong>{{ number_format($principalCredit, 2) }}</strong>
                                        </div>

                                        <div class="financial-summary-item">
                                            <span>Interest Credits Posted</span>
                                            <strong>{{ number_format($interestCredit, 2) }}</strong>
                                        </div>

                                        <div class="financial-summary-item">
                                            <span>Total Credits Posted</span>
                                            <strong>{{ number_format($totalCredit, 2) }}</strong>
                                        </div>

                                        <div class="financial-summary-item">
                                            <span>Total Debits / Resets</span>
                                            <strong>{{ number_format($totalDebit, 2) }}</strong>
                                        </div>

                                        <div class="financial-summary-item">
                                            <span>Closing Principal</span>
                                            <strong>{{ number_format($closingPrincipal, 2) }}</strong>
                                        </div>

                                        <div class="financial-summary-item">
                                            <span>Closing Interest</span>
                                            <strong>{{ number_format($closingInterest, 2) }}</strong>
                                        </div>

                                        <div class="financial-summary-item financial-summary-total">
                                            <span>Closing Total</span>
                                            <strong>{{ number_format($closingTotal, 2) }}</strong>
                                        </div>

                                        <div
                                            class="financial-summary-item {{ abs($difference) > 0.01 ? 'financial-check-bad' : 'financial-check-good' }}">
                                            <span>Reconciliation Check</span>
                                            <strong>{{ number_format($difference, 2) }}</strong>
                                        </div>
                                    </div>

                                    <div class="financial-formula-line">
                                        Opening Total + Total Credits Posted - Total Debits / Resets = Closing Total
                                    </div>

                                    <div class="financial-table-wrap">
                                        <table class="table table-sm mb-0 financial-ledger-table">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th class="nowrap-cell">Period</th>
                                                    <th class="nowrap-cell">Date</th>
                                                    <th>Description</th>
                                                    <th class="nowrap-cell">Doc No</th>
                                                    <th class="nowrap-cell">Type</th>
                                                    <th class="text-end money-cell">Debit</th>
                                                    <th class="text-end money-cell">Credit</th>
                                                    <th class="text-end money-cell">Principal Bal</th>
                                                    <th class="text-end money-cell">Interest Bal</th>
                                                    <th class="text-end money-cell">Total Bal</th>
                                                </tr>

                                                <tr class="financial-opening-row">
                                                    <td colspan="8" class="text-end fw-bold">
                                                        Opening Balance before selected period
                                                    </td>
                                                    <td class="text-end fw-bold money-cell">
                                                        {{ number_format($openingPrincipal, 2) }}</td>
                                                    <td class="text-end fw-bold money-cell">
                                                        {{ number_format($openingInterest, 2) }}</td>
                                                    <td class="text-end fw-bold money-cell">
                                                        {{ number_format($openingTotal, 2) }}</td>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                @foreach ($transactions as $txn)
                                                    @php
                                                        $direction = strtoupper(
                                                            (string) $txn->special_saving_transaction_direction,
                                                        );
                                                        $amount = (float) $txn->special_saving_transaction_amount;

                                                        $debit = $direction === 'DEBIT' ? $amount : 0;
                                                        $credit = $direction === 'CREDIT' ? $amount : 0;

                                                        $interestBalance =
                                                            (float) $txn->special_saving_transaction_accrued_interest_after +
                                                            (float) $txn->special_saving_transaction_available_interest_after;
                                                    @endphp

                                                    <tr>
                                                        <td class="row-number-cell">{{ $loop->iteration }}</td>
                                                        <td class="nowrap-cell">
                                                            {{ $txn->special_saving_transaction_period }}
                                                        </td>
                                                        <td class="nowrap-cell">
                                                            {{ \Carbon\Carbon::parse($txn->special_saving_transaction_date)->format('d-m-Y') }}
                                                        </td>
                                                        <td class="description-cell">
                                                            {{ $txn->special_saving_transaction_description }}</td>
                                                        <td class="nowrap-cell">
                                                            {{ $txn->special_saving_transaction_doc_no }}
                                                        </td>
                                                        <td class="nowrap-cell">
                                                            {{ $txn->special_saving_transaction_type }}
                                                        </td>

                                                        <td class="text-end money-cell">
                                                            {{ $debit != 0 ? number_format($debit, 2) : '' }}
                                                        </td>

                                                        <td class="text-end money-cell">
                                                            {{ $credit != 0 ? number_format($credit, 2) : '' }}
                                                        </td>

                                                        <td class="text-end fw-bold money-cell">
                                                            {{ number_format((float) $txn->special_saving_transaction_principal_balance_after, 2) }}
                                                        </td>

                                                        <td class="text-end fw-bold money-cell">
                                                            {{ number_format($interestBalance, 2) }}
                                                        </td>

                                                        <td class="text-end fw-bold money-cell total-balance-cell">
                                                            {{ number_format((float) $txn->special_saving_transaction_total_balance_after, 2) }}
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            @endforeach
                        @else
                            <div class="statement-empty-state">
                                No Special Savings records were found for the selected period.
                            </div>
                        @endif
                    </div>
                </div>
            @endif



            {{-- LOANS --}}
            @if ($data['showLoans'])
                <div class="card mb-5 statement-section-card" id="statement-loans">

                    <div class="card-header bg-danger text-white fw-bold">Loan Statement</div>

                    <div class="card-body">
                        @forelse ($data['loans'] as $loan)
                            <div class="loan-box mb-4 p-3 border rounded">

                                <h6 class="fw-bold text-danger d-flex justify-content-between align-items-center">
                                    <span>
                                        {{ $loan->loan_type_name }} ({{ $loan->loan_id }}) — {{ $loan->loan_doc_no }}
                                    </span>

                                    @if ($migrationMode)
                                        <form method="POST"
                                            action="{{ route('migration.statement.loan.destroy', $loan->loan_id) }}"
                                            class="migration-delete-form d-inline" data-export="ignore"
                                            onsubmit="return confirm('Delete this loan and all its repayments? This cannot be undone.');">
                                            @csrf
                                            @method('DELETE')

                                            <button type="submit" class="migration-delete-x" title="Delete loan">
                                                &times;
                                            </button>
                                        </form>
                                    @endif
                                </h6>

                                @php
                                    $loanDuration = isset($loan->loan_type_duration)
                                        ? (int) $loan->loan_type_duration
                                        : null;

                                    $isShortTermLoan =
                                        ($loanDuration !== null && $loanDuration > 0 && $loanDuration <= 1) ||
                                        (int) ($loan->loan_type_instant_qualification ?? 0) === 1 ||
                                        (int) ($loan->loan_type_instant_disbursement ?? 0) === 1;
                                @endphp

                                <p class="small mb-2">

                                    @if ($isShortTermLoan && !empty($loan->loan_on))
                                        <strong>Date Taken:</strong>
                                        {{ \Carbon\Carbon::parse($loan->loan_on)->format('d-m-Y') }}
                                    @else
                                        <strong>Period Taken:</strong>
                                        {{ $loan->loan_taken_period }}
                                    @endif

                                    |

                                    <strong>Amount:</strong>
                                    Ksh {{ number_format($loan->loan_amount, 2) }} |

                                    <strong>Paid:</strong>
                                    Ksh {{ number_format($loan->loan_loan_paid, 2) }} |

                                    <strong>Charges:</strong>
                                    {{ number_format($loan->loan_commision, 2) }} |

                                    <strong>Insurance:</strong>
                                    {{ number_format($loan->loan_insurance, 2) }}

                                </p>

                                @php
                                    $effectiveFromPeriod = $data['period_from'];

                                    if (
                                        empty($effectiveFromPeriod) ||
                                        $effectiveFromPeriod === '000000' ||
                                        $loan->loan_taken_period > $effectiveFromPeriod
                                    ) {
                                        $effectiveFromPeriod = $loan->loan_taken_period;
                                    }

                                    $openingPaid = $data['loanOpeningBalances'][$loan->loan_id] ?? 0;
                                    $balance = $loan->loan_amount - $openingPaid;
                                    $loanPayments = $data['paymentsByLoan'][$loan->loan_id] ?? collect();
                                @endphp

                                <div class="table-responsive">
                                    <table class="table table-bordered table-sm mb-0">
                                        <thead class="bg-light">
                                            <tr>
                                                <th>#</th>
                                                <th>Period</th>
                                                <th>Date</th>
                                                <th>Doc No</th>
                                                <th>Description</th>
                                                <th class="text-end">Principal</th>
                                                <th class="text-end">Interest</th>
                                                <th class="text-end">Total</th>
                                                <th class="text-end">Balance</th>

                                                @if ($migrationMode)
                                                    <th class="text-center migration-action-col" data-export="ignore">
                                                        Action
                                                    </th>
                                                @endif
                                            </tr>
                                        </thead>

                                        <tbody>
                                            <tr class="table-secondary fw-bold">
                                                <td colspan="8" class="text-end">
                                                    Opening Balance as at {{ $effectiveFromPeriod }}
                                                </td>

                                                <td class="text-end">
                                                    {{ number_format($balance, 2) }}
                                                </td>

                                                @if ($migrationMode)
                                                    <td class="text-center migration-action-col" data-export="ignore">
                                                    </td>
                                                @endif
                                            </tr>

                                            @foreach ($loanPayments as $i => $p)
                                                @php
                                                    $balance -= $p->loan_payments_amount;
                                                @endphp

                                                <tr>
                                                    <td>{{ $i + 1 }}</td>
                                                    <td>{{ $p->loan_payments_period }}</td>
                                                    <td>{{ \Carbon\Carbon::parse($p->loan_payments_paid_on)->format('d-m-Y') }}
                                                    </td>
                                                    <td>{{ $p->loan_payments_docno }}</td>
                                                    <td>{{ $p->loan_payments_description }}</td>

                                                    <td class="text-end">
                                                        {{ number_format($p->loan_payments_amount, 2) }}
                                                    </td>

                                                    <td class="text-end">
                                                        {{ number_format($p->loan_payments_interest, 2) }}
                                                    </td>

                                                    <td class="text-end">
                                                        {{ number_format($p->loan_payments_amount + $p->loan_payments_interest, 2) }}
                                                    </td>

                                                    <td class="text-end fw-bold">
                                                        {{ number_format($balance, 2) }}
                                                    </td>

                                                    @if ($migrationMode)
                                                        <td class="text-center migration-action-col" data-export="ignore">
                                                            <form method="POST"
                                                                action="{{ route('migration.statement.payment.destroy', $p->loan_payments_id) }}"
                                                                class="migration-delete-form d-inline"
                                                                data-export="ignore"
                                                                onsubmit="return confirm('Delete this loan repayment? This cannot be undone.');">
                                                                @csrf
                                                                @method('DELETE')

                                                                <button type="submit" class="migration-delete-x"
                                                                    title="Delete repayment">
                                                                    &times;
                                                                </button>
                                                            </form>
                                                        </td>
                                                    @endif
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @empty
                            <div class="statement-empty-state">
                                No loans matched the selected statement filters.
                            </div>
                        @endforelse
                    </div>
                </div>
            @endif

        </div>

        <div class="statement-back-top">
            <a href="#statementPageTop">
                <i class="fas fa-arrow-up"></i>
                Back to top
            </a>
        </div>
    </div>

    {{-- ================= STYLES ================= --}}
    <script>
        const memberName = @json($data['member']->member_name);
    </script>
    <style>
        body {
            background: #f4f6f9;
            color: #1f2937;
        }

        .statement-header h2 {
            color: #1f2937;
        }

        .statement-sections {
            padding: 0 18px 24px 18px;
        }

        .statement-sections-inner {
            max-width: 100%;
        }

        .statement-sections .card {
            border: 1px solid #d9dee7;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            background: #ffffff;
        }

        .card-header {
            font-size: 0.95rem;
            letter-spacing: 0.2px;
        }

        .statement-sections table {
            border-collapse: collapse !important;
            width: 100%;
            margin-bottom: 0;
        }

        .statement-sections table th,
        .statement-sections table td {
            vertical-align: middle;
            border: 1px solid #dde3ec !important;
        }

        .statement-sections table th {
            font-weight: 700;
        }

        .statement-sections table th:nth-child(2),
        .statement-sections table td:nth-child(2),
        .statement-sections table th:nth-child(3),
        .statement-sections table td:nth-child(3) {
            white-space: nowrap !important;
        }

        .statement-sections table th.text-end,
        .statement-sections table td.text-end,
        .money-cell {
            white-space: nowrap !important;
            font-variant-numeric: tabular-nums;
        }

        .nowrap-cell {
            white-space: nowrap !important;
        }

        .table-sm th,
        .table-sm td {
            padding: 0.42rem 0.55rem;
        }

        .financial-section-card {
            border: 1px solid #cfd7e3 !important;
        }

        .financial-section-header {
            background: #172033 !important;
            color: #ffffff !important;
            padding: 14px 18px;
        }

        .financial-section-title {
            font-size: 0.98rem;
            font-weight: 800;
            letter-spacing: 0.3px;
        }

        .financial-section-subtitle {
            font-size: 0.74rem;
            opacity: 0.78;
            margin-top: 2px;
        }

        .financial-section-body {
            padding: 16px !important;
            background: #f8fafc;
        }

        .financial-product-panel {
            background: #ffffff;
            border: 1px solid #cfd7e3;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 16px;
        }

        .financial-product-header {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: center;
            padding: 14px 16px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            border-bottom: 1px solid #d9e0ea;
        }

        .financial-product-title {
            font-size: 1rem;
            font-weight: 800;
            color: #172033;
        }

        .financial-account-number {
            display: inline-block;
            margin-left: 8px;
            padding: 2px 8px;
            border-radius: 999px;
            background: #eef2f7;
            color: #334155;
            font-size: 0.74rem;
            font-weight: 700;
        }

        .financial-product-meta {
            margin-top: 5px;
            font-size: 0.78rem;
            color: #64748b;
        }

        .financial-status-badge {
            display: inline-block;
            margin-left: 4px;
            padding: 2px 9px;
            border-radius: 999px;
            background: #e8f7ee;
            color: #166534;
            font-weight: 800;
            font-size: 0.72rem;
        }

        .financial-closing-box {
            min-width: 190px;
            padding: 10px 14px;
            border-radius: 8px;
            background: #172033;
            color: #ffffff;
            text-align: right;
        }

        .financial-closing-box span {
            display: block;
            font-size: 0.68rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            opacity: 0.78;
        }

        .financial-closing-box strong {
            display: block;
            margin-top: 2px;
            font-size: 1.12rem;
            font-variant-numeric: tabular-nums;
        }

        .financial-summary-grid {
            display: grid;
            grid-template-columns: repeat(9, minmax(130px, 1fr));
            gap: 0;
            border-bottom: 1px solid #d9e0ea;
            overflow-x: auto;
        }

        .financial-summary-item {
            padding: 11px 12px;
            border-right: 1px solid #e2e8f0;
            background: #ffffff;
            min-width: 130px;
        }

        .financial-summary-item span {
            display: block;
            color: #64748b;
            font-size: 0.68rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.35px;
            line-height: 1.2;
        }

        .financial-summary-item strong {
            display: block;
            margin-top: 5px;
            color: #111827;
            font-size: 0.92rem;
            font-weight: 800;
            text-align: right;
            font-variant-numeric: tabular-nums;
        }

        .financial-summary-total {
            background: #f1f5f9;
        }

        .financial-summary-total strong {
            color: #0f172a;
            font-size: 1rem;
        }

        .financial-check-good strong {
            color: #15803d;
        }

        .financial-check-bad strong {
            color: #b91c1c;
        }

        .financial-formula-line {
            padding: 8px 12px;
            background: #f8fafc;
            border-bottom: 1px solid #d9e0ea;
            color: #475569;
            font-size: 0.75rem;
        }

        .financial-table-wrap {
            width: 100%;
            overflow-x: auto;
            background: #ffffff;
        }

        .financial-ledger-table {
            min-width: 1180px;
            font-size: 0.79rem;
        }

        .financial-ledger-table thead th {
            background: #273244 !important;
            color: #ffffff;
            border-color: #3b4658 !important;
            font-size: 0.72rem;
            text-transform: uppercase;
            letter-spacing: 0.35px;
        }

        .financial-ledger-table tbody td {
            background: #ffffff;
            color: #243041;
        }

        .financial-ledger-table tbody tr:nth-child(even) td {
            background: #f8fafc;
        }

        .financial-ledger-table tbody tr:hover td {
            background: #eef6ff;
        }

        .financial-opening-row td {
            background: #e5eaf1 !important;
            color: #111827;
        }

        .row-number-cell {
            text-align: center;
            color: #64748b;
            width: 42px;
        }

        .description-cell {
            min-width: 320px;
            max-width: 520px;
            line-height: 1.35;
        }

        .total-balance-cell {
            color: #0f172a;
        }

        .loan-box {
            background: #ffffff;
        }

        .loading-indicator {
            display: none;
            color: #6c63ff;
            font-weight: bold;
            margin-left: 10px;
        }

        .migration-delete-form {
            margin: 0;
            padding: 0;
        }

        .migration-delete-x {
            border: none;
            background: transparent;
            color: #dc3545;
            font-size: 1.35rem;
            font-weight: 900;
            line-height: 1;
            padding: 0 6px;
            cursor: pointer;
        }

        .migration-delete-x:hover {
            color: #9b0000;
            transform: scale(1.08);
        }

        .migration-action-col {
            width: 45px;
            min-width: 45px;
        }

        .export-mode .statement-back-top,
        .export-mode .migration-delete-form,
        .export-mode .migration-delete-x,
        .export-mode .migration-action-col {
            display: none !important;
        }


        .statement-header {
            flex-wrap: wrap;
        }

        .statement-header h2 {
            margin: 0;
            min-width: 0;
            word-break: break-word;
        }

        .statement-download-actions {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .statement-filter-card {
            border: 1px solid #d9dee7;
            border-radius: 10px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
            background: #ffffff;
        }

        .filter-group {
            padding: 0 0 18px 0;
            margin-bottom: 18px;
            border-bottom: 1px solid #e5e7eb;
        }

        .filter-group:last-of-type {
            margin-bottom: 0;
        }

        .filter-group-heading {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 14px;
        }

        .filter-step {
            flex: 0 0 28px;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #1f2937;
            color: #ffffff;
            font-size: 0.78rem;
            font-weight: 800;
        }

        .filter-title {
            font-size: 0.96rem;
            font-weight: 800;
            color: #111827;
            line-height: 1.25;
        }

        .filter-help {
            margin-top: 2px;
            font-size: 0.78rem;
            color: #6b7280;
        }

        .section-choice-grid {
            display: grid;
            grid-template-columns: repeat(5, minmax(150px, 1fr));
            gap: 10px;
        }

        .section-choice {
            display: flex;
            align-items: flex-start;
            gap: 9px;
            min-height: 70px;
            padding: 11px 12px;
            border: 1px solid #d9dee7;
            border-radius: 8px;
            background: #ffffff;
            cursor: pointer;
        }

        .section-choice:hover {
            border-color: #9ca3af;
            background: #f9fafb;
        }

        .section-choice .form-check-input {
            margin-top: 3px;
            flex: 0 0 auto;
        }

        .section-choice span {
            min-width: 0;
        }

        .section-choice strong,
        .section-choice small {
            display: block;
        }

        .section-choice strong {
            color: #111827;
            font-size: 0.86rem;
        }

        .section-choice small {
            margin-top: 3px;
            color: #6b7280;
            line-height: 1.25;
            font-size: 0.72rem;
        }

        .filter-checkbox-panel {
            display: grid;
            grid-template-columns: repeat(3, minmax(170px, 1fr));
            gap: 8px;
            max-height: 220px;
            overflow-y: auto;
            padding: 10px;
            border: 1px solid #d9dee7;
            border-radius: 8px;
            background: #f8fafc;
        }

        .filter-checkbox-item {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            padding: 9px 10px;
            border: 1px solid #e5e7eb;
            border-radius: 7px;
            background: #ffffff;
            cursor: pointer;
        }

        .filter-checkbox-item:hover {
            border-color: #9ca3af;
        }

        .filter-checkbox-item .form-check-input {
            margin-top: 3px;
            flex: 0 0 auto;
        }

        .filter-checkbox-item strong,
        .filter-checkbox-item small {
            display: block;
        }

        .filter-checkbox-item strong {
            color: #1f2937;
            font-size: 0.82rem;
            line-height: 1.25;
        }

        .filter-checkbox-item small {
            margin-top: 2px;
            color: #6b7280;
            font-size: 0.7rem;
        }

        .filter-empty-state,
        .statement-empty-state {
            padding: 14px 16px;
            color: #6b7280;
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            font-size: 0.84rem;
        }

        .statement-filter-actions {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            padding-top: 18px;
        }

        .statement-jump-nav {
            display: flex;
            align-items: center;
            gap: 8px;
            overflow-x: auto;
            white-space: nowrap;
            padding: 0 2px 12px 2px;
            margin-top: -4px;
            -webkit-overflow-scrolling: touch;
        }

        .statement-jump-label {
            color: #6b7280;
            font-size: 0.76rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.35px;
        }

        .statement-jump-nav a {
            display: inline-block;
            padding: 6px 10px;
            border: 1px solid #d9dee7;
            border-radius: 999px;
            background: #ffffff;
            color: #334155;
            text-decoration: none;
            font-size: 0.78rem;
            font-weight: 700;
        }

        .statement-jump-nav a:hover {
            background: #f8fafc;
            color: #111827;
        }

        .statement-period-line {
            color: #475569;
            font-size: 0.8rem;
        }

        .statement-section-card {
            scroll-margin-top: 18px;
        }

        .statement-table-wrap {
            width: 100%;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        .statement-ledger-table {
            min-width: 850px;
        }

        .fosa-type-block+.fosa-type-block {
            border-top: 10px solid #f4f6f9;
        }

        .fosa-type-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 10px 12px;
            background: #4b5563;
            color: #ffffff;
        }

        .fosa-type-prefix {
            display: inline-block;
            margin-left: 6px;
            padding: 2px 7px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.14);
            font-size: 0.7rem;
            font-weight: 800;
        }

        .fosa-type-caption {
            opacity: 0.78;
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 0.35px;
        }

        .statement-back-top {
            display: flex;
            justify-content: flex-end;
            margin-top: -10px;
            margin-bottom: 24px;
        }

        .statement-back-top a {
            color: #475569;
            text-decoration: none;
            font-size: 0.78rem;
            font-weight: 700;
        }

        .export-mode .statement-table-wrap,
        .export-mode .financial-table-wrap,
        .export-mode .table-responsive {
            overflow: visible !important;
        }

        @media (max-width: 991.98px) {
            .section-choice-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .filter-checkbox-panel {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .financial-product-header {
                align-items: stretch;
                flex-direction: column;
            }

            .financial-closing-box {
                width: 100%;
                min-width: 0;
                text-align: left;
            }
        }

        @media (max-width: 767.98px) {
            body {
                background: #ffffff;
            }

            .statement-header {
                align-items: stretch !important;
                margin-bottom: 16px !important;
            }

            .statement-header h2 {
                width: 100%;
                font-size: 1.2rem;
                line-height: 1.3;
            }

            .statement-download-actions {
                width: 100%;
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }

            .statement-download-actions .btn {
                width: 100%;
                margin: 0 !important;
            }

            .loading-indicator {
                grid-column: 1 / -1;
                margin-left: 0;
                padding: 5px 0;
                font-size: 0.78rem;
            }

            .statement-filter-card .card-body {
                padding: 14px;
            }

            .section-choice-grid,
            .filter-checkbox-panel {
                grid-template-columns: 1fr;
            }

            .section-choice {
                min-height: 0;
            }

            .filter-checkbox-panel {
                max-height: 260px;
            }

            .statement-filter-actions {
                display: grid;
                grid-template-columns: 1fr;
            }

            .statement-filter-actions .btn {
                width: 100%;
            }

            .statement-sections {
                padding: 0 0 20px 0;
            }

            .statement-sections>.text-center {
                padding-left: 12px;
                padding-right: 12px;
            }

            .statement-sections .card {
                border-left: 0;
                border-right: 0;
                border-radius: 0;
                box-shadow: none;
            }

            .financial-section-body {
                padding: 10px !important;
            }

            .financial-product-panel {
                border-radius: 8px;
            }

            .financial-summary-grid {
                grid-template-columns: repeat(9, minmax(145px, 1fr));
            }

            .loan-box {
                padding: 10px !important;
            }

            .loan-box h6 {
                align-items: flex-start !important;
                gap: 8px;
            }

            .fosa-type-heading {
                align-items: flex-start;
                flex-direction: column;
                gap: 3px;
            }

            .statement-back-top {
                padding-right: 12px;
            }
        }

        @media (max-width: 479.98px) {
            .statement-download-actions {
                grid-template-columns: 1fr;
            }

            .filter-group {
                margin-bottom: 15px;
                padding-bottom: 15px;
            }

            .statement-jump-nav {
                padding-left: 10px;
                padding-right: 10px;
            }

            .financial-section-body {
                padding: 6px !important;
            }
        }

        @media print {

            #nonPrintable,
            .statement-back-top,
            .migration-delete-form,
            .migration-delete-x,
            .migration-action-col {
                display: none !important;
            }

            .statement-sections {
                padding: 0;
            }

            .financial-section-body {
                padding: 10px !important;
            }

            .financial-product-panel {
                box-shadow: none;
                page-break-inside: avoid;
            }
        }
    </style>

    <script>
        (function() {
            const form = document.getElementById('statementFilterForm');
            const periodFrom = document.getElementById('periodFrom');
            const periodTo = document.getElementById('periodTo');
            const specificLoan = document.getElementById('specificLoan');

            if (!form) {
                return;
            }

            function validStatementPeriod(value) {
                if (!/^\d{6}$/.test(value)) {
                    return false;
                }

                if (value === '000000' || value === '999999') {
                    return true;
                }

                const month = parseInt(value.substring(4, 6), 10);
                return month >= 1 && month <= 12;
            }

            function refreshSpecificLoanOptions() {
                if (!specificLoan) {
                    return;
                }

                const checkedLoanTypes = Array.from(
                    form.querySelectorAll('input[name="loan_types[]"]:checked')
                ).map(input => String(input.value));

                const selectedValue = specificLoan.value;

                Array.from(specificLoan.options).forEach(option => {
                    if (!option.value) {
                        option.hidden = false;
                        return;
                    }

                    const typeId = String(option.dataset.loanType || '');
                    option.hidden =
                        checkedLoanTypes.length > 0 &&
                        !checkedLoanTypes.includes(typeId);
                });

                const selectedOption = specificLoan.options[specificLoan.selectedIndex];

                if (selectedOption && selectedOption.hidden) {
                    specificLoan.value = '';
                } else {
                    specificLoan.value = selectedValue;
                }
            }

            form.querySelectorAll('input[name="loan_types[]"]').forEach(input => {
                input.addEventListener('change', refreshSpecificLoanOptions);
            });

            refreshSpecificLoanOptions();

            form.addEventListener('submit', function(event) {
                const fromValue = periodFrom ? periodFrom.value.trim() : '';
                const toValue = periodTo ? periodTo.value.trim() : '';

                if (!validStatementPeriod(fromValue)) {
                    event.preventDefault();
                    alert('Period From must be a valid YYYYMM value, or 000000 for the beginning.');
                    periodFrom.focus();
                    return;
                }

                if (!validStatementPeriod(toValue)) {
                    event.preventDefault();
                    alert('Period To must be a valid YYYYMM value, or 999999 for the latest available period.');
                    periodTo.focus();
                    return;
                }

                if (fromValue !== '000000' && toValue !== '999999' && fromValue > toValue) {
                    event.preventDefault();
                    alert('Period From cannot be later than Period To.');
                    periodFrom.focus();
                    return;
                }

                const selectedSections = form.querySelectorAll(
                    'input[name="sections[]"]:checked'
                );

                if (selectedSections.length === 0) {
                    event.preventDefault();
                    alert('Select at least one statement section.');
                }
            });
        })();
    </script>

    {{-- ================= JS FOR PDF EXPORT ================= --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <script>
        document.getElementById("downloadPDF").addEventListener("click", function() {
            if (!window.jspdf || typeof window.jspdf.jsPDF !== "function" || typeof html2canvas !== "function") {
                alert("PDF tools did not load. Please refresh the page and try again.");
                return;
            }

            const {
                jsPDF
            } = window.jspdf;
            const pdf = new jsPDF('l', 'mm', 'a4');
            const container = document.getElementById("printableArea");
            const btn = this;
            const loader = document.getElementById("loadingIndicator");

            btn.disabled = true;
            loader.style.display = "inline-block";
            container.classList.add("export-mode");

            html2canvas(container, {
                scale: 2,
                useCORS: true,
                backgroundColor: '#fff'
            }).then(canvas => {
                const imgData = canvas.toDataURL("image/jpeg", 0.5);
                const imgWidth = 297;
                const pageHeight = 210;
                const imgHeight = (canvas.height * imgWidth) / canvas.width;

                let heightLeft = imgHeight;
                let position = 10;

                pdf.addImage(imgData, 'JPEG', 0, position, imgWidth, imgHeight);

                while (heightLeft > pageHeight) {
                    position -= pageHeight;
                    pdf.addPage();
                    pdf.addImage(imgData, 'JPEG', 0, position, imgWidth, imgHeight);
                    heightLeft -= pageHeight;
                }

                const safeName = memberName.replace(/[^a-z0-9]+/gi, '_').toLowerCase();
                pdf.save(`${safeName}_member_statement.pdf`);
            }).catch(error => {
                console.error("PDF generation failed:", error);
                alert("PDF generation failed. Please refresh the page and try again.");
            }).finally(() => {
                container.classList.remove("export-mode");
                btn.disabled = false;
                loader.style.display = "none";
            });
        });
    </script>

    {{-- ================= JS FOR EXCEL EXPORT ================= --}}
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>

    <script>
        document.getElementById("downloadExcel").addEventListener("click", function() {
            const container = document.getElementById("printableArea");

            if (!container) {
                return alert("Statement area not found.");
            }

            if (typeof XLSX === "undefined" || !XLSX.utils) {
                return alert("Excel tools did not load. Please refresh the page and try again.");
            }

            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.aoa_to_sheet([]);
            let rowCursor = 0;

            const title = `Member Statement - ${memberName || ""}`.trim();

            XLSX.utils.sheet_add_aoa(ws, [
                [title]
            ], {
                origin: {
                    r: rowCursor,
                    c: 0
                }
            });

            rowCursor += 2;

            const blocks = extractExportBlocks(container);

            blocks.forEach(block => {
                if (block.type === "text") {
                    XLSX.utils.sheet_add_aoa(ws, [
                        [block.value]
                    ], {
                        origin: {
                            r: rowCursor,
                            c: 0
                        }
                    });

                    rowCursor += 1;
                }

                if (block.type === "table") {
                    const aoa = tableToAOAWithSpans(block.node);

                    XLSX.utils.sheet_add_aoa(ws, aoa, {
                        origin: {
                            r: rowCursor,
                            c: 0
                        }
                    });

                    rowCursor += aoa.length + 2;
                }
            });

            ws["!cols"] = autoFitCols(ws);
            XLSX.utils.book_append_sheet(wb, ws, "Statement");

            const safeName = (memberName || "member_statement")
                .replace(/[^a-z0-9]+/gi, "_")
                .toLowerCase();

            XLSX.writeFile(wb, `${safeName}_member_statement.xlsx`);
        });

        function extractExportBlocks(container) {
            const blocks = [];
            const seenText = new Set();

            const walker = document.createTreeWalker(container, NodeFilter.SHOW_ELEMENT, {
                acceptNode(node) {
                    if (node.tagName === "SCRIPT" || node.tagName === "STYLE") {
                        return NodeFilter.FILTER_REJECT;
                    }

                    if (node.getAttribute && node.getAttribute("data-export") === "ignore") {
                        return NodeFilter.FILTER_REJECT;
                    }

                    if (node.tagName === "TABLE") {
                        return NodeFilter.FILTER_ACCEPT;
                    }

                    const textTags = ["H1", "H2", "H3", "H4", "H5", "H6", "P", "DIV", "SPAN"];

                    if (textTags.includes(node.tagName)) {
                        return NodeFilter.FILTER_ACCEPT;
                    }

                    return NodeFilter.FILTER_SKIP;
                }
            });

            let node;

            while ((node = walker.nextNode())) {
                if (node.closest && node.closest("table") && node.tagName !== "TABLE") {
                    continue;
                }

                if (node.tagName === "TABLE") {
                    blocks.push({
                        type: "table",
                        node
                    });
                    walker.currentNode = node;
                    continue;
                }

                const text = (node.innerText || "")
                    .replace(/\u00A0/g, " ")
                    .replace(/\s+/g, " ")
                    .trim();

                if (!text) {
                    continue;
                }

                const hasTableDesc = node.querySelector && node.querySelector("table");

                if (hasTableDesc) {
                    continue;
                }

                if (seenText.has(text)) {
                    continue;
                }

                seenText.add(text);

                blocks.push({
                    type: "text",
                    value: text
                });
            }

            return blocks;
        }

        function tableToAOAWithSpans(table) {
            const rows = Array.from(table.querySelectorAll("tr"));
            const grid = [];
            const spanMap = {};

            rows.forEach((tr, r) => {
                grid[r] = grid[r] || [];

                let c = 0;

                while (spanMap[`${r},${c}`]) {
                    c++;
                }

                const cells = Array.from(tr.querySelectorAll("th,td")).filter(cell => {
                    return cell.getAttribute("data-export") !== "ignore";
                });

                cells.forEach(cell => {
                    while (spanMap[`${r},${c}`]) {
                        c++;
                    }

                    const text = (cell.innerText || "")
                        .replace(/\u00A0/g, " ")
                        .replace(/\s+/g, " ")
                        .trim();

                    const colspan = parseInt(cell.getAttribute("colspan") || "1", 10);
                    const rowspan = parseInt(cell.getAttribute("rowspan") || "1", 10);

                    grid[r][c] = text;

                    for (let cc = 1; cc < colspan; cc++) {
                        grid[r][c + cc] = "";
                    }

                    if (rowspan > 1) {
                        for (let rr = 1; rr < rowspan; rr++) {
                            for (let cc = 0; cc < colspan; cc++) {
                                spanMap[`${r + rr},${c + cc}`] = true;
                            }
                        }
                    }

                    c += colspan;
                });
            });

            const maxCols = Math.max(...grid.map(r => r.length));

            return grid.map(r => {
                const row = r.slice();

                while (row.length < maxCols) {
                    row.push("");
                }

                return row;
            });
        }

        function autoFitCols(ws) {
            const ref = ws["!ref"];

            if (!ref) {
                return [];
            }

            const range = XLSX.utils.decode_range(ref);
            const colWidths = [];

            for (let C = range.s.c; C <= range.e.c; ++C) {
                let maxLen = 10;

                for (let R = range.s.r; R <= range.e.r; ++R) {
                    const addr = XLSX.utils.encode_cell({
                        r: R,
                        c: C
                    });
                    const cell = ws[addr];

                    if (!cell || cell.v == null) {
                        continue;
                    }

                    maxLen = Math.max(maxLen, String(cell.v).length);
                }

                colWidths[C] = {
                    wch: Math.min(maxLen + 2, 70)
                };
            }

            return colWidths;
        }
    </script>
@endsection
