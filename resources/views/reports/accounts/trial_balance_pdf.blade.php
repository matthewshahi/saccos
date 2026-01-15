<!DOCTYPE html>
<html>
<head>
    <style>
        body {
            font-size: 11px;
            font-family: DejaVu Sans;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #000;
            padding: 4px;
        }
        th {
            background: #eee;
            text-align: center;
        }
        .right {
            text-align: right;
        }
        .bold {
            font-weight: bold;
        }
    </style>
</head>
<body>

<h3 style="margin-bottom: 5px;">Trial Balance</h3>

<p style="margin-top: 0;">
    <strong>As at:</strong> {{ $period }}<br>
    <small>
        Based on transactions recorded within the selected period.
    </small>
</p>

<table>
    <thead>
        <tr>
            <th width="60%">Account</th>
            <th width="20%">Debit (KES)</th>
            <th width="20%">Credit (KES)</th>
        </tr>
    </thead>
    <tbody>
        @php
            $totalDebit = 0;
            $totalCredit = 0;
        @endphp

        @foreach($rows as $row)
            @php
                $d = (float) str_replace(',', '', $row['debit'] ?? 0);
                $c = (float) str_replace(',', '', $row['credit'] ?? 0);
                $totalDebit += $d;
                $totalCredit += $c;
            @endphp
            <tr>
                <td>{{ $row['name'] }}</td>
                <td class="right">{{ $row['debit'] }}</td>
                <td class="right">{{ $row['credit'] }}</td>
            </tr>
        @endforeach
    </tbody>

    <tfoot>
        <tr class="bold">
            <td class="right">TOTALS</td>
            <td class="right">{{ number_format($totalDebit, 2) }}</td>
            <td class="right">{{ number_format($totalCredit, 2) }}</td>
        </tr>
        <tr>
            <td colspan="3" class="bold" style="text-align: center;">
                @if(round($totalDebit,2) === round($totalCredit,2))
                    Trial Balance Balances
                @else
                    Out of Balance by {{ number_format(abs($totalDebit - $totalCredit), 2) }}
                @endif
            </td>
        </tr>
    </tfoot>
</table>

</body>
</html>
