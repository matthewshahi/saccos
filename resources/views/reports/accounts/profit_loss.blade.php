@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Profit and Loss</h1>
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
                    <form action="{{ route('reports.accounts.profit-loss') }}" method="GET">
                        <div class="row row-xs">
                            <div class="col-md-5">
                                <input type="text" id="start_period" name="start_period" class="form-control" placeholder="Start Period (YYYYmm)" value="{{ $startPeriod }}">
                            </div>
                            <div class="col-md-5 mt-3 mt-md-0">
                                <input type="text" id="end_period" name="end_period" class="form-control" placeholder="End Period (YYYYmm)" value="{{ $endPeriod }}">
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
                                    <th style="text-align: right;">Total Debit</th>
                                    <th style="text-align: right;">Total Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $incomeTotalDebit = 0;
                                    $incomeTotalCredit = 0;
                                    $expenseTotalDebit = 0;
                                    $expenseTotalCredit = 0;
                                @endphp
                                @foreach($accounts['INCOME'] ?? [] as $account)
                                    @php
                                        $debit = $account->total_debit;
                                        $credit = $account->total_credit;
                                        if ($debit > $credit) {
                                            $debit = $debit - $credit;
                                            $credit = 0;
                                        } else {
                                            $credit = $credit - $debit;
                                            $debit = 0;
                                        }
                                        $incomeTotalDebit += $debit;
                                        $incomeTotalCredit += $credit;
                                    @endphp
                                    <tr>
                                        <td>{{ $account->main_account_code . '/' . $account->sub_account_code }}</td>
                                        <td>{{ $account->sub_account_name }}</td>
                                        <td style="text-align: right;">{{ number_format($debit, 2) }}</td>
                                        <td style="text-align: right;">{{ number_format($credit, 2) }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td colspan="2"><strong>Income Total</strong></td>
                                    <td style="text-align: right;"><strong>{{ $incomeTotalDebit > $incomeTotalCredit ? number_format($incomeTotalDebit - $incomeTotalCredit, 2) : '' }}</strong></td>
                                    <td style="text-align: right;"><strong>{{ $incomeTotalCredit > $incomeTotalDebit ? number_format($incomeTotalCredit - $incomeTotalDebit, 2) : '' }}</strong></td>
                                </tr>
                                @foreach($accounts['EXPENSE'] ?? [] as $account)
                                    @php
                                        $debit = $account->total_debit;
                                        $credit = $account->total_credit;
                                        if ($debit > $credit) {
                                            $debit = $debit - $credit;
                                            $credit = 0;
                                        } else {
                                            $credit = $credit - $debit;
                                            $debit = 0;
                                        }
                                        $expenseTotalDebit += $debit;
                                        $expenseTotalCredit += $credit;
                                    @endphp
                                    <tr>
                                        <td>{{ $account->main_account_code . '/' . $account->sub_account_code }}</td>
                                        <td>{{ $account->sub_account_name }}</td>
                                        <td style="text-align: right;">{{ number_format($debit, 2) }}</td>
                                        <td style="text-align: right;">{{ number_format($credit, 2) }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td colspan="2"><strong>Expense Total</strong></td>
                                    <td style="text-align: right;"><strong>{{ $expenseTotalDebit > $expenseTotalCredit ? number_format($expenseTotalDebit - $expenseTotalCredit, 2) : '' }}</strong></td>
                                    <td style="text-align: right;"><strong>{{ $expenseTotalCredit > $expenseTotalDebit ? number_format($expenseTotalCredit - $expenseTotalDebit, 2) : '' }}</strong></td>
                                </tr>
                            </tbody>
                            <tfoot>
                                @php
                                    $netProfitLoss = ($incomeTotalCredit - $incomeTotalDebit) - ($expenseTotalDebit - $expenseTotalCredit);
                                @endphp
                                <tr>
                                    <th colspan="2">Net Profit/Loss</th>
                                    <th style="text-align: right;">{{ $netProfitLoss < 0 ? number_format(abs($netProfitLoss), 2) : '' }}</th>
                                    <th style="text-align: right;">{{ $netProfitLoss > 0 ? number_format($netProfitLoss, 2) : '' }}</th>
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
            var wb = XLSX.utils.table_to_book(document.getElementById('profitLossTable'), { sheet: "Profit and Loss" });
            XLSX.writeFile(wb, 'profit_loss.xlsx');
        });
    </script>
@endsection
