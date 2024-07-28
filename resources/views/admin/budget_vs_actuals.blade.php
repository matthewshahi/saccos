@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Budget vs Actuals</h1>
        <div class="header-part-right">
            <ul>
                @if(Auth::check())
                    <li>{{ Auth::user()->member_name }}</li>
                @endif
                @if(isset($currentPeriod))
                    <li><a href="{{ route('admin.periods') }}">{{ $currentPeriod->period_name }}</a></li>
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

                    <form action="{{ route('reports.accounts.budget-vs-actuals') }}" method="GET">
                        <div class="row row-xs">
                            <div class="col-md-5">
                                <input type="text" id="year" name="year" class="form-control" placeholder="Year (YYYY)" value="{{ $year }}">
                            </div>
                            <div class="col-md-2 mt-3 mt-md-0">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </div>
                    </form>
                    <div class="table-responsive mt-4">
                        <table id="budgetVsActualsTable" class="display table table-striped table-bordered" style="width: 100%">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Account</th>
                                    <th>Account Name</th>
                                    <th>Main Account Name</th>
                                    <th>Main Account Type</th>
                                    <th colspan="2">Budget</th>
                                    <th colspan="2">Actuals</th>
                                </tr>
                                <tr>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th></th>
                                    <th>Debits</th>
                                    <th>Credits</th>
                                    <th>Debits</th>
                                    <th>Credits</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($budgets as $index => $budget)
                                    @php
                                        $actuals = $actualsData[$budget->budget_account_id] ?? (object) ['actual_debit' => 0, 'actual_credit' => 0];
                                        $actual_debit = 0;
                                        $actual_credit = 0;

                                        if ($actuals->actual_debit > $actuals->actual_credit) {
                                            $actual_debit = $actuals->actual_debit - $actuals->actual_credit;
                                        } else {
                                            $actual_credit = $actuals->actual_credit - $actuals->actual_debit;
                                        }
                                    @endphp
                                    <tr class="{{ ($budget->budget_amount_debit < $actual_debit) || ($budget->budget_amount_credit < $actual_credit) ? 'table-danger' : '' }}">
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $budget->main_account_code . '/' . $budget->sub_account_code }}</td>
                                        <td>{{ $budget->sub_account_name }}</td>
                                        <td>{{ $budget->main_account_name }}</td>
                                        <td>{{ $budget->main_account_type }}</td>
                                        <td style="text-align: right;">{{ number_format($budget->budget_amount_debit, 2) }}</td>
                                        <td style="text-align: right;">{{ number_format($budget->budget_amount_credit, 2) }}</td>
                                        <td style="text-align: right;">{{ number_format($actual_debit, 2) }}</td>
                                        <td style="text-align: right;">{{ number_format($actual_credit, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
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
        .table-danger {
            background-color: #f8d7da;
        }
    </style>
<script src="https://cdn.datatables.net/1.10.21/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.10.21/js/dataTables.bootstrap4.min.js"></script>
<link href="https://cdn.datatables.net/1.10.21/css/dataTables.bootstrap4.min.css" rel="stylesheet" />

<script>
    $(document).ready(function() {
        $('#budgetVsActualsTable').DataTable({
            searching: true,
            ordering: true,
            order: [[0, 'asc']]
        });
    });
</script>
@endsection
