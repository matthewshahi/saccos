{{-- resources/views/pdf/loan_form.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Loan Application Form</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; line-height: 1.4; }
        h2, h3 { text-align: center; margin: 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        td, th { border: 1px solid #000; padding: 6px; vertical-align: top; }
        .section-title { background: #f2f2f2; padding: 5px; font-weight: bold; margin-top: 15px; }
        .small { font-size: 10px; color: #555; }
        .page-break { page-break-after: always; }

        @page { margin: 60px 40px; }
        footer {
            position: fixed; 
            bottom: -30px; 
            left: 0; 
            right: 0;
            height: 40px; 
            text-align: center; 
            font-size: 10px;
            color: #555;
        }
        .pagenum:before { content: counter(page); }
        .total-pages:before { content: counter(pages); }
    </style>
</head>
<body>
    <h2>{{ $companyName ?? 'SACCO Ltd' }}</h2>
    <h3>Loan Application & Agreement Form</h3>
    <p class="small">Generated on {{ $today ?? '' }}</p>

    {{-- SECTION 1 --}}
    <div class="section-title">SECTION 1 – Applicant Details</div>
    <table>
        <tr><td>Name</td><td>{{ $loan->member_name ?? '-' }}</td></tr>
        <tr><td>Member No</td><td>{{ $loan->member_sacco_id ?? '-' }}</td></tr>
        <tr><td>National ID</td><td>{{ $loan->member_national_id ?? '-' }}</td></tr>
        <tr><td>Phone</td><td>{{ $loan->member_phone_no ?? '-' }}</td></tr>
        <tr><td>Payroll/Employee No</td><td>{{ $loan->batch_trans_payroll_number ?? '-' }}</td></tr>
        <tr><td>Designation</td><td>{{ $loan->batch_trans_present_designation ?? '-' }}</td></tr>
        <tr><td>Terms of Employment</td><td>{{ $loan->batch_trans_terms_of_employment ?? '-' }}</td></tr>
        <tr><td>Date of Application</td>
            <td>
                {{ isset($loan->batch_trans_on) ? \Carbon\Carbon::parse($loan->batch_trans_on)->format('d/m/Y H:i') : '-' }}
            </td>
        </tr>
        <tr>
            <td>Status</td>
            <td>
                @if(($loan->batch_trans_deleted ?? 'N') == 'Y')
                    Rejected
                @elseif(($loan->batch_trans_updated ?? 'N') == 'Y')
                    Proceeded / Approved
                @else
                    Pending
                @endif
            </td>
        </tr>
    </table>

    {{-- SECTION 2 --}}
    <div class="section-title">SECTION 2 – Loan Details</div>
    <table>
        <tr><td>Loan Type</td><td>{{ $loan->loan_type_name ?? '-' }}</td></tr>
        <tr><td>Loan Category</td><td>{{ $loan->loan_category_name ?? '-' }}</td></tr>
        <tr><td>Requested Amount</td><td>KES {{ number_format($loan->batch_trans_loan_amount ?? 0, 2) }}</td></tr>
        <tr><td>Repayment Period</td><td>{{ $loan->batch_trans_loan_duration ?? '-' }} months</td></tr>
        <tr><td>Purpose / Reason</td><td>{{ $loan->batch_trans_description ?? '-' }}</td></tr>
    </table>

    {{-- SECTION 3 --}}
    <div class="section-title">SECTION 3 – Guarantors</div>
    <table>
        <thead>
            <tr>
                <th>#</th><th>Name</th><th>Member No</th><th>Phone</th><th>Amount Guaranteed</th><th>Sign/Approval</th>
            </tr>
        </thead>
        <tbody>
            @forelse($guarantors as $i => $g)
            <tr>
                <td>{{ $i+1 }}</td>
                <td>{{ $g->member_name ?? '-' }}</td>
                <td>{{ $g->member_sacco_id ?? '-' }}</td>
                <td>{{ $g->member_phone_no ?? '-' }}</td>
                <td>{{ number_format($g->guarantors_amount_guaranteed ?? 0, 2) }}</td>
                <td class="small">
                    @if(($g->guarantors_approved ?? 'N') == 'Y')
                        Approved 
                        @if(isset($g->guarantor_created_at))
                            on {{ \Carbon\Carbon::parse($g->guarantor_created_at)->format('d/m/Y') }}
                        @endif
                    @else
                        Pending
                    @endif
                </td>
            </tr>
            @empty
            <tr><td colspan="6">No guarantors provided</td></tr>
            @endforelse
        </tbody>
    </table>

    {{-- SECTION 4 --}}
    <div class="section-title">SECTION 4 – Declaration by Applicant</div>
    <p>I declare that the information given above is true to the best of my knowledge. I hereby authorize the SACCO to deduct loan repayments from my income/savings as per agreed terms.</p>
    <p>Applicant Signature: ________________________ Date: ______________</p>

    {{-- SECTION 5 --}}
    <div class="section-title">SECTION 5 – Appraisal (For Official Use Only)</div>
    <p>Total Shares: __________ | Outstanding Loans: __________ | Requested Amount: __________</p>
    <p>Applicant’s Monthly Income: __________ | Deductions: __________ | Eligibility: Yes / No</p>
    <p>Appraised by: ________________________ Signature: __________ Date: __________</p>

    {{-- SECTION 6 --}}
    <div class="section-title">SECTION 6 – Credit Committee Decision</div>
    <p>In the committee meeting held on __________, the application was discussed and resolved as follows:</p>
    <p>a) Loan Approved: KES __________ Recoverable in __________ installments</p>
    <p>OR</p>
    <p>b) Loan Deferred / Rejected – Reasons:</p>
    <ol>
        <li>____________________________</li>
        <li>____________________________</li>
        <li>____________________________</li>
    </ol>
    <p>Committee Minute No: __________ Date: __________</p>
    <p>
        Chairman Signature: __________________ Date: __________ <br>
        Secretary Signature: _________________ Date: __________ <br>
        Member Signature: ___________________ Date: __________
    </p>

    <footer>
        This form was generated automatically from the SACCO Online Loan Application System.  
        No manual alterations are valid without official SACCO stamp & signature.  
        Page <span class="pagenum"></span> of <span class="total-pages"></span>
    </footer>
</body>
</html>