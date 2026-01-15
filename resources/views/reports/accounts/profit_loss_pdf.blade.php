<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 11px;
            color: #000;
        }

        h2, h3 {
            text-align: center;
            margin: 0;
        }

        .subtitle {
            text-align: center;
            font-size: 10px;
            margin-bottom: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        th, td {
            border: 1px solid #000;
            padding: 5px;
        }

        th {
            background: #f0f0f0;
            font-weight: bold;
            text-align: left;
        }

        .right {
            text-align: right;
        }

        .total-row {
            font-weight: bold;
            background: #fafafa;
        }

        .net-profit {
            font-size: 12px;
            font-weight: bold;
            text-align: right;
        }

        .footer-note {
            font-size: 9px;
            margin-top: 20px;
            text-align: center;
            color: #555;
        }
    </style>
</head>
<body>

<h2>Profit &amp; Loss Statement</h2>
<h3>{{ config('app.name') }}</h3>

<p class="subtitle">
    For the period:
    <strong>{{ $periodLabel }}</strong>
</p>
<p class="subtitle">
    For the period:
    <strong>{{ $periodLabel }}</strong>
</p>

<p class="subtitle" style="font-size:9px; margin-top:-5px;">
    This statement reflects income and expenses recorded during the selected period,
    based on posted ledger transactions. It is not a cumulative or year-to-date report
    unless the selected period spans those dates.
</p>

{{-- ================= INCOME ================= --}}
<table>
    <thead>
        <tr>
            <th colspan="2">INCOME</th>
        </tr>
        <tr>
            <th>Account</th>
            <th class="right">KES</th>
        </tr>
    </thead>
    <tbody>
        @foreach($income as $row)
            @php
                $amount = ($row->credit ?? 0);
            @endphp
            @if($amount > 0)
                <tr>
                    <td>
                        {{ strtoupper($row->sub_account_name) }}
                        <br>
                        <small>
                            {{ $row->main_account_code }}/{{ $row->sub_account_code }}
                        </small>
                    </td>
                    <td class="right">{{ number_format($amount, 2) }}</td>
                </tr>
            @endif
        @endforeach

        <tr class="total-row">
            <td>Total Income</td>
            <td class="right">{{ number_format($totalIncome, 2) }}</td>
        </tr>
    </tbody>
</table>

{{-- ================= EXPENSES ================= --}}
<table>
    <thead>
        <tr>
            <th colspan="2">EXPENSES</th>
        </tr>
        <tr>
            <th>Account</th>
            <th class="right">KES</th>
        </tr>
    </thead>
    <tbody>
        @foreach($expenses as $row)
            @php
                $amount = ($row->debit ?? 0);
            @endphp
            @if($amount > 0)
                <tr>
                    <td>
                        {{ strtoupper($row->sub_account_name) }}
                        <br>
                        <small>
                            {{ $row->main_account_code }}/{{ $row->sub_account_code }}
                        </small>
                    </td>
                    <td class="right">{{ number_format($amount, 2) }}</td>
                </tr>
            @endif
        @endforeach

        <tr class="total-row">
            <td>Total Expenses</td>
            <td class="right">{{ number_format($totalExpenses, 2) }}</td>
        </tr>
    </tbody>
</table>

{{-- ================= NET RESULT ================= --}}
<p class="net-profit">
    {{ $netProfit >= 0 ? 'Net Profit' : 'Net Loss' }}:
    {{ number_format(abs($netProfit), 2) }} KES
</p>

<p class="footer-note">
    This statement is generated from the official ledger records and is derived from the Trial Balance.
</p>

</body>
</html>
