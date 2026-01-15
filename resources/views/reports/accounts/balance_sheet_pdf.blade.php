<!-- resources/views/reports/accounts/balance_sheet_pdf.blade.php -->
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
            margin-bottom: 18px;
        }

        th, td {
            border: 1px solid #000;
            padding: 5px;
        }

        th {
            background: #f0f0f0;
            font-weight: bold;
        }

        .right {
            text-align: right;
        }

        .section-header th {
            background: #e6e6e6;
            text-align: left;
            font-size: 11px;
        }

        .total-row {
            font-weight: bold;
            background: #fafafa;
        }

        .footer-note {
            font-size: 9px;
            text-align: center;
            margin-top: 25px;
            color: #555;
        }
    </style>
</head>
<body>

{{-- ================= HEADER ================= --}}
<h2>Balance Sheet</h2>
<h3>{{ config('app.name') }}</h3>

<p class="subtitle">
    As at <strong>{{ \Carbon\Carbon::parse($periodLabel ?? now())->format('d M Y') }}</strong><br>
    <em>
        This statement reflects the financial position at the close of the selected period,
        derived from transactions recorded within that period.
    </em>
</p>

{{-- ================= ASSETS ================= --}}
<table>
    <thead class="section-header">
        <tr>
            <th colspan="2">ASSETS</th>
        </tr>
        <tr>
            <th>Account</th>
            <th class="right">KES</th>
        </tr>
    </thead>
    <tbody>
        @foreach($assets as $a)
            @php
                $amount = ($a->debit ?? 0);
            @endphp
            @if($amount > 0)
                <tr>
                    <td>
                        {{ strtoupper($a->sub_account_name) }}<br>
                        <small>
                            {{ $a->main_account_code }}/{{ $a->sub_account_code }}
                        </small>
                    </td>
                    <td class="right">{{ number_format($amount, 2) }}</td>
                </tr>
            @endif
        @endforeach

        <tr class="total-row">
            <td>Total Assets</td>
            <td class="right">{{ number_format($totalAssets, 2) }}</td>
        </tr>
    </tbody>
</table>

{{-- ================= LIABILITIES ================= --}}
<table>
    <thead class="section-header">
        <tr>
            <th colspan="2">LIABILITIES</th>
        </tr>
        <tr>
            <th>Account</th>
            <th class="right">KES</th>
        </tr>
    </thead>
    <tbody>
        @foreach($liabilities as $l)
            @php
                $amount = ($l->credit ?? 0);
            @endphp
            @if($amount > 0)
                <tr>
                    <td>
                        {{ strtoupper($l->sub_account_name) }}<br>
                        <small>
                            {{ $l->main_account_code }}/{{ $l->sub_account_code }}
                        </small>
                    </td>
                    <td class="right">{{ number_format($amount, 2) }}</td>
                </tr>
            @endif
        @endforeach

        <tr class="total-row">
            <td>Total Liabilities</td>
            <td class="right">{{ number_format($totalLiabilities, 2) }}</td>
        </tr>
    </tbody>
</table>

{{-- ================= CAPITAL ================= --}}
<table>
    <thead class="section-header">
        <tr>
            <th colspan="2">CAPITAL</th>
        </tr>
        <tr>
            <th>Account</th>
            <th class="right">KES</th>
        </tr>
    </thead>
    <tbody>
        @foreach($capital as $c)
            @php
                $amount = ($c->credit ?? 0) > 0 ? $c->credit : ($c->debit ?? 0);
            @endphp
            @if($amount > 0)
                <tr>
                    <td>
                        {{ strtoupper($c->sub_account_name) }}
                    </td>
                    <td class="right">{{ number_format($amount, 2) }}</td>
                </tr>
            @endif
        @endforeach

        <tr class="total-row">
            <td>Total Capital</td>
            <td class="right">{{ number_format($totalCapital, 2) }}</td>
        </tr>

        <tr class="total-row">
            <td>Total Liabilities &amp; Capital</td>
            <td class="right">{{ number_format($totalRight, 2) }}</td>
        </tr>
    </tbody>
</table>

{{-- ================= FOOTER ================= --}}
<p class="footer-note">
    This Balance Sheet is generated from the official accounting ledger
    (<strong>sacco_accounts_trans</strong>) and is derived from the Trial Balance.
    Figures are presented in Kenya Shillings (KES).
</p>

</body>
</html>
