<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Period</th>
            <th>Date</th>
            <th>Account</th>
            <th>Account Name</th>
            <th>Doc No</th>
            <th>Description</th>
            <th>Debit</th>
            <th>Credit</th>
            <th>Reconciled</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($transactions as $index => $transaction)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td>{{ $transaction->accounts_trans_period }}</td>
                <td>{{ $transaction->accounts_trans_dat_date }}</td>
                <td>{{ $transaction->main_account_code }}/{{ $transaction->sub_account_code }}</td>
                <td>{{ $transaction->sub_account_name }}</td>
                <td>{{ $transaction->accounts_trans_doc_no }}</td>
                <td>{{ $transaction->accounts_trans_decription }}</td>
                <td>{{ $transaction->accounts_trans_debit }}</td>
                <td>{{ $transaction->accounts_trans_credit }}</td>
                <td>{{ $transaction->accounts_trans_reconsiled }}</td>
            </tr>
        @endforeach
    </tbody>
</table>