<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Active Loans Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #999; padding: 4px 6px; text-align: right; }
        th { background: #eee; text-align: center; }
        td:first-child, td:nth-child(2), td:nth-child(3), td:nth-child(4) { text-align: left; }
        h2 { text-align: center; }
    </style>
</head>
<body>
    <h2>Active Loans Report</h2>
    <table>
        <thead>
            <tr>
                <th>Loan#</th><th>Member</th><th>Phone</th><th>Company</th><th>Type</th>
                <th>Loan Amount</th><th>Balance</th><th>Monthly Principal</th><th>Expected Interest</th><th>Monthly Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $r)
            <tr>
                <td>{{ $r->loan_id }}</td>
                <td>{{ $r->member_name }}</td>
                <td>{{ $r->member_phone_no }}</td>
                <td>{{ $r->company_name }}</td>
                <td>{{ $r->loan_type_name }}</td>
                <td>{{ number_format($r->loan_amount,2) }}</td>
                <td>{{ number_format($r->current_balance,2) }}</td>
                <td>{{ number_format($r->loan_monthly_repayment_principal,2) }}</td>
                <td>{{ number_format($r->annual_rate,2) }}</td>
                <td>{{ number_format($r->loan_monthly_repayment_amount,2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>