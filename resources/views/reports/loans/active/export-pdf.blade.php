<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Active Loans Report</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; }
        h3 { text-align: center; margin-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #555; padding: 6px; text-align: left; }
        th { background: #eee; }
    </style>
</head>
<body>
    <h3>Active Loans Report</h3>
    <table>
        <thead>
            <tr>
                <th>#</th>
                <th>Member</th>
                <th>Loan Type</th>
                <th>Amount</th>
                <th>Duration</th>
                <th>Monthly Repayment</th>
                <th>Expected Interest</th>
                <th>Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach($records as $index => $loan)
                <tr>
                    <td>{{ $index + 1 }}</td>
                    <td>{{ $loan->member_name }}</td>
                    <td>{{ $loan->loan_type_name }}</td>
                    <td>{{ number_format($loan->loan_amount, 2) }}</td>
                    <td>{{ $loan->loan_payment_period }}</td>
                    <td>{{ number_format($loan->loan_monthly_repayment_amount, 2) }}</td>
                    <td>{{ number_format($loan->loan_interest_payable, 2) }}</td>
                    <td>{{ number_format($loan->current_balance ?? 0, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>