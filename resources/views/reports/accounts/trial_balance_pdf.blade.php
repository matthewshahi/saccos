<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-size: 11px; font-family: DejaVu Sans; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #000; padding: 4px; }
        th { background: #eee; }
        .right { text-align: right; }
    </style>
</head>
<body>

<h3>Trial Balance</h3>
<p>Period: {{ $period }}</p>

<table>
    <thead>
        <tr>
            <th>Account</th>
            <th>Debit</th>
            <th>Credit</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $row)
        <tr>
            <td>{{ $row['name'] }}</td>
            <td class="right">{{ $row['debit'] }}</td>
            <td class="right">{{ $row['credit'] }}</td>
        </tr>
        @endforeach
    </tbody>
</table>

</body>
</html>
