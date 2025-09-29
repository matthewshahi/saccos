<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <title>Capital Share Receipt - {{ $rec->share_capitaldoc_no }}</title>
  <style>
    body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
    .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
    .header h1 { margin: 0; font-size: 26px; text-transform: uppercase; }
    .header h2 { margin: 5px 0; font-size: 18px; color: #555; }
    .receipt-title { text-align: center; margin: 20px 0; font-size: 20px; font-weight: bold; text-decoration: underline; }
    .info, .details { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    .info td, .details td, .details th { padding: 8px; border: 1px solid #ddd; font-size: 14px; }
    .details th { background: #f5f5f5; text-align: left; }
    .footer { text-align: center; margin-top: 30px; font-size: 13px; color: #777; }
    .amount { font-weight: bold; font-size: 16px; }
    .text-success { color: green; }
    .btn-print { display: block; margin: 20px auto; padding: 10px 20px; background: #007bff; color: #fff; text-align: center; text-decoration: none; border-radius: 4px; }
    @media print { .btn-print { display: none; } }
  </style>
</head>
<body>
  <!-- Header -->
  <div class="header">
    <h1>{{ strtoupper($companyName) }}</h1>
    <h2>Capital Share Receipt</h2>
  </div>

  <div class="receipt-title">Receipt #CAP-{{ $rec->share_capitalid }}</div>

 
  <table class="info">
    <tr>
      <td><strong>Document No:</strong> {{ $rec->share_capitaldoc_no }}  </td>
      <td><strong>Date:</strong> {{ \Carbon\Carbon::parse($rec->share_capitaldate_paid)->format('d M Y H:i') }}</td>
    </tr>
    <tr>
      <td><strong>Member Name:</strong> {{ $rec->member_name }}</td>
      <td><strong>Member No:</strong> {{ $rec->member_sacco_id ?? 'N/A' }}</td>
    </tr>
    <tr>
      <td><strong>Phone:</strong> {{ $rec->member_phone_no ?? 'N/A' }}</td>
      <td><strong>National ID:</strong> {{ $rec->member_national_id ?? 'N/A' }}</td>
    </tr>
    <tr>
      <td><strong>Period:</strong> {{ $rec->share_capitalperiod ?? '-' }}</td>
      <td><strong>Transaction Date:</strong> {{ \Carbon\Carbon::parse($rec->share_capitaltransdate)->format('d M Y H:i') }}</td>
    </tr>
  </table>

  <!-- Transaction Details -->
  <table class="details">
    <tr>
      <th>Description</th>
      <th>Amount</th>
    </tr>
    <tr>
      <td>{{ $rec->share_capitaldescription }}</td>
      <td class="amount text-success">
        {{ number_format($rec->share_capitalamount_paying, 2) }}
      </td>
    </tr>
  </table>

  <!-- Audit Info -->
  <table class="info">
    <tr>
      <td><strong>Paid By:</strong> {{ $rec->share_capitalpaid_by }}</td>
      <td><strong>Entered By:</strong> {{ $rec->entered_by_name ?? 'System' }}</td>
    </tr>
    <tr>
      <td colspan="2"><strong>IP Address:</strong> {{ $rec->share_capitalip }}</td>
    </tr>
  </table>

  <!-- Footer -->
  <div class="footer">
    Thank you for investing in {{ $companyName }}.<br>
    This receipt is system-generated and valid without a signature.
  </div>

  <!-- Print Button -->
  <a href="javascript:window.print()" class="btn-print">🖨 Print Receipt</a>
</body>
</html>