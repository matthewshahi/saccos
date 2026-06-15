@extends('layouts.app')

@section('content')
    @php
        $migrationMode = ($data['migrationMode'] ?? 'N') === 'Y';
    @endphp

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    {{-- ================= NON-PRINTABLE SECTION ================= --}}
    <div id="nonPrintable">
        <div class="statement-header d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold text-primary">
                Member Statement — {{ $data['member']->member_name }}
            </h2>

            <div>
                <button id="downloadPDF" class="btn btn-primary">
                    <i class="fas fa-file-pdf"></i> Download PDF
                </button>

                <button id="downloadExcel" class="btn btn-success ms-2">
                    <i class="fas fa-file-excel"></i> Download Excel
                </button>

                <span id="loadingIndicator" class="loading-indicator">
                    <i class="fas fa-spinner fa-spin"></i> Generating PDF...
                </span>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger">
                {{ session('error') }}
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                @foreach($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @if($migrationMode)
            <div class="alert alert-warning">
                <strong>Migration Mode is ON.</strong> Red X buttons are enabled for deleting loans and loan repayments.
            </div>
        @endif

        <div class="card mb-4 p-3 bg-light">
            <form method="get" action="{{ route('members.statement', ['id' => $data['member']->member_id]) }}">
                <div class="row align-items-end">
                    <div class="col-md-3 mb-2">
                        <label class="form-label fw-bold">Period From (YYYYMM)</label>
                        <input type="text" name="period_from" class="form-control form-control-sm"
                               value="{{ request('period_from', '000000') }}">
                    </div>

                    <div class="col-md-3 mb-2">
                        <label class="form-label fw-bold">Period To (YYYYMM)</label>
                        <input type="text" name="period_to" class="form-control form-control-sm"
                               value="{{ request('period_to', '999999') }}">
                    </div>

                    <div class="col-md-3 mb-2">
                        <label class="form-label fw-bold">Loan Status</label>
                        <select name="cleared_loans" class="form-control form-control-sm">
                            <option value="all" {{ request('cleared_loans', 'all') == 'all' ? 'selected' : '' }}>
                                All
                            </option>
                            <option value="cleared" {{ request('cleared_loans') == 'cleared' ? 'selected' : '' }}>
                                Cleared
                            </option>
                            <option value="uncleared" {{ request('cleared_loans') == 'uncleared' ? 'selected' : '' }}>
                                Uncleared
                            </option>
                        </select>
                    </div>

                    <div class="col-md-3 mb-2">
                        <button type="submit" class="btn btn-success w-100">
                            <i class="fas fa-filter"></i> Apply Filter
                        </button>
                    </div>
                </div>
            </form>
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

            <p class="small text-muted">
                Email: {{ $data['member']->member_email ?? 'N/A' }} |
                Phone: {{ $data['member']->member_phone_no ?? 'N/A' }}
            </p>

            <hr>
        </div>

        <div class="statement-sections-inner">

            {{-- SHARE CAPITAL --}}
            <div class="card mb-4">
                <div class="card-header bg-primary text-white fw-bold">Share Capital Statement</div>

                <div class="card-body p-0">
                    <table class="table table-striped table-sm mb-0">
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
                                    <td>{{ \Carbon\Carbon::parse($r->share_capitaldate_paid)->format('d-m-Y') }}</td>
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

            {{-- MEMBER DEPOSITS --}}
            <div class="card mb-4">
                <div class="card-header bg-success text-white fw-bold">Deposit (Shares) Statement</div>

                <div class="card-body p-0">
                    <table class="table table-striped table-sm mb-0">
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

            {{-- FOSA --}}
            <div class="card mb-4">
                <div class="card-header bg-warning fw-bold">Other Contributions Statement (Grouped by Type)</div>

                <div class="card-body p-0">
                    @foreach ($data['fosaGrouped'] as $typeName => $rows)
                        <div class="bg-secondary text-white p-2 fw-bold">
                            {{ $typeName }}
                        </div>

                        <table class="table table-striped table-sm mb-0">
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
                            </thead>

                            <tbody>
                                @php $running = 0; @endphp

                                @foreach ($rows as $i => $r)
                                    @php $running += $r->fosa_amount_paying; @endphp

                                    <tr>
                                        <td>{{ $i + 1 }}</td>
                                        <td>{{ $r->fosa_period }}</td>
                                        <td>{{ \Carbon\Carbon::parse($r->fosa_date_paid)->format('d-m-Y') }}</td>
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
                                @endforeach

                                <tr class="table-secondary fw-bold">
                                    <td colspan="7" class="text-end">Subtotal for {{ $typeName }}</td>
                                    <td class="text-end">{{ number_format($running, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>

                        <br>
                    @endforeach
                </div>
            </div>

{{-- SPECIAL SAVINGS --}}
@if(isset($data['specialSavings']) && $data['specialSavings']->count() > 0)
    <div class="card mb-4">
        <div class="card-header bg-info text-white fw-bold">
            Special Savings Statement
        </div>

        <div class="card-body p-0">
            @foreach($data['specialSavings'] as $specialSaving)
                @php
                    $account = $specialSaving->account;
                    $transactions = $specialSaving->transactions;

                    $totalDebit = 0;
                    $totalCredit = 0;
                @endphp

                <div class="bg-secondary text-white p-2 fw-bold">
                    {{ $account->special_saving_product_name ?? 'Special Savings' }}
                    @if(!empty($account->special_saving_account_number))
                        — {{ $account->special_saving_account_number }}
                    @endif
                </div>

                <div class="p-2 small bg-light">
                    <strong>Status:</strong> {{ $account->special_saving_account_status }}
                    |
                    <strong>Principal:</strong> {{ number_format((float) $account->special_saving_account_principal_balance, 2) }}
                    |
                    <strong>Accrued Interest:</strong> {{ number_format((float) $account->special_saving_account_accrued_interest_balance, 2) }}
                    |
                    <strong>Available Interest:</strong> {{ number_format((float) $account->special_saving_account_available_interest_balance, 2) }}
                    |
                    <strong>Total:</strong> {{ number_format((float) $account->special_saving_account_total_balance, 2) }}
                </div>

                <table class="table table-striped table-sm mb-0">
                    <thead class="bg-light">
                        <tr>
                            <th>#</th>
                            <th>Period</th>
                            <th>Date</th>
                            <th>Description</th>
                            <th>Doc No</th>
                            <th>Type</th>
                            <th class="text-end">Debit</th>
                            <th class="text-end">Credit</th>
                            <th class="text-end">Principal Bal</th>
                            <th class="text-end">Interest Bal</th>
                            <th class="text-end">Total Bal</th>
                        </tr>

                        <tr class="table-secondary">
                            <td colspan="8" class="text-end fw-bold">Opening Balance</td>
                            <td class="text-end fw-bold">
                                {{ number_format((float) $specialSaving->opening_principal, 2) }}
                            </td>
                            <td class="text-end fw-bold">
                                {{ number_format((float) $specialSaving->opening_accrued_interest + (float) $specialSaving->opening_available_interest, 2) }}
                            </td>
                            <td class="text-end fw-bold">
                                {{ number_format((float) $specialSaving->opening_total, 2) }}
                            </td>
                        </tr>
                    </thead>

                    <tbody>
                        @foreach($transactions as $txn)
                            @php
                                $direction = strtoupper((string) $txn->special_saving_transaction_direction);
                                $amount = (float) $txn->special_saving_transaction_amount;

                                $debit = $direction === 'DEBIT' ? $amount : 0;
                                $credit = $direction === 'CREDIT' ? $amount : 0;

                                $totalDebit += $debit;
                                $totalCredit += $credit;

                                $interestBalance =
                                    (float) $txn->special_saving_transaction_accrued_interest_after
                                    + (float) $txn->special_saving_transaction_available_interest_after;
                            @endphp

                            <tr>
                                <td>{{ $loop->iteration }}</td>
                                <td>{{ $txn->special_saving_transaction_period }}</td>
                                <td>{{ \Carbon\Carbon::parse($txn->special_saving_transaction_date)->format('d-m-Y') }}</td>
                                <td>{{ $txn->special_saving_transaction_description }}</td>
                                <td>{{ $txn->special_saving_transaction_doc_no }}</td>
                                <td>{{ $txn->special_saving_transaction_type }}</td>

                                <td class="text-end">
                                    {{ $debit != 0 ? number_format($debit, 2) : '' }}
                                </td>

                                <td class="text-end">
                                    {{ $credit != 0 ? number_format($credit, 2) : '' }}
                                </td>

                                <td class="text-end fw-bold">
                                    {{ number_format((float) $txn->special_saving_transaction_principal_balance_after, 2) }}
                                </td>

                                <td class="text-end fw-bold">
                                    {{ number_format($interestBalance, 2) }}
                                </td>

                                <td class="text-end fw-bold">
                                    {{ number_format((float) $txn->special_saving_transaction_total_balance_after, 2) }}
                                </td>
                            </tr>
                        @endforeach

                        <tr class="table-secondary fw-bold">
                            <td colspan="6" class="text-end">
                                Subtotal for {{ $account->special_saving_product_name ?? 'Special Savings' }}
                            </td>
                            <td class="text-end">{{ number_format($totalDebit, 2) }}</td>
                            <td class="text-end">{{ number_format($totalCredit, 2) }}</td>
                            <td class="text-end">{{ number_format((float) $account->special_saving_account_principal_balance, 2) }}</td>
                            <td class="text-end">
                                {{ number_format((float) $account->special_saving_account_accrued_interest_balance + (float) $account->special_saving_account_available_interest_balance, 2) }}
                            </td>
                            <td class="text-end">{{ number_format((float) $account->special_saving_account_total_balance, 2) }}</td>
                        </tr>
                    </tbody>
                </table>

                <br>
            @endforeach
        </div>
    </div>
@endif
            {{-- LOANS --}}
            <div class="card mb-5">
                <div class="card-header bg-danger text-white fw-bold">Loan Statement</div>

                <div class="card-body">
                    @foreach ($data['loans'] as $loan)
                        <div class="loan-box mb-4 p-3 border rounded">

                            <h6 class="fw-bold text-danger d-flex justify-content-between align-items-center">
                                <span>
                                    {{ $loan->loan_type_name }} ({{ $loan->loan_id }}) — {{ $loan->loan_doc_no }}
                                </span>

                                @if($migrationMode)
                                    <form
                                        method="POST"
                                        action="{{ route('migration.statement.loan.destroy', $loan->loan_id) }}"
                                        class="migration-delete-form d-inline"
                                        data-export="ignore"
                                        onsubmit="return confirm('Delete this loan and all its repayments? This cannot be undone.');"
                                    >
                                        @csrf
                                        @method('DELETE')

                                        <button type="submit" class="migration-delete-x" title="Delete loan">
                                            &times;
                                        </button>
                                    </form>
                                @endif
                            </h6>

                            <p class="small mb-2">
                                <strong>Period Taken:</strong> {{ $loan->loan_taken_period }} |
                                <strong>Amount:</strong> Ksh {{ number_format($loan->loan_amount, 2) }} |
                                <strong>Paid:</strong> Ksh {{ number_format($loan->loan_loan_paid, 2) }} |
                                <strong>Commission:</strong> {{ number_format($loan->loan_commision, 2) }} |
                                <strong>Insurance:</strong> {{ number_format($loan->loan_insurance, 2) }}
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

                                            @if($migrationMode)
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

                                            @if($migrationMode)
                                                <td class="text-center migration-action-col" data-export="ignore"></td>
                                            @endif
                                        </tr>

                                        @foreach ($loanPayments as $i => $p)
                                            @php
                                                $balance -= $p->loan_payments_amount;
                                            @endphp

                                            <tr>
                                                <td>{{ $i + 1 }}</td>
                                                <td>{{ $p->loan_payments_period }}</td>
                                                <td>{{ \Carbon\Carbon::parse($p->loan_payments_paid_on)->format('d-m-Y') }}</td>
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

                                                @if($migrationMode)
                                                    <td class="text-center migration-action-col" data-export="ignore">
                                                        <form
                                                            method="POST"
                                                            action="{{ route('migration.statement.payment.destroy', $p->loan_payments_id) }}"
                                                            class="migration-delete-form d-inline"
                                                            data-export="ignore"
                                                            onsubmit="return confirm('Delete this loan repayment? This cannot be undone.');"
                                                        >
                                                            @csrf
                                                            @method('DELETE')

                                                            <button type="submit" class="migration-delete-x" title="Delete repayment">
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
                    @endforeach
                </div>
            </div>

        </div>
    </div>

    {{-- ================= STYLES ================= --}}
    <script>
        const memberName = @json($data['member']->member_name);
    </script>

    <style>
        body {
            background: #f7f9fc;
        }

        .statement-header h2 {
            color: #2c3e50;
        }

        .statement-sections .card {
            border-radius: 10px;
            overflow: hidden;
        }

        .card-header {
            font-size: 1rem;
            letter-spacing: 0.3px;
        }

        .table th,
        .table td {
            vertical-align: middle;
        }

        .table th {
            font-weight: 600;
        }

        .loan-box {
            background: #fffdfd;
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

        .export-mode .migration-delete-form,
        .export-mode .migration-delete-x,
        .export-mode .migration-action-col {
            display: none !important;
        }

        @media print {
            #nonPrintable,
            .migration-delete-form,
            .migration-delete-x,
            .migration-action-col {
                display: none !important;
            }
        }
    </style>

    {{-- ================= JS FOR PDF EXPORT ================= --}}
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

    <script>
        document.getElementById("downloadPDF").addEventListener("click", function () {
            const { jsPDF } = window.jspdf;
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
        document.getElementById("downloadExcel").addEventListener("click", function () {
            const container = document.getElementById("printableArea");

            if (!container) {
                return alert("printableArea not found.");
            }

            const wb = XLSX.utils.book_new();
            const ws = XLSX.utils.aoa_to_sheet([]);
            let rowCursor = 0;

            const title = `Member Statement - ${memberName || ""}`.trim();

            XLSX.utils.sheet_add_aoa(ws, [[title]], {
                origin: { r: rowCursor, c: 0 }
            });

            rowCursor += 2;

            const blocks = extractExportBlocks(container);

            blocks.forEach(block => {
                if (block.type === "text") {
                    XLSX.utils.sheet_add_aoa(ws, [[block.value]], {
                        origin: { r: rowCursor, c: 0 }
                    });

                    rowCursor += 1;
                }

                if (block.type === "table") {
                    const aoa = tableToAOAWithSpans(block.node);

                    XLSX.utils.sheet_add_aoa(ws, aoa, {
                        origin: { r: rowCursor, c: 0 }
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
                    blocks.push({ type: "table", node });
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

                blocks.push({ type: "text", value: text });
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
                    const addr = XLSX.utils.encode_cell({ r: R, c: C });
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