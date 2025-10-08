<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Member Name</th>
            <th>FOSA Type</th>
            <th>Amount</th>
            <th>Description</th>
            <th>Doc No</th>
            <th>Period</th>
            <th>Date Paid</th>
            <th>Entered By</th>
        </tr>
    </thead>
    <tbody>
        @foreach($records as $i => $rec)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $rec->member_name }}</td>
                <td>{{ $rec->fosa_type_name }}</td>
                <td>{{ number_format($rec->fosa_amount_paying, 2) }}</td>
                <td>{{ $rec->fosa_description }}</td>
                <td>{{ $rec->fosa_doc_no }}</td>
                <td>{{ $rec->fosa_period }}</td>
                <td>{{ \Carbon\Carbon::parse($rec->fosa_date_paid)->format('d M Y') }}</td>
                <td>{{ $rec->entered_by_name }}</td>
            </tr>
        @endforeach
    </tbody>
</table>