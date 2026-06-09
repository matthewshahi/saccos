<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Special Saving Statement</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #222; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ccc; padding: 6px; }
        th { background: #f3f3f3; }
        .text-end { text-align: right; }
    </style>
</head>
<body>
    <h3>Special Saving Statement</h3>
    <p>
        <strong>Member:</strong> {{ $record->member_name }}<br>
        <strong>Sacco No:</strong> {{ $record->member_sacco_id }}<br>
        <strong>Account:</strong> {{ $record->special_saving_account_number }}<br>
        <strong>Product:</strong> {{ $record->special_saving_product_name }}
    </p>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Type</th>
                <th>Doc No</th>
                <th>Description</th>
                <th>Principal</th>
                <th>Interest</th>
                <th>Amount</th>
                <th>Balance</th>
            </tr>
        </thead>
        <tbody>
            @foreach($transactions as $row)
                <tr>
                    <td>{{ $row->special_saving_transaction_date }}</td>
                    <td>{{ $row->special_saving_transaction_type }}</td>
                    <td>{{ $row->special_saving_transaction_doc_no }}</td>
                    <td>{{ $row->special_saving_transaction_description }}</td>
                    <td class="text-end">{{ number_format($row->special_saving_transaction_principal_amount, 2) }}</td>
                    <td class="text-end">{{ number_format($row->special_saving_transaction_interest_amount, 2) }}</td>
                    <td class="text-end">{{ number_format($row->special_saving_transaction_amount, 2) }}</td>
                    <td class="text-end">{{ number_format($row->special_saving_transaction_total_balance_after, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>