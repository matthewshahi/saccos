<!-- resources/views/reports/accounts/balance_sheet_pdf.blade.php -->
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

<h3>Balance Sheet</h3>
<p>As at {{ $dateTo }}</p>

<h4>Assets</h4>
<table>
@foreach($assets as $a)
<tr>
<td>{{ $a->sub_account_name }}</td>
<td class="right">{{ number_format(($a->debit ?? 0) - ($a->credit ?? 0),2) }}</td>
</tr>
@endforeach
</table>

<h4>Liabilities & Capital</h4>
<table>
@foreach($rightSide as $r)
<tr>
<td>{{ $r->sub_account_name }}</td>
<td class="right">{{ number_format(($r->credit ?? 0) - ($r->debit ?? 0),2) }}</td>
</tr>
@endforeach
</table>

<h4>
Total Assets: {{ number_format($totalAssets,2) }} <br>
Total Liabilities + Capital: {{ number_format($totalRight,2) }}
</h4>

</body>
</html>
