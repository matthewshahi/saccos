@extends('layouts.app')

@section('content')
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
<div class="custom_text-right mb-3">
    <button id="downloadPDF" class="custom_btn-primary">
        <i class="fas fa-file-pdf"></i> Download Statement (PDF)
    </button>
    <span id="loadingIndicator" class="custom_loading-indicator">
        <i class="fas fa-spinner fa-spin"></i> Generating PDF...
    </span>
</div>
<div class="statement-container">
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Member Statement for {{ $data['member']->member_name }}</h1>
        <div class="header-part-right">
            <ul>
                @if(Auth::check())
                <!-- <li class="d-none d-sm-inline-block">{{ Auth::user()->member_name }}</li> -->
                @endif
                @if(isset($currentPeriod))
                <li class="d-none d-sm-inline-block"><a href="{{ route('admin.periods') }}">{{ $currentPeriod->period_name }}</a></li>
                @endif


                <li><i class="i-Full-Screen header-icon d-none d-sm-inline-block" data-fullscreen=""></i></li>
            </ul>

        </div>
    </div>
    <div class="separator-breadcrumb border-top"></div>

    <form method="get" action="{{ route('members.statement', ['id' => $data['member']->member_id]) }}">
        <fieldset class="border p-3 mb-4">
            <legend class="w-auto px-2">Filter Options</legend>
            <div class="form-row d-flex flex-wrap align-items-end">
                <div class="form-group col-lg-3 col-md-6 col-sm-12 mb-3">
                    <label for="period_from">Period From (YYYYMM)</label>
                    <input type="text" name="period_from" id="period_from" class="form-control" value="{{ request('period_from', '000000') }}">
                </div>
                <div class="form-group col-lg-3 col-md-6 col-sm-12 mb-3">
                    <label for="period_to">Period To (YYYYMM)</label>
                    <input type="text" name="period_to" id="period_to" class="form-control" value="999999" readonly>
                </div>
                <div class="form-group col-lg-3 col-md-6 col-sm-12 mb-3">
                    <label for="cleared_loans">Cleared Loans</label>
                    <select name="cleared_loans" id="cleared_loans" class="form-control">
                        <option value="all" {{ request('cleared_loans', 'all') == 'all' ? 'selected' : '' }}>All Loans</option>
                        <option value="cleared" {{ request('cleared_loans') == 'cleared' ? 'selected' : '' }}>Cleared Loans</option>
                        <option value="uncleared" {{ request('cleared_loans') == 'uncleared' ? 'selected' : '' }}>Uncleared Loans</option>
                    </select>
                </div>
                <div class="form-group col-lg-3 col-md-6 col-sm-12 mb-3">
                    <button type="submit" class="btn btn-primary w-100">Filter</button>
                </div>
            </div>
        </fieldset>
    </form>

    <!-- Capital Statement -->
    <h2>Share Capital Statement</h2>
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th colspan="8"><strong>Member Share Capital Statement</strong></th>
                </tr>
                <tr>
                    <th>&nbsp;</th>
                    <th><strong>Period</strong></th>
                    <th><strong>Date</strong></th>
                    <th><strong>Description</strong></th>
                    <th><strong>Doc. No.</strong></th>
                    <th align="right"><strong>Debit</strong></th>
                    <th align="right"><strong>Credit</strong></th>
                    <th align="right"><strong>Total</strong></th>
                </tr>
                <tr>
                    <th>&nbsp;</th>
                    <th>&nbsp;</th>
                    <th>&nbsp;</th>
                    <th colspan="4">Opening Balance</th>
                    <th align="right">{{ number_format($data['openingBalanceCapital'], 2) }}</th>
                </tr>
            </thead>
            <tbody>
                @php $total_capital = $data['openingBalanceCapital']; @endphp
                @foreach($data['capitalContributions'] as $index => $contribution)
                @php
                $total_capital += $contribution->share_capitalamount_paying;
                @endphp
                <tr>
                    <td>{{ $index + 1 }}.</td>
                    <td>{{ $contribution->share_capitalperiod }}</td>
                    <td style="white-space: nowrap;">{{ \Carbon\Carbon::parse($contribution->share_capitaldate_paid)->format('d-m-Y') }}</td>
                    <td>{{ $contribution->share_capitaldescription }}</td>
                    <td>{{ $contribution->share_capitaldoc_no }}</td>
                    <td align="right">
                        @if($contribution->share_capitalamount_paying < 0)
                            {{ number_format(-$contribution->share_capitalamount_paying, 2) }}
                            @endif
                            </td>
                    <td align="right">
                        @if($contribution->share_capitalamount_paying > 0)
                        {{ number_format($contribution->share_capitalamount_paying, 2) }}
                        @endif
                    </td>
                    <td align="right">{{ number_format($total_capital, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Share Statement -->
    <h2>Deposit Statement</h2>
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th colspan="8"><strong>Member Deposit Statement</strong></th>
                </tr>
                <tr>
                    <th>&nbsp;</th>
                    <th><strong>Period</strong></th>
                    <th><strong>Date</strong></th>
                    <th><strong>Description</strong></th>
                    <th><strong>Doc. No.</strong></th>
                    <th align="right"><strong>Debit</strong></th>
                    <th align="right"><strong>Credit</strong></th>
                    <th align="right"><strong>Total</strong></th>
                </tr>
                <tr>
                    <th>&nbsp;</th>
                    <th>&nbsp;</th>
                    <th>&nbsp;</th>
                    <th colspan="4">Opening Balance</th>
                    <th align="right">{{ number_format($data['openingBalanceShares'], 2) }}</th>
                </tr>
            </thead>
            <tbody>
                @php $total_shares = $data['openingBalanceShares']; @endphp
                @foreach($data['shareContributions'] as $index => $contribution)
                @php
                $total_shares += $contribution->share_amount_paying;
                @endphp
                <tr>
                    <td>{{ $index + 1 }}.</td>
                    <td>{{ $contribution->share_period }}</td>
                    <td style="white-space: nowrap;">{{ \Carbon\Carbon::parse($contribution->share_date_paid)->format('d-m-Y') }}</td>
                    <td>{{ $contribution->share_description }}</td>
                    <td>{{ $contribution->share_doc_no }}</td>
                    <td align="right">
                        @if($contribution->share_amount_paying < 0)
                            {{ number_format(-$contribution->share_amount_paying, 2) }}
                            @endif
                            </td>
                    <td align="right">
                        @if($contribution->share_amount_paying > 0)
                        {{ number_format($contribution->share_amount_paying, 2) }}
                        @endif
                    </td>
                    <td align="right">{{ number_format($total_shares, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- FOSA Statement -->
    <h2>FOSA Statement</h2>
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th colspan="8"><strong>FOSA Statement</strong></th>
                </tr>
                <tr>
                    <th>&nbsp;</th>
                    <th><strong>Period</strong></th>
                    <th><strong>Date</strong></th>
                    <th><strong>Description</strong></th>
                    <th><strong>Doc. No.</strong></th>
                    <th align="right"><strong>Debit</strong></th>
                    <th align="right"><strong>Credit</strong></th>
                    <th align="right"><strong>Total</strong></th>
                </tr>
            </thead>
            <tbody>
                @php $total_fosas = 0; @endphp
                @foreach($data['fosaContributions'] as $index => $contribution)
                @php
                $total_fosas += $contribution->fosa_amount_paying;
                @endphp
                <tr>
                    <td align="right">{{ $index + 1 }}.</td>
                    <td>{{ $contribution->fosa_period }}</td>
                    <td style="white-space: nowrap;">{{ \Carbon\Carbon::parse($contribution->fosa_date_paid)->format('d-m-Y') }}</td>
                    <td>{{ $contribution->fosa_description }}</td>
                    <td>{{ $contribution->fosa_doc_no }}</td>
                    <td align="right">
                        @if($contribution->fosa_amount_paying < 0)
                            {{ number_format(-$contribution->fosa_amount_paying, 2) }}
                            @endif
                            </td>
                    <td align="right">
                        @if($contribution->fosa_amount_paying > 0)
                        {{ number_format($contribution->fosa_amount_paying, 2) }}


                        @endif
                    </td>
                    <td align="right">{{ number_format($total_fosas, 2) }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <!-- Loan Statement -->
    <h2>Loan Statement</h2>
    @foreach($data['loans'] as $loan)
    <div class="table-responsive">
        <table class="table table-sm table-hover">
            <thead>
                <tr>
                    <th colspan="8">
                        <strong>{{ $loan->loan_type_name }} ({{ $loan->loan_id }})<br />{{ $loan->loan_category_name }}</strong>
                    </th>
                </tr>
                <tr>
                    <th><strong>Loan taken:</strong><br />{{ number_format($loan->loan_amount, 2) }}</th>
                    <th><strong>Loan Paid:</strong><br />{{ number_format($loan->loan_loan_paid, 2) }}</th>
                    <th><strong>Commission:</strong><br />{{ number_format($loan->loan_commision, 2) }}</th>
                    <th><strong>Insurance:</strong><br />{{ number_format($loan->loan_insurance, 2) }}</th>
                    <th><strong>Period taken:</strong><br />{{ $loan->loan_taken_period }}</th>
                    <th><strong>Description:</strong><br />{{ $loan->loan_description }}</th>
                    <th><strong>Doc No.:</strong><br />{{ $loan->loan_doc_no }}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td colspan="8">
                        <table width="100%" border="0" cellspacing="0" cellpadding="0">
                            <thead>
                                <tr class="row-a">
                                    <td>&nbsp;</td>
                                    <td>Period</td>
                                    <td>Date</td>
                                    <td>Doc. No.</td>
                                    <td>Description</td>
                                    <td align="right">Principal</td>
                                    <td align="right">Interest</td>
                                    <td align="right">Total Paid</td>
                                    <td align="right">Balance</td>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                $new_balance = $loan->loan_amount;
                                $loanPayments = $data['paymentsByLoan'][$loan->loan_id] ?? collect();
                                @endphp
                                @if($loan->loan_taken_period < $data['period_from'])
                                    @php
                                    $priorPayments=$loanPayments->filter(function ($payment) use ($data) {
                                    return $payment->loan_payments_period < $data['period_from'];
                                        });
                                        $sum_loan_payments_amount=$priorPayments->sum('loan_payments_amount');
                                        $sum_loan_payments_interest = $priorPayments->sum('loan_payments_interest');
                                        $new_balance -= $sum_loan_payments_amount;
                                        @endphp
                                        <tr class="row-b" style="border-bottom: 1px solid #ddd;">
                                            <td>&nbsp;</td>
                                            <td>&nbsp;</td>
                                            <td colspan="3">Total paid as at {{ $data['period_from'] }}</td>
                                            <td align="right">{{ number_format($sum_loan_payments_amount, 2) }}</td>
                                            <td align="right">{{ number_format($sum_loan_payments_interest, 2) }}</td>
                                            <td align="right">{{ number_format($sum_loan_payments_amount + $sum_loan_payments_interest, 2) }}</td>
                                            <td align="right">{{ number_format($new_balance, 2) }}</td>
                                        </tr>
                                        @endif

                                        @foreach($loanPayments->filter(function ($payment) use ($data) {
                                        return $payment->loan_payments_period >= $data['period_from'];
                                        }) as $index => $payment)
                                        @php
                                        $new_balance -= $payment->loan_payments_amount;
                                        @endphp
                                        <tr style="border-bottom: 1px solid #ddd;">
                                            <td>{{ $index + 1 }}.&nbsp;</td>
                                            <td>{{ $payment->loan_payments_period }}&nbsp;</td>
                                            <td style="white-space: nowrap;">{{ \Carbon\Carbon::parse($payment->loan_payments_paid_on)->format('d-m-Y') }}&nbsp;</td>
                                            <td>{{ $payment->loan_payments_docno }}</td>
                                            <td>{{ $payment->loan_payments_description }}</td>
                                            <td align="right">&nbsp;{{ number_format($payment->loan_payments_amount, 2) }}</td>
                                            <td align="right">&nbsp;{{ number_format($payment->loan_payments_interest, 2) }}</td>
                                            <td align="right">&nbsp;{{ number_format($payment->loan_payments_amount + $payment->loan_payments_interest, 2) }}</td>
                                            <td align="right">&nbsp;{{ number_format($new_balance, 2) }}</td>
                                        </tr>
                                        @endforeach
                            </tbody>
                        </table>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    @endforeach
</div>

 
    <style>
        .statement-container {
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        /* Table Styling */
        .table {
            border-collapse: separate;
            border-spacing: 0 5px; /* Adds spacing between rows */
        }
        .table th, .table td {
            white-space: nowrap;
            vertical-align: middle;
            padding: 12px 15px; /* Ensures good spacing */
        }
        .table th {
            background-color: #f8f9fa; /* Light background for headers */
            font-weight: 600;
            text-align: left;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }
        .table-responsive {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }

        /* Mobile Adjustments */
        @media (max-width: 768px) {
            .table-responsive {
                margin-bottom: 20px;
            }
            .table th, .table td {
                padding: 10px;
                font-size: 14px; /* Reduce font size for smaller screens */
            }
            .statement-container {
                padding: 15px; /* Reduce padding on smaller screens */
            }
        }
        .custom_btn-primary {
    background-color: #663399 !important;
    border-color: #663399 !important;
    color: #fff !important;
    font-weight: bold;
    padding: 10px 20px;
    border-radius: 6px;
    transition: 0.3s;
}

.custom_btn-primary:hover {
    background-color: #562a77 !important;
    border-color: #562a77 !important;
}

.custom_text-right {
    text-align: right;
}

.custom_loading-indicator {
    display: none;
    margin-left: 10px;
    font-weight: bold;
    color: #663399;
}
    </style>
 <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
 <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

 <script>
   document.getElementById("downloadPDF").addEventListener("click", function () {
    const { jsPDF } = window.jspdf;
    const pdf = new jsPDF('l', 'mm', 'a4'); // 'l' -> Landscape, 'mm' -> Millimeters, 'a4' -> A4 Size

    let container = document.querySelector(".statement-container");
    let downloadButton = document.getElementById("downloadPDF");
    let loadingIndicator = document.getElementById("loadingIndicator");

    // Show Loading Indicator & Disable Button
    downloadButton.disabled = true;
    loadingIndicator.style.display = "inline-block";

    html2canvas(container, {
        scale: 2, // Keeps text sharp but avoids excessive size
        useCORS: true,
        willReadFrequently: true
    }).then(canvas => {
        let imgData = canvas.toDataURL("image/jpeg", 0.5); // 50% compression to reduce file size

        let imgWidth = 297; // A4 landscape width (mm)
        let pageHeight = 210; // A4 landscape height (mm)
        let imgHeight = (canvas.height * imgWidth) / canvas.width;

        let heightLeft = imgHeight;
        let position = 10;

        pdf.addImage(imgData, 'JPEG', 0, position, imgWidth, imgHeight);

        // Add pages if content exceeds one A4 page
        while (heightLeft > pageHeight) {
            position -= pageHeight;
            pdf.addPage();
            pdf.addImage(imgData, 'JPEG', 0, position, imgWidth, imgHeight);
            heightLeft -= pageHeight;
        }

        // Save the PDF
        pdf.save("shahi_services_financial_statement.pdf");

    }).catch(error => {
        alert("Error generating PDF: " + error);
    }).finally(() => {
        // Hide Loading Indicator & Re-enable Button
        downloadButton.disabled = false;
        loadingIndicator.style.display = "none";
    });
});
    </script>
@endsection