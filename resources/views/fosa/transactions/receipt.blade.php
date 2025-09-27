<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>{{ $docType }} - {{ $docNo }}</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
    .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
    .header h1 { margin: 0; font-size: 28px; text-transform: uppercase; }
    .header h2 { margin: 5px 0; font-size: 18px; color: #555; }
    .receipt-title { text-align: center; margin: 20px 0; font-size: 20px; font-weight: bold; text-decoration: underline; }
    .info, .details { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    .info td, .details td, .details th { padding: 8px; border: 1px solid #ddd; font-size: 14px; }
    .details th { background: #f5f5f5; text-align: left; }
    .footer { text-align: center; margin-top: 30px; font-size: 13px; color: #777; }
    .amount { font-weight: bold; font-size: 16px; }
    .text-success { color: green; }
    .text-danger { color: red; }
    .btn-print { display: block; margin: 20px auto; padding: 10px 20px; background: #007bff; color: #fff; text-align: center; text-decoration: none; border-radius: 4px; }
    @media print { .btn-print { display: none; } }
  </style>
</head>
<body>
  <!-- Header -->
  <div class="header">
    <h1>{{ strtoupper($companyName) }}</h1>
    <h2>{{ $docType }}</h2>
  </div>

  <div class="receipt-title">{{ $docType }} #{{ $docNo }}</div>

  <!-- Member & Transaction Info -->
  <table class="info">
    <tr>
      <td><strong>Document No:</strong> {{ $record->fosa_doc_no }}</td>
      <td><strong>Date:</strong> {{ \Carbon\Carbon::parse($record->fosa_date_paid)->format('d M Y H:i') }}</td>
    </tr>
    <tr>
      <td><strong>Member Name:</strong> {{ $record->member_name }}</td>
      <td><strong>Member No:</strong> {{ $record->member_sacco_id ?? 'N/A' }}</td>
    </tr>
    <tr>
      <td><strong>Phone:</strong> {{ $record->member_phone_no ?? 'N/A' }}</td>
      <td><strong>National ID:</strong> {{ $record->member_national_id ?? 'N/A' }}</td>
    </tr>
  </table>

  <!-- Transaction Details -->
  <table class="details">
    <tr>
      <th>Transaction Type</th>
      <th>FOSA Type</th>
      <th>Description</th>
      <th>Amount</th>
    </tr>
    <tr>
      <td>
        @if($record->fosa_amount_paying >= 0)
          Deposit
        @else
          Withdrawal
        @endif
      </td>
      <td>{{ $record->fosa_type_name ?? 'N/A' }}</td>
      <td>{{ $record->fosa_description }}</td>
      <td class="amount">
        @if($record->fosa_amount_paying >= 0)
          <span class="text-success">+{{ number_format($record->fosa_amount_paying, 2) }}</span>
        @else
          <span class="text-danger">{{ number_format(abs($record->fosa_amount_paying), 2) }}</span>
        @endif
      </td>
    </tr>
  </table>

  <!-- Audit Info -->
  <table class="info">
    <tr>
      <td><strong>Paid By:</strong> {{ $record->fosa_paid_by }}</td>
      <td><strong>Entered By:</strong> {{ $record->entered_by_name ?? 'System' }}</td>
    </tr>
    <tr>
      <td colspan="2"><strong>IP Address:</strong> {{ $record->fosa_ip }}</td>
    </tr>
  </table>

  <!-- Footer -->
  <div class="footer">
    Thank you for banking with {{ $companyName }}.<br>
    This {{ strtolower($docType) }} is system-generated and valid without a signature.
  </div>

  <!-- Print Button -->
  <a href="javascript:window.print()" class="btn-print">🖨 Print {{ $docType }}</a>
</body>
</html>