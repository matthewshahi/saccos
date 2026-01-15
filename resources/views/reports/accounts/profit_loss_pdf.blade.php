<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Balance Sheet</title>

    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
        }

        h2, h4 {
            text-align: center;
            margin: 0;
        }

        h4 {
            margin-top: 5px;
            font-weight: normal;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 12px;
        }

        th, td {
            border: 1px solid #000;
            padding: 6px;
        }

        th {
            background: #f0f0f0;
            text-align: left;
        }

        td.amount {
            text-align: right;
        }

        .section-title {
            background: #ddd;
            font-weight: bold;
        }

        .total-row {
            font-weight: bold;
            background: #f9f9f9;
        }
    </style>
</head>
<body>

    <h2>Balance Sheet</h2>
    <h4>{{ $periodLabel }}</h4>

    {{-- ASSETS --}}
    <table>
        <thead>
            <tr>
                <th colspan="2">ASSETS</th>
            </tr>
            <tr>
                <th>Description</th>
                <th class="amount">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($assets as $row)
                <tr>
                    <td>{{ $row->sub_account_name }}</td>
                    <td class="amount">
                        {{ number_format(($row->debit ?? 0) - ($row->credit ?? 0), 2) }}
                    </td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td>Total Assets</td>
                <td class="amount">{{ number_format($totalAssets, 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- LIABILITIES --}}
    <table>
        <thead>
            <tr>
                <th colspan="2">LIABILITIES</th>
            </tr>
            <tr>
                <th>Description</th>
                <th class="amount">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($liabilities as $row)
                <tr>
                    <td>{{ $row->sub_account_name }}</td>
                    <td class="amount">
                        {{ number_format(($row->credit ?? 0) - ($row->debit ?? 0), 2) }}
                    </td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td>Total Liabilities</td>
                <td class="amount">{{ number_format($totalLiabilities, 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- CAPITAL --}}
    <table>
        <thead>
            <tr>
                <th colspan="2">CAPITAL</th>
            </tr>
            <tr>
                <th>Description</th>
                <th class="amount">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($capital as $row)
                <tr>
                    <td>{{ $row->sub_account_name }}</td>
                    <td class="amount">
                        {{ number_format(($row->credit ?? 0) - ($row->debit ?? 0), 2) }}
                    </td>
                </tr>
            @endforeach
            <tr class="total-row">
                <td>Total Capital</td>
                <td class="amount">{{ number_format($totalCapital, 2) }}</td>
            </tr>
        </tbody>
    </table>

    {{-- BALANCING --}}
    <table>
        <tbody>
            <tr class="total-row">
                <td style="width: 80%">Total Liabilities + Capital</td>
                <td class="amount">{{ number_format($totalRight, 2) }}</td>
            </tr>
        </tbody>
    </table>

</body>
</html>
