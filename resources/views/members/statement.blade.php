@extends('layouts.app')

@section('content')
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">

    {{-- ================= NON-PRINTABLE SECTION ================= --}}
    <div id="nonPrintable">
        <div class="statement-header d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold text-primary">
                Member Statement — {{ $data['member']->member_name }}
            </h2>
            <button id="downloadPDF" class="btn btn-primary">
                <i class="fas fa-file-pdf"></i> Download PDF
            </button>
            <span id="loadingIndicator" class="loading-indicator">
                <i class="fas fa-spinner fa-spin"></i> Generating PDF...
            </span>
        </div>

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
                            <option value="all" {{ request('cleared_loans', 'all') == 'all' ? 'selected' : '' }}>All
                            </option>
                            <option value="cleared" {{ request('cleared_loans') == 'cleared' ? 'selected' : '' }}>Cleared
                            </option>
                            <option value="uncleared" {{ request('cleared_loans') == 'uncleared' ? 'selected' : '' }}>
                                Uncleared</option>
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

        <!-- ✅ Member Info for PDF -->
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


        <div id="printableArea" class="statement-sections">

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
                                <td class="text-end fw-bold">{{ number_format($data['openingBalanceCapital'], 2) }}</td>
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
                                    <td class="text-end fw-bold">{{ number_format($total_capital, 2) }}</td>
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
                                <td class="text-end fw-bold">{{ number_format($data['openingBalanceShares'], 2) }}</td>
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
                                    <td class="text-end fw-bold">{{ number_format($total_shares, 2) }}</td>
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
                        {{-- TYPE HEADER --}}
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

                                        <td class="text-end fw-bold">{{ number_format($running, 2) }}</td>
                                    </tr>
                                @endforeach

                                {{-- SUBTOTAL --}}
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


            {{-- LOANS --}}
            <div class="card mb-5">
    <div class="card-header bg-danger text-white fw-bold">Loan Statement</div>
    <div class="card-body">

        @foreach ($data['loans'] as $loan)

            <div class="loan-box mb-4 p-3 border rounded">

                <h6 class="fw-bold text-danger">
                    {{ $loan->loan_type_name }} ({{ $loan->loan_id }}) — {{ $loan->loan_doc_no }}
                </h6>

                <p class="small mb-2">
                    <strong>Period Taken:</strong> {{ $loan->loan_taken_period }} |
                    <strong>Amount:</strong> Ksh {{ number_format($loan->loan_amount, 2) }} |
                    <strong>Paid:</strong> Ksh {{ number_format($loan->loan_loan_paid, 2) }} |
                    <strong>Commission:</strong> {{ number_format($loan->loan_commision, 2) }} |
                    <strong>Insurance:</strong> {{ number_format($loan->loan_insurance, 2) }}
                </p>

                @php
                    /**
                     * Determine effective opening period:
                     * - Loan cannot exist before loan_taken_period
                     * - Avoid displaying 000000
                     */
                    $effectiveFromPeriod = $data['period_from'];

                    if (
                        empty($effectiveFromPeriod) ||
                        $effectiveFromPeriod === '000000' ||
                        $loan->loan_taken_period > $effectiveFromPeriod
                    ) {
                        $effectiveFromPeriod = $loan->loan_taken_period;
                    }

                    // Sum of principal paid BEFORE effective period
                    $openingPaid = $data['loanOpeningBalances'][$loan->loan_id] ?? 0;

                    // Outstanding balance at opening
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
                            </tr>
                        </thead>
                        <tbody>

                            {{-- Opening balance row --}}
                            <tr class="table-secondary fw-bold">
                                <td colspan="8" class="text-end">
                                    Opening Balance as at {{ $effectiveFromPeriod }}
                                </td>
                                <td class="text-end">
                                    {{ number_format($balance, 2) }}
                                </td>
                            </tr>

                            @foreach ($loanPayments as $i => $p)

                                @php
                                    /**
                                     * loan_payments_amount is signed:
                                     * +ve = repayment → reduces outstanding
                                     * -ve = contra / loan increase → increases outstanding
                                     */
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
                                        {{ number_format(
                                            $p->loan_payments_amount + $p->loan_payments_interest,
                                            2
                                        ) }}
                                    </td>

                                    <td class="text-end fw-bold">
                                        {{ number_format($balance, 2) }}
                                    </td>
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

            @media print {
                #nonPrintable {
                    display: none !important;
                }
            }
        </style>

        {{-- ================= JS FOR PDF EXPORT ================= --}}
        <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
        <script>
            document.getElementById("downloadPDF").addEventListener("click", function() {
                const {
                    jsPDF
                } = window.jspdf;
                const pdf = new jsPDF('l', 'mm', 'a4');
                const container = document.getElementById("printableArea");
                const btn = this,
                    loader = document.getElementById("loadingIndicator");

                btn.disabled = true;
                loader.style.display = "inline-block";

                html2canvas(container, {
                    scale: 2,
                    useCORS: true,
                    backgroundColor: '#fff'
                }).then(canvas => {
                    const imgData = canvas.toDataURL("image/jpeg", 0.5);
                    const imgWidth = 297,
                        pageHeight = 210;
                    const imgHeight = (canvas.height * imgWidth) / canvas.width;
                    let heightLeft = imgHeight,
                        position = 10;
                    pdf.addImage(imgData, 'JPEG', 0, position, imgWidth, imgHeight);
                    while (heightLeft > pageHeight) {
                        position -= pageHeight;
                        pdf.addPage();
                        pdf.addImage(imgData, 'JPEG', 0, position, imgWidth, imgHeight);
                        heightLeft -= pageHeight;
                    }
                    // pdf.save("member_statement.pdf");
                    const safeName = memberName.replace(/[^a-z0-9]+/gi, '_').toLowerCase();
                    pdf.save(`${safeName}_member_statement.pdf`);

                }).finally(() => {
                    btn.disabled = false;
                    loader.style.display = "none";
                });
            });
        </script>
    @endsection
