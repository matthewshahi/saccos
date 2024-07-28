@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Budget Management</h1>
        <div class="header-part-right">
            <ul>
                @if(Auth::check())
                    <li>{{ Auth::user()->member_name }}</li>
                @endif
                @if(isset($currentYear))
                    <li><a href="{{ route('admin.periods') }}">{{ $currentYear }}</a></li>
                @endif
                <li><i class="i-Full-Screen header-icon d-none d-sm-inline-block" data-fullscreen=""></i></li>
            </ul>
        </div>
    </div>
    <div class="separator-breadcrumb border-top"></div>

    <div class="row mb-3">
        <div class="col-md-2 mb-3">
            <div class="card">
                <div class="card-body text-center">
                    <a href="{{ route('reports.accounts.ledger') }}" class="btn btn-primary w-100">Ledger</a>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="card">
                <div class="card-body text-center">
                    <a href="{{ route('reports.accounts.trial-balance') }}" class="btn btn-primary w-100">Trial Balance</a>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="card">
                <div class="card-body text-center">
                    <a href="{{ route('reports.accounts.profit-loss') }}" class="btn btn-primary w-100">Profit & Loss</a>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="card">
                <div class="card-body text-center">
                    <a href="{{ route('reports.accounts.balance-sheet') }}" class="btn btn-primary w-100">Balance Sheet</a>
                </div>
            </div>
        </div>
        <div class="col-md-2 mb-3">
            <div class="card">
                <div class="card-body text-center">
                    <a href="{{ route('reports.accounts.budget-vs-actuals') }}" class="btn btn-primary w-100">Budget vs Actuals</a>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-4">
        <div class="col-md-12 mb-4">
            <div class="card text-start">
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form action="{{ route('admin.budget') }}" method="GET">
                        <div class="row row-xs">
                            <div class="col-md-5">
                                <input type="text" id="year" name="year" class="form-control" placeholder="Year (YYYY)" value="{{ $year }}">
                            </div>
                            <div class="col-md-2 mt-3 mt-md-0">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </div>
                    </form>
                    <form action="{{ route('admin.budget.store') }}" method="POST" class="mt-4">
                        @csrf
                        <input type="hidden" name="year" value="{{ $year }}">
                        <div class="table-responsive">
                            <table id="budgetTable" class="display table table-striped table-bordered" style="width: 100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Account</th>
                                        <th>Account Name</th>
                                        <th>Main Account Name</th>
                                        <th>Main Account Type</th>
                                        <th>Budget Debits</th>
                                        <th>Budget Credits</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($subAccounts as $index => $subAccount)
                                        @php
                                            $budget = $budgets->firstWhere('budget_account_id', $subAccount->sub_account_id);
                                            $budgetAmountDebit = $budget->budget_amount_debit ?? 0;
                                            $budgetAmountCredit = $budget->budget_amount_credit ?? 0;
                                        @endphp
                                        <tr>
                                            <td>{{ $index + 1 }}</td>
                                            <td>{{ $subAccount->main_account_code . '/' . $subAccount->sub_account_code }}</td>
                                            <td>{{ $subAccount->sub_account_name }}</td>
                                            <td>{{ $subAccount->main_account_name }}</td>
                                            <td>{{ $subAccount->main_account_type }}</td>
                                            <td>
                                                <input type="number" name="budget[{{ $subAccount->sub_account_id }}][amount_debit]" class="form-control" value="{{ $budgetAmountDebit }}" min="0">
                                            </td>
                                            <td>
                                                <input type="number" name="budget[{{ $subAccount->sub_account_id }}][amount_credit]" class="form-control" value="{{ $budgetAmountCredit }}" min="0">
                                            </td>
                                            <input type="hidden" name="budget[{{ $subAccount->sub_account_id }}][account_id]" value="{{ $subAccount->sub_account_id }}">
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <button type="submit" class="btn btn-success mt-3">Save Budget</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <style>
        .custom-search-form {
            margin-bottom: 20px;
        }
        .custom-search-form .form-control {
            border-radius: 0.25rem;
        }
        .custom-search-form .btn {
            border-radius: 0.25rem;
        }
    </style>
<script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.21/js/dataTables.bootstrap4.min.js"></script>
<link href="https://cdn.datatables.net/1.10.21/css/dataTables.bootstrap4.min.css" rel="stylesheet" />

<script>
    $(document).ready(function() {
        $('#budgetTable').DataTable({
            searching: true,
            ordering: true,
            order: [[0, 'asc']]
        });
    });
</script>
@endsection
