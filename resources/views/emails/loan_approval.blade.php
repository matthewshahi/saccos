<!DOCTYPE html>
<html>
<head>
    <title>Loan Approval</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 0 auto;
            color: #333;
            line-height: 1.6;
        }
        .container {
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background-color: #f9f9f9;
        }
        .header {
            background-color: #0044cc;
            color: #fff;
            padding: 10px;
            border-radius: 5px 5px 0 0;
            text-align: center;
        }
        .footer {
            margin-top: 20px;
            font-size: 0.9em;
            color: #777;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h2>Loan Approved</h2>
        </div>
        <p>Dear {{ $loan->member_name }},</p>
        <p>Congratulations! Your loan has been approved. Here are the details:</p>
        <ul>
            <li><strong>Loan Type:</strong> {{ $loan->loan_type }}</li>
            <li><strong>Loan Amount:</strong> Ksh {{ number_format($loan->loan_amount, 2) }}</li>
            <li><strong>Repayment Period:</strong> {{ $loan->loan_taken_period }} months</li>
            <li><strong>Monthly Repayment:</strong> Ksh {{ number_format($loan->loan_monthly_repayment_amount, 2) }}</li>
        </ul>

        @if (!empty($saccoMail) && filter_var($saccoMail, FILTER_VALIDATE_EMAIL))
            <p>If you have any questions or need support, feel free to contact us at <a href="mailto:{{ $saccoMail }}">{{ $saccoMail }}</a>.</p>
        @endif

        <p>Thank you for choosing iSave Sacco!</p>
        <div class="footer">
            <p>&copy; 2024 iSave Sacco. All rights reserved.</p>
        </div>
    </div>
</body>
</html>