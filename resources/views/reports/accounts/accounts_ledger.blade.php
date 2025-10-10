@extends('layouts.app')

@section('content')
<div class="container">
    <h3>Accounts Ledger</h3>

     <!-- Display Success or Error Messages -->
     @if (session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
 @include('includes.accounts_nav')
    <!-- Search and Filter Form -->
    <form method="GET" action="{{ route('reports.accounts.ledger') }}" class="mb-3">
        <div class="row align-items-end">
            <!-- Start Period -->
            <div class="col-lg-2 col-md-3 col-sm-6 mb-2">
                <label for="start_period" class="form-label">Start Period</label>
                <input type="text" id="start_period" name="start_period" class="form-control" placeholder="YYYYmm"
                    value="{{ request('start_period', '000000') }}">
            </div>
    
            <!-- End Period -->
            <div class="col-lg-2 col-md-3 col-sm-6 mb-2">
                <label for="end_period" class="form-label">End Period</label>
                <input type="text" id="end_period" name="end_period" class="form-control" placeholder="YYYYmm"
                    value="{{ request('end_period', '999999') }}">
            </div>
    
            <!-- Start Date -->
            <div class="col-lg-2 col-md-3 col-sm-6 mb-2">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" id="start_date" name="start_date" class="form-control" 
                    value="{{ request('start_date', now()->startOfMonth()->format('Y-m-d')) }}">
            </div>
    
            <!-- End Date -->
            <div class="col-lg-2 col-md-3 col-sm-6 mb-2">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" id="end_date" name="end_date" class="form-control" 
                    value="{{ request('end_date', now()->format('Y-m-d')) }}">
            </div>
    
            <!-- Search -->
            <div class="col-lg-3 col-md-4 col-sm-6 mb-2">
                <label for="search" class="form-label">Search</label>
                <input type="text" id="search" name="search" class="form-control" placeholder="Search..."
                    value="{{ request('search', '') }}">
            </div>
    
            <!-- Order By -->
            <div class="col-lg-2 col-md-3 col-sm-6 mb-2">
                <label for="order_field" class="form-label">Order By</label>
                <select id="order_field" name="order_field" class="form-select">
                    <option value="accounts_trans_period" {{ request('order_field') == 'accounts_trans_period' ? 'selected' : '' }}>Period</option>
                    <option value="accounts_trans_dat_date" {{ request('order_field') == 'accounts_trans_dat_date' ? 'selected' : '' }}>Date</option>
                    <option value="main_account_code" {{ request('order_field') == 'main_account_code' ? 'selected' : '' }}>Account</option>
                    <option value="sub_account_name" {{ request('order_field') == 'sub_account_name' ? 'selected' : '' }}>Account Name</option>
                    <option value="accounts_trans_doc_no" {{ request('order_field') == 'accounts_trans_doc_no' ? 'selected' : '' }}>Doc No</option>
                    <option value="accounts_trans_debit" {{ request('order_field') == 'accounts_trans_debit' ? 'selected' : '' }}>Debit</option>
                    <option value="accounts_trans_credit" {{ request('order_field') == 'accounts_trans_credit' ? 'selected' : '' }}>Credit</option>
                </select>
            </div>
    
            <!-- Order Direction -->
            <div class="col-lg-2 col-md-3 col-sm-6 mb-2">
                <label for="order_direction" class="form-label">Order Direction</label>
                <select id="order_direction" name="order_direction" class="form-select">
                    <option value="asc" {{ request('order_direction') == 'asc' ? 'selected' : '' }}>Ascending</option>
                    <option value="desc" {{ request('order_direction') == 'desc' ? 'selected' : '' }}>Descending</option>
                </select>
            </div>
    
            <!-- Submit Button -->
            <div class="col-lg-1 col-md-2 col-sm-6 mb-2">
                <button type="submit" class="btn btn-primary w-100">Filter</button>
            </div>
        </div>
    </form>

    <a href="{{ route('reports.accounts.ledger.export', request()->all()) }}" class="btn btn-success">Download Excel</a>
    <!-- Ledger Table -->
    <div class="card text-start">
        <div class="card-body">
            <h4 class="card-title mb-3">Accounts Transactions</h4>
            <div class="table-responsive">
                <table class="table table-striped">
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
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- Opening Balance -->
                        @if(isset($openingBalance))
                        <tr>
                            <td colspan="7" class="text-end"><strong>Opening Balance:</strong></td>
                            <td><strong>{{ $openingBalance->type === 'Debit' ? number_format($openingBalance->balance, 2) : '' }}</strong></td>
                            <td><strong>{{ $openingBalance->type === 'Credit' ? number_format($openingBalance->balance, 2) : '' }}</strong></td>
                            <td colspan="2"></td>
                        </tr>
                        @endif
                        @forelse ($transactions as $index => $transaction)
                        <tr>
                            <th>{{ $transactions->firstItem() + $index }}</th>
                            <td>{{ $transaction->accounts_trans_period }}</td>
                            <td>{{ \Carbon\Carbon::parse($transaction->accounts_trans_dat_date)->format('d/m/Y') }}</td>
                            <td>{{ $transaction->main_account_code }}/{{ $transaction->sub_account_code }}</td>
                            <td>{{ $transaction->sub_account_name }}</td>
                            <td>{{ $transaction->accounts_trans_doc_no }}</td>
                            <td>{{ $transaction->accounts_trans_decription }}</td>
                            <td>{{ number_format($transaction->accounts_trans_debit, 2) }}</td>
                            <td>{{ number_format($transaction->accounts_trans_credit, 2) }}</td>
                            <td>
                                @if ($transaction->accounts_trans_reconsiled === 'Y')
                                    <span class="badge bg-success">✔ Yes</span>
                                @elseif ($transaction->accounts_trans_reconsiled === 'N')
                                    <span class="badge bg-danger">✖ No</span>
                                @else
                                    <span class="badge bg-secondary">Not Set</span>
                                @endif
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#transactionModal{{ $transaction->accounts_trans_id }}">View</button>
                            </td>
                            
                        </tr>

                        <!-- Modal -->
                        <div class="modal fade" id="transactionModal{{ $transaction->accounts_trans_id }}" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Transaction Details</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <ul class="list-group mb-3">
                                            <li class="list-group-item"><strong>ID:</strong> {{ $transaction->accounts_trans_id }}</li>
                                            <li class="list-group-item"><strong>Period:</strong> {{ $transaction->accounts_trans_period }}</li>
                                            <li class="list-group-item"><strong>Date:</strong> {{ \Carbon\Carbon::parse($transaction->accounts_trans_dat_date)->format('d/m/Y') }}</li>
                                            <li class="list-group-item"><strong>Account:</strong> {{ $transaction->main_account_code }}/{{ $transaction->sub_account_code }}</li>
                                            <li class="list-group-item"><strong>Account Name:</strong> {{ $transaction->sub_account_name }}</li>
                                            <li class="list-group-item"><strong>Doc No:</strong> {{ $transaction->accounts_trans_doc_no }}</li>
                                            <li class="list-group-item"><strong>Description:</strong> {{ $transaction->accounts_trans_decription }}</li>
                                            <li class="list-group-item"><strong>Debit:</strong> {{ number_format($transaction->accounts_trans_debit, 2) }}</li>
                                            <li class="list-group-item"><strong>Credit:</strong> {{ number_format($transaction->accounts_trans_credit, 2) }}</li>
                                        </ul>

                                        @if ($transaction->accounts_trans_period === $activePeriod)
                                        <form method="POST" action="{{ route('reports.accounts.update', ['id' => $transaction->accounts_trans_id]) }}">
                                            @csrf
                                            @method('PUT')


                                        <!-- Hidden fields to retain filter values -->
                                        <input type="hidden" name="start_period" value="{{ request('start_period', '000000') }}">
                                        <input type="hidden" name="end_period" value="{{ request('end_period', '999999') }}">
                                        <input type="hidden" name="search" value="{{ request('search', '') }}">
                                        <input type="hidden" name="order_field" value="{{ request('order_field', 'accounts_trans_period') }}">
                                        <input type="hidden" name="order_direction" value="{{ request('order_direction', 'asc') }}">
                                        

                                            <div class="mb-3">
                                                <label for="accounts_trans_cash_in_already" class="form-label">Cash In Already</label>
                                                <select id="accounts_trans_cash_in_already" name="accounts_trans_cash_in_already" class="form-select">
                                                    <option value="" {{ $transaction->accounts_trans_cash_in_already === null ? 'selected' : '' }}>Not Set</option>
                                                    <option value="Y" {{ $transaction->accounts_trans_cash_in_already === 'Y' ? 'selected' : '' }}>Yes</option>
                                                    <option value="N" {{ $transaction->accounts_trans_cash_in_already === 'N' ? 'selected' : '' }}>No</option>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label for="accounts_trans_reconsiled" class="form-label">Reconciled</label>
                                                <select id="accounts_trans_reconsiled" name="accounts_trans_reconsiled" class="form-select">
                                                    <option value="" {{ $transaction->accounts_trans_reconsiled === null ? 'selected' : '' }}>Not Set</option>
                                                    <option value="Y" {{ $transaction->accounts_trans_reconsiled === 'Y' ? 'selected' : '' }}>Yes</option>
                                                    <option value="N" {{ $transaction->accounts_trans_reconsiled === 'N' ? 'selected' : '' }}>No</option>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label for="accounts_trans_reconsiled_comments" class="form-label">Reconciled Comments</label>
                                                <textarea id="accounts_trans_reconsiled_comments" name="accounts_trans_reconsiled_comments" class="form-control">{{ $transaction->accounts_trans_reconsiled_comments }}</textarea>
                                            </div>

                                            <div class="mb-3">
                                                <label for="accounts_trans_payment_type" class="form-label">Payment Type</label>
                                                <select id="accounts_trans_payment_type" name="accounts_trans_payment_type" class="form-select">
                                                    <option value="" {{ $transaction->accounts_trans_payment_type === null ? 'selected' : '' }}>Not Set</option>
                                                    <option value="M-Pesa" {{ $transaction->accounts_trans_payment_type === 'M-Pesa' ? 'selected' : '' }}>M-Pesa</option>
                                                    <option value="Bank" {{ $transaction->accounts_trans_payment_type === 'Bank' ? 'selected' : '' }}>Bank</option>
                                                    <option value="Cheque" {{ $transaction->accounts_trans_payment_type === 'Cheque' ? 'selected' : '' }}>Cheque</option>
                                                    <option value="Cash" {{ $transaction->accounts_trans_payment_type === 'Cash' ? 'selected' : '' }}>Cash</option>
                                                    <option value="Other" {{ $transaction->accounts_trans_payment_type === 'Other' ? 'selected' : '' }}>Other</option>
                                                </select>
                                            </div>

                                            <div class="mt-3">
                                                <button type="submit" class="btn btn-success">Save Changes</button>
                                            </div>
                                        </form>
                                        @else
                                        <div class="alert alert-warning">
                                            <strong>Note:</strong> This record does not belong to the active period. Editing is not allowed.
                                        </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                        @empty
                        <tr>
                            <td colspan="10" class="text-center">No transactions found.</td>
                        </tr>
                        @endforelse
                    </tbody>
                    <tfoot> 
                        <tr>
                            <td colspan="7" class="text-end"><strong>Total for Selected Range:</strong></td>
                            <td><strong>{{ number_format($totals->total_debit, 2) }}</strong></td>
                            <td><strong>{{ number_format($totals->total_credit, 2) }}</strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <!-- Pagination -->
    <div class="d-flex justify-content-center mt-3">
        {{ $transactions->appends(request()->except('page'))->links('pagination::bootstrap-4') }}
    </div>
</div>

<!-- Bootstrap Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
@endsection