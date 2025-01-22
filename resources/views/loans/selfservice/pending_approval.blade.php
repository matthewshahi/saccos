@extends('layouts.app')

@section('content')
<div class="container">
    <!-- Page Title -->
    <div class="row mb-4"> 
        <div class="col-md-12">
            <h3 class="text-center">Loans Pending Approval</h3>
        </div>
    </div>
    
<!-- Success and Error Messages -->
@if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {!! session('success') !!}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{!! $error !!}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <!-- Search Bar -->
    <div class="row mb-3">
    <div class="col-md-12">
        <form method="GET" action="{{ route('loans.pending.approval') }}">
            <div class="row align-items-center">
                <!-- Search Input -->
                <div class="col-md-6 mb-2 mb-md-0">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control" placeholder="Search..." value="{{ request()->search }}">
                    </div>
                </div>
                <!-- Pending Loans Checkbox -->
                <div class="col-md-4 mb-2 mb-md-0">
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" id="pendingLoansOnly" name="pending" value="1" {{ request()->pending ? 'checked' : '' }}>
                        <label class="form-check-label" for="pendingLoansOnly">Pending Loans Only</label>
                    </div>
                </div>
                <!-- Submit Button -->
                <div class="col-md-2 text-md-end">
                    <button class="btn btn-primary w-100" type="submit">Search</button>
                </div>
            </div>
        </form>
    </div>
</div>

    <!-- Table Section -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table id="memberContributionsTable" class="table table-striped table-hover text-start">
                <thead class="table-light">
    <tr>
        <th>#</th>
        <th class="text-start">Applicant Name</th>
        <th class="text-start">Member Number</th>
        <th class="text-start">Phone</th>
        <th class="text-start">National ID</th>
        <th class="text-end">Loan Amount</th>
        <th>Loan Type</th>
        <th>Loan Category</th> <!-- Added Column -->
        <th class="text-end">EMI</th> <!-- Added Column -->
        <th class="text-end">Insurance</th> <!-- Added Column -->
        <th class="text-end">Commission</th> <!-- Added Column -->
        <th>Period to Pay</th> <!-- Added Column -->
        <th>Date Applied</th>
        <th>Updated</th>
        <th>Edit</th>
        <th>Actions</th>
    </tr>
</thead>
<tbody>
    <?php
    // dd($loans);
    ?>
    @forelse ($loans as $index => $loan)
        <tr>
            <td>{{ $loans->firstItem() + $index }}</td>
            <td class="text-start">{{ $loan->member_name }}</td>
            <td class="text-start">{{ $loan->member_sacco_id }}</td>
            <td class="text-start">{{ $loan->member_phone_no }}</td>
            <td class="text-start">{{ $loan->member_national_id }}</td>
            <td class="text-end">{{ number_format($loan->batch_trans_loan_amount, 2) }}</td>
            <td>{{ $loan->loan_type_name }}</td>
            <td>{{ $loan->loan_category_name }}</td>
            <td class="text-end">{{ number_format($loan->batch_trans_monthly_payment, 2) }}</td> <!-- EMI -->
            <td class="text-end">{{ number_format($loan->batch_trans_insurance, 2) }}</td> <!-- Insurance -->
            <td class="text-end">{{ number_format($loan->batch_trans_commission, 2) }}</td> <!-- Commission -->
            <td>{{ $loan->batch_trans_loan_duration }} months</td> <!-- Period to Pay -->
            <td>{{ \Carbon\Carbon::parse($loan->batch_trans_on)->format('d/m/Y') }}</td>
            <td>
                @if ($loan->batch_trans_deleted == 'Y')
                    <span class="badge bg-danger">Rejected</span>
                @elseif ($loan->batch_trans_updated == 'Y')
                    <span class="badge bg-success">Updated</span>
                @else
                    <span class="badge bg-warning">Pending</span>
                @endif
            </td>
            <td>
                <a href="{{ route('loans.pending.approval.selfedit', ['id' => $loan->batch_trans_id]) }}" class="btn btn-primary btn-sm">
                    Edit
                </a>
            </td>
            <td>
                <button class="btn btn-info btn-sm" data-bs-toggle="modal" data-bs-target="#loanModal{{ $loan->batch_trans_id }}">
                    Details
                </button>
            </td>
        </tr>
    @empty
        <tr>
            <td colspan="15" class="text-center">No loans pending approval.</td>
        </tr>
    @endforelse
</tbody>
                </table>
            </div>
            <!-- Pagination -->
            <div class="d-flex justify-content-center mt-3">
                {{ $loans->links('pagination::bootstrap-4') }}
            </div>
        </div>
    </div>

    <!-- Modals -->
    @foreach ($loans as $loan)
    <div class="modal fade" id="loanModal{{ $loan->batch_trans_id }}" tabindex="-1" aria-labelledby="loanModalLabel{{ $loan->batch_trans_id }}" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <!-- Modal Header -->
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="loanModalLabel{{ $loan->batch_trans_id }}">Loan Details - {{ $loan->member_name }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <!-- Modal Body -->
                <div class="modal-body">
                    <!-- Applicant Information -->
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <h6 class="text-primary">Applicant Information</h6>
                            <ul class="list-unstyled">
                                <li><strong>Name:</strong> {{ $loan->member_name }}</li>
                                <li><strong>Phone:</strong> {{ $loan->member_phone_no }}</li>
                                <li><strong>National ID:</strong> {{ $loan->member_national_id }}</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-primary">Loan Information</h6>
                            <ul class="list-unstyled">
                                <li><strong>Loan Amount:</strong> {{ number_format($loan->batch_trans_loan_amount, 2) }}</li>
                                <li><strong>Loan Type:</strong> {{ $loan->loan_type_name }}</li>
                                <li><strong>Duration:</strong> {{ $loan->batch_trans_loan_duration }} months</li>
                                <li><strong>Monthly Payment:</strong> {{ number_format($loan->batch_trans_monthly_payment, 2) }}</li>
                                <li><strong>Top-Up Amount:</strong> {{ number_format($loan->batch_trans_loan_to_top_up_amount, 2) }}</li>
                                <li><strong>Commission:</strong> {{ number_format($loan->batch_trans_commission, 2) }}</li>
                                <li><strong>Insurance:</strong> {{ number_format($loan->batch_trans_insurance, 2) }}</li>
                            </ul>
                        </div>
                    </div>

                    <!-- Application Details -->
                    <h6 class="text-primary">Application Details</h6>
                    <ul class="list-unstyled mb-3">
                        <li><strong>Applied On:</strong> {{ $loan->batch_trans_on }}</li>
                        <li><strong>IP Address:</strong> {{ $loan->batch_trans_ip }}</li>
                    </ul>

                    <!-- Guarantors Section -->
                    <h6 class="text-primary mb-3">Guarantors</h6>
                    <table class="table table-bordered table-sm">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Guarantor Name</th>
                                <th class="text-end">Amount Guaranteed</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        @php
    $guarantors_names = explode('|', $loan->guarantors_names ?? '');
    $guarantors_amounts = explode('|', $loan->guarantors_amounts ?? '');
    $guarantors_statuses = explode('|', $loan->guarantors_approval_status ?? '');
@endphp

@foreach ($guarantors_names as $index => $name)
<tr>
    <td>{{ $index + 1 }}</td>
    <td>{{ $name }}</td>
    <td class="text-end">
        {{ is_numeric($guarantors_amounts[$index] ?? null) ? number_format((float)$guarantors_amounts[$index], 2) : '0.00' }}
    </td>
    <td>
        <span class="badge {{ isset($guarantors_statuses[$index]) && $guarantors_statuses[$index] == 'Y' ? 'bg-success' : 'bg-warning' }}">
            {{ isset($guarantors_statuses[$index]) && $guarantors_statuses[$index] == 'Y' ? 'Accepted' : 'Pending' }}
        </span>
    </td>
</tr>
@endforeach

 
                            @if (empty($guarantors_names[0]))
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No guarantors available.</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>

                <!-- Modal Footer -->
                <div class="modal-footer">
                        @if ($loan->batch_trans_updated == 'N' && $loan->batch_trans_deleted != 'Y')
                            <!-- Approve Form -->
                            <form id="approveForm{{ $loan->batch_trans_id }}" action="{{ route('loans.approve', ['id' => $loan->batch_trans_id]) }}" method="POST" style="display: inline;">
                                @csrf
                                <button type="button" class="btn btn-success" onclick="confirmAction('approve', '{{ $loan->batch_trans_id }}')">Approve</button>
                            </form>

                            <!-- Reject Form -->
                            <form id="rejectForm{{ $loan->batch_trans_id }}" action="{{ route('loans.reject', ['id' => $loan->batch_trans_id]) }}" method="POST" style="display: inline;">
                                @csrf
                                <button type="button" class="btn btn-danger" onclick="confirmAction('reject', '{{ $loan->batch_trans_id }}')">Reject</button>
                            </form>
                        @else
                            <span class="text-muted">This application is closed or deleted.</span>
                        @endif
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>

            </div>
        </div>
    </div>
    @endforeach
</div>

<script>
function confirmAction(action, loanId) {
    let actionText = action === 'approve' ? 'Approve' : 'Reject';
    let confirmation = confirm(`Are you sure you want to ${actionText} this loan application? This action is irreversible.`);
    
    if (confirmation) {
        if (action === 'approve') {
            document.getElementById(`approveForm${loanId}`).submit();
        } else if (action === 'reject') {
            document.getElementById(`rejectForm${loanId}`).submit();
        }
    }
} 
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>


<link href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css" rel="stylesheet">

<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.flash.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
 
<script>
   $(document).ready(function() {
    $('#memberContributionsTable').DataTable({
        dom: 'Bfrtip', // Allows the buttons to be displayed
        buttons: [
            'copy', 'csv', 'excel', 'pdf', 'print'
        ],
        searching: false, // Disables the default search box
        paging: false, // Disables pagination
        lengthChange: false, // Disables the ability to change the number of rows per page
        order: [[0, 'asc']] // Optional: Orders by the first column
    });
});

    
</script>
@endsection