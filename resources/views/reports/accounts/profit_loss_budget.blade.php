@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Profit and Loss with Budget</h1>
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
                    <form action="{{ route('reports.accounts.profit-loss-budget') }}" method="GET">
                        <div class="row row-xs">
                            <div class="col-md-5">
                                <input type="text" id="year" name="year" class="form-control" placeholder="Year (YYYY)" value="{{ $year }}">
                            </div>
                            <div class="col-md-2 mt-3 mt-md-0">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </div>
                    </form>
                    <button id="downloadExcel" class="btn btn-success mb-3">
                        <i class="i-Download"></i> Download Excel
                    </button>
                    <div class="table-responsive mt-4">
                        <table id="profitLossTable" class="display table table-striped table-bordered" style="width: 100%">
                            <thead>
                                <tr>
                                    <th>Account</th>
                                    <th>Account Name</th>
                                    <th style="text-align: right;">Budget Debit</th>
                                    <th style="text-align: right;">Budget Credit</th>
                                    <th style="text-align: right;">Actual Debit</th>
                                    <th style="text-align: right;">Actual Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $incomeTotalBudgetDebit = 0;
                                    $incomeTotalBudgetCredit = 0;
                                    $incomeTotalActualDebit = 0;
                                    $incomeTotalActualCredit = 0;
                                    $expenseTotalBudgetDebit = 0;
                                    $expenseTotalBudgetCredit = 0;
                                    $expenseTotalActualDebit = 0;
                                    $expenseTotalActualCredit = 0;
                                @endphp
                                @foreach($budgets as $budget)
                                    @php
                                        $actuals = $actualsData[$budget->budget_account_id] ?? (object) ['actual_debit' => 0, 'actual_credit' => 0];
                                        $debit = $actuals->actual_debit;
                                        $credit = $actuals->actual_credit;
                                        if ($debit > $credit) {
                                            $actual_debit = $debit - $credit;
                                            $actual_credit = 0;
                                        } else {
                                            $actual_credit = $credit - $debit;
                                            $actual_debit = 0;
                                        }

                                        if ($budget->main_account_type == 'INCOME') {
                                            $incomeTotalBudgetDebit += $budget->budget_amount_debit;
                                            $incomeTotalBudgetCredit += $budget->budget_amount_credit;
                                            $incomeTotalActualDebit += $actual_debit;
                                            $incomeTotalActualCredit += $actual_credit;
                                        } elseif (in_array($budget->main_account_type, ['EXPENSE', 'EXPENSES'])) {
                                            $expenseTotalBudgetDebit += $budget->budget_amount_debit;
                                            $expenseTotalBudgetCredit += $budget->budget_amount_credit;
                                            $expenseTotalActualDebit += $actual_debit;
                                            $expenseTotalActualCredit += $actual_credit;
                                        }
                                    @endphp
                                    <tr>
                                        <td>{{ $budget->main_account_code . '/' . $budget->sub_account_code }}</td>
                                        <td>{{ $budget->sub_account_name }}</td>
                                        <td style="text-align: right;">{{ number_format($budget->budget_amount_debit, 2) }}</td>
                                        <td style="text-align: right;">{{ number_format($budget->budget_amount_credit, 2) }}</td>
                                        <td style="text-align: right;">{{ number_format($actual_debit, 2) }}</td>
                                        <td style="text-align: right;">{{ number_format($actual_credit, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="2">Total Income</th>
                                    <th style="text-align: right;">{{ number_format($incomeTotalBudgetDebit, 2) }}</th>
                                    <th style="text-align: right;">{{ number_format($incomeTotalBudgetCredit, 2) }}</th>
                                    <th style="text-align: right;">{{ number_format($incomeTotalActualDebit, 2) }}</th>
                                    <th style="text-align: right;">{{ number_format($incomeTotalActualCredit, 2) }}</th>
                                </tr>
                                <tr>
                                    <th colspan="2">Total Expense</th>
                                    <th style="text-align: right;">{{ number_format($expenseTotalBudgetDebit, 2) }}</th>
                                    <th style="text-align: right;">{{ number_format($expenseTotalBudgetCredit, 2) }}</th>
                                    <th style="text-align: right;">{{ number_format($expenseTotalActualDebit, 2) }}</th>
                                    <th style="text-align: right;">{{ number_format($expenseTotalActualCredit, 2) }}</th>
                                </tr>
                                @php
                                    $netBudget = ($incomeTotalBudgetCredit - $incomeTotalBudgetDebit) - ($expenseTotalBudgetDebit - $expenseTotalBudgetCredit);
                                    $netActual = ($incomeTotalActualCredit - $incomeTotalActualDebit) - ($expenseTotalActualDebit - $expenseTotalActualCredit);
                                @endphp
                                <tr>
                                    <th colspan="2">Net Profit/Loss (Budget)</th>
                                    <th style="text-align: right;" colspan="3">{{ $netBudget < 0 ? number_format(abs($netBudget), 2) : '' }}</th>
                                    <th style="text-align: right;">{{ $netBudget > 0 ? number_format($netBudget, 2) : '' }}</th>
                                </tr>
                                <tr>
                                    <th colspan="2">Net Profit/Loss (Actual)</th>
                                    <th style="text-align: right;" colspan="3">{{ $netActual < 0 ? number_format(abs($netActual), 2) : '' }}</th>
                                    <th style="text-align: right;">{{ $netActual > 0 ? number_format($netActual, 2) : '' }}</th>
                                </tr>
                            </tfoot>
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
    </style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>
    <script>
        document.getElementById('downloadExcel').addEventListener('click', function() {
            var wb = XLSX.utils.table_to_book(document.getElementById('profitLossTable'), { sheet: "Profit and Loss Budget" });
            XLSX.writeFile(wb, 'profit_loss_budget.xlsx');
        });
    </script>
@endsection
