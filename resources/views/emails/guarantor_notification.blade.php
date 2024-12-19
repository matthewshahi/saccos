<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Guarantee Request</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            margin: 0;
            padding: 0;
            background-color: #f9f9f9;
        }
        .email-container {
            max-width: 600px;
            margin: 20px auto;
            background: #fff;
            border: 1px solid #ddd;
            padding: 20px;
            box-shadow: 0 2px 3px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
            color: #4CAF50;
        }
        .content {
            font-size: 16px;
        }
        .footer {
            margin-top: 20px;
            font-size: 12px;
            color: #777;
            text-align: center;
        }
        .btn {
            display: inline-block;
            padding: 10px 15px;
            color: #fff;
            background: #4CAF50;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 15px;
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>Loan Guarantee Request</h1>
        </div>
        <div class="content">
            <p>Dear Guarantor,</p>
            <p>You have been requested to guarantee a loan for <strong>{{ $loan->applicant_name }}</strong>.</p>
            <p><strong>Loan Details:</strong></p>
            <ul>
                <li>Loan Type: {{ $loan->loan_type_name }}</li>
                <li>Amount: {{ number_format($loan->batch_trans_loan_amount, 2) }}</li>
                <li>Amount Guaranteed: {{ number_format($loan->guarantors_amount_guaranteed, 2) }}</li>
            </ul>
            <p>Please log in to your account to review and approve the guarantor request.</p>
            <a href="{{ env('APP_URL') }}" class="btn">Log In to Approve</a>
        </div>
        <div class="footer">
            <p>This email was sent from {{ config('app.name') }}. If you have any questions, contact us at {{ config('mail.from.address') }}.</p>
        </div>
    </div>
</body>
</html>