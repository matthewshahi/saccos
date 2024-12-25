@extends('layouts.app')

@section('content')
<div class="container mt-4">
     <!-- Session Messages -->
     @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if (session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    <div class="row">
        <div class="col-md-12">
            <div class="card bg-default text-black">
                <div class="card-body">
                    <h5 class="text-18 font-weight-700 card-title mb-3">Loan Repayments</h5>
                    
                    <!-- Search Form -->
                    <form action="{{ route('modify.member.loans') }}" method="GET">
                        <div class="input-group mb-3">
                            <input type="text" name="search" class="form-control" placeholder="Search by KRA PIN, Phone Number, Member ID, Company, or Loan Type..." value="{{ request('search') }}">
                            <button type="submit" class="btn btn-primary">Search</button>
                        </div>
                    </form>

                    <!-- Loan Table -->
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered text-nowrap">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Loan Type</th>
                                    <th class="text-end">Loan Amount</th>
                                    <th class="text-end">Loan Balance</th>
                                    <th>Loan Taken Period</th>
                                    <th>Loan Payment Period</th>
                                    <th>Loan Date</th>
                                    <th>Member Name</th>
                                    <th>Phone No</th>
                                    <th>KRA PIN</th>
                                    <th>Member ID</th>
                                    <th>Department</th>
                                    <th>Company</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($loans as $index => $loan)
                                    <tr>
                                        <td>{{ $index + $loans->firstItem() }}</td>
                                        <td>{{ $loan->loan_type_name ?? 'N/A' }}, {{ $loan->loan_id ?? 'N/A' }}</td>
                                        <td class="text-end">{{ number_format($loan->loan_amount ?? 0, 2) }}</td>
                                        <td class="text-end">{{ number_format(($loan->loan_amount ?? 0) - ($loan->loan_loan_paid ?? 0), 2) }}</td>
                                        <td>{{ $loan->loan_taken_period ?? 'N/A' }}</td>
                                        <td>{{ $loan->loan_payment_period ?? 'N/A' }}</td>
                                        <td>{{ $loan->loan_on ? \Carbon\Carbon::parse($loan->loan_on)->format('d/m/Y') : 'N/A' }}</td>
                                        <td>{{ $loan->member_name ?? 'N/A' }}</td>
                                        <td>{{ $loan->member_phone_no ?? 'N/A' }}</td>
                                        <td>{{ $loan->member_kra_pin ?? 'N/A' }}</td>
                                        <td>{{ $loan->loan_member ?? 'N/A' }}</td>
                                        <td>{{ $loan->department_name ?? 'N/A' }}</td>
                                        <td>{{ $loan->company_name ?? 'N/A' }}</td>
                                        <td>
                                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#updateLoanModal{{ $loan->loan_id }}">Update</button>
                                        </td>
                                    </tr>

                                    <!-- Modal for Updating Loan Payment -->
                                    <div class="modal fade" id="updateLoanModal{{ $loan->loan_id }}" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="{{ route('modify.member.loans.update') }}">
                @csrf
                <div class="modal-header">
                    <h5 class="modal-title">Update Loan Payment</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="loan_id" value="{{ $loan->loan_id }}">

                    <!-- Member Information -->
                    <div class="row mb-2">
                        <div class="col-4"><strong>Member Name</strong></div>
                        <div class="col-8">{{ $loan->member_name }}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-4"><strong>National ID</strong></div>
                        <div class="col-8">{{ $loan->member_national_id }}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-4"><strong>Phone Number</strong></div>
                        <div class="col-8">{{ $loan->member_phone_no }}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-4"><strong>KRA PIN</strong></div>
                        <div class="col-8">{{ $loan->member_kra_pin }}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-4"><strong>Loan Taken</strong></div>
                        <div class="col-8">{{ number_format($loan->loan_amount, 2) }}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-4"><strong>Loan ID</strong></div>
                        <div class="col-8">{{ $loan->loan_id }}</div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-4"><strong>Loan Balance</strong></div>
                        <div class="col-8">{{ number_format(($loan->loan_amount ?? 0) - ($loan->loan_loan_paid ?? 0), 2) }}</div>
                    </div>

                    <!-- Action Section -->
                    <div class="row mb-2">
                        <div class="col-4"><label class="form-label">Action*</label></div>
                        <div class="col-8">
                            <select name="action" id="action" class="form-control" required>
                                <option value="increase" selected>Increase Loan</option>
                                <option value="reduce">Reduce Loan</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-4"><label class="form-label">Amount*</label></div>
                        <div class="col-8">
                            <input type="number" name="amount_paid" class="form-control" step="0.01" min="0" required>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-4"><label class="form-label">Document No.*</label></div>
                        <div class="col-8">
                            <input type="text" name="document_no" class="form-control" required>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-4"><label class="form-label">Description*</label></div>
                        <div class="col-8">
                            <input type="text" name="description" class="form-control" required>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-4"><label class="form-label">Payment Account*</label></div>
                        <div class="col-8">
                            <select name="payment_account" class="form-control" required>
                                <option value="" disabled selected>Select Payment Account</option>
                                @foreach ($paymentAccounts as $account)
                                    <option value="{{ $account->sub_account_id }}">
                                        {{ $account->sub_account_name }} ({{ $account->main_account_code }}/{{ $account->sub_account_code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-4"><label class="form-label">Calculate Interest *</label></div>
                        <div class="col-8">
                            <select name="calculate_interest" id="calculate_interest" class="form-control" required>
                                <option value="Y" selected>Yes</option>
                                <option value="N">No</option>
                            </select>
                        </div>
                    </div>
                    <div class="row mb-2">
                        <div class="col-4"><label class="form-label">Transaction Date*</label></div>
                        <div class="col-8">
                            <input type="date" name="transaction_date" class="form-control" required value="{{ now()->toDateString() }}">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <p class="text-danger fw-bold">
                        This process is irreversible. Please confirm all values before proceeding.
                    </p>
                    <button type="submit" class="btn btn-success">Submit</button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>
                                @empty
                                    <tr>
                                        <td colspan="14" class="text-center">No loans found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="mt-3">
                        {{ $loans->links('pagination::bootstrap-4') }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
@endsection