@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-md-12 mb-3">
            <div class="card text-start">
                <div class="card-body">
                    <h4 class="card-title mb-3">Loan Performance Report</h4>
                    <p>Use the form below to search for loans based on various criteria.</p>
                    <form method="GET" action="{{ route('reports.sasra.loanperformance', ['version' => $version]) }}">
                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label for="search">Searchable Fields</label>
                                <input class="form-control" id="search" type="text" name="search" placeholder="Search by name, phone, ID, or PIN" value="{{ request('search') }}">
                            </div>
                            <div class="col-md-6">
                                <button class="btn btn-primary mt-4">Search</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-md-12 mb-3">
            <div class="card text-start">
                <div class="card-body">
                    <h4 class="card-title mb-3">Loans Issued</h4>
                    <p>Below is a table of loans issued, categorized by their status.</p>
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered nowrap" id="loanPerformanceTable" style="width:100%">
                            <thead>
                                <tr>
                                    <th scope="col">#</th>
                                    <th scope="col">Member Name</th>
                                    <th scope="col">Sacco ID</th>
                                    <th scope="col">National ID</th>
                                    <th scope="col">Phone No</th>
                                    <th scope="col">Position</th>
                                    <th scope="col">Loan Type</th>
                                    <th scope="col" class="text-right">Loan Amount</th>
                                    <th scope="col" class="text-right">Loan Paid</th>
                                    <th scope="col" class="text-right">Outstanding Balance</th>
                                    <th scope="col">Loan Taken Period</th>
                                    <th scope="col">Last Payment Period</th>
                                    <th scope="col">Loan Category</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($loans as $index => $loan)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $loan->member_name }}</td>
                                    <td>{{ $loan->member_sacco_id }}</td>
                                    <td>{{ $loan->member_national_id }}</td>
                                    <td>{{ $loan->member_phone_no }}</td>
                                    <td>{{ $loan->member_position == 2 ? 'Official' : 'Member' }}</td>
                                    <td>{{ $loan->loan_type_name }}</td>
                                    <td class="text-right">{{ number_format($loan->loan_amount, 2) }}</td>
                                    <td class="text-right">{{ number_format($loan->loan_loan_paid, 2) }}</td>
                                    <td class="text-right">{{ number_format($loan->loan_amount - $loan->loan_loan_paid, 2) }}</td>
                                    <td>{{ $loan->loan_taken_period }}</td>
                                    <td>{{ $loan->loan_payments_period }}</td>
                                    <td><span class="badge {{ $loan->loan_category['class'] }}">{{ $loan->loan_category['category'] }}</span></td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.11.3/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.0.1/js/dataTables.buttons.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.0.1/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.0.1/js/buttons.print.min.js"></script>
<script>
$(document).ready(function() {
    $('#loanPerformanceTable').DataTable({
        "paging": false,
        "ordering": true,
        "info": true,
        "searching": true,
        "scrollX": true,
        "autoWidth": false,
        "dom": 'Bfrtip', // Include buttons
        "buttons": [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ],
        "columnDefs": [
            { "orderable": false, "targets": 0 }, // Disable ordering on the first column (index)
            { "className": "text-right", "targets": [7, 8, 9] } // Right align currency columns
        ]
    });
});
</script>
@endsection
