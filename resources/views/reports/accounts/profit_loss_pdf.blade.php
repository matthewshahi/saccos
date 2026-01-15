<!-- resources/views/reports/accounts/profit_loss_pdf.blade.php -->
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: DejaVu Sans; font-size: 11px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 4px; }
        th { background: #eee; }
        .right { text-align: right; }
    </style>
</head>
<body>

<h3>Profit & Loss Statement</h3>
<p>Period: {{ $period }}</p>

<h4>Income</h4>
<table>
<thead>
<tr><th>Account</th><th class="right">KES</th></tr>
</thead>
<tbody>
@foreach($income as $row)
<tr>
<td>{{ $row['name'] }}</td>
<td class="right">{{ $row['amount'] }}</td>
</tr>
@endforeach
</tbody>
</table>

<h4>Expenses</h4>
<table>
<thead>
<tr><th>Account</th><th class="right">KES</th></tr>
</thead>
<tbody>
@foreach($expenses as $row)
<tr>
<td>{{ $row['name'] }}</td>
<td class="right">{{ $row['amount'] }}</td>
</tr>
@endforeach
</tbody>
</table>

<h4>
Net {{ $netProfit >= 0 ? 'Profit' : 'Loss' }}:
{{ number_format(abs($netProfit),2) }} KES
</h4>

</body>
</html>
