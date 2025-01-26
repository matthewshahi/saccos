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
                                <input type="date" id="start_date" name="start_date" class="form-control" placeholder="Start Date" value="{{ $startDate }}">
                            </div>
                            <div class="col-md-5 mt-3 mt-md-0">
                                <input type="date" id="end_date" name="end_date" class="form-control" placeholder="End Date" value="{{ $endDate }}">
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
                                {{-- Opening Balance --}}
                                @if(isset($openingBalance))
                                    <tr>
                                        <td colspan="2"><strong>Opening Balance</strong></td>
                                        <td style="text-align: right;">
                                            <strong>{{ $openingBalance->type == 'Debit' ? number_format($openingBalance->balance, 2) : '' }}</strong>
                                        </td>
                                        <td style="text-align: right;">
                                            <strong>{{ $openingBalance->type == 'Credit' ? number_format($openingBalance->balance, 2) : '' }}</strong>
                                        </td>
                                    </tr>
                                @endif

                                {{-- Income Section --}}
                                @php
                                    $incomeTotalDebit = 0;
                                    $incomeTotalCredit = 0;
                                @endphp
                                @foreach($accounts['INCOME'] ?? [] as $account)
                                    @php
                                        $debit = (float) $account->total_debit;
                                        $credit = (float) $account->total_credit;

                                        // Calculate Net
                                        $incomeTotalDebit += $debit;
                                        $incomeTotalCredit += $credit;
                                    @endphp
                                    <tr>
                                        <td>{{ $account->main_account_code . '/' . $account->sub_account_code }}</td>
                                        <td>{{ $account->sub_account_name }}</td>
                                        <td style="text-align: right;">{{ $debit > 0 ? number_format($debit, 2) : '' }}</td>
                                        <td style="text-align: right;">{{ $credit > 0 ? number_format($credit, 2) : '' }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td colspan="2"><strong>Income Total</strong></td>
                                    <td></td>
                                    <td style="text-align: right;"><strong>{{ number_format($incomeTotalCredit, 2) }}</strong></td>
                                </tr>

                                {{-- Expense Section --}}
                                @php
                                    $expenseTotalDebit = 0;
                                    $expenseTotalCredit = 0;
                                @endphp
                                @foreach($accounts['EXPENSE'] ?? [] as $account)
                                    @php
                                        $debit = (float) $account->total_debit;
                                        $credit = (float) $account->total_credit;

                                        // Calculate Net
                                        $expenseTotalDebit += $debit;
                                        $expenseTotalCredit += $credit;
                                    @endphp
                                    <tr>
                                        <td>{{ $account->main_account_code . '/' . $account->sub_account_code }}</td>
                                        <td>{{ $account->sub_account_name }}</td>
                                        <td style="text-align: right;">{{ $debit > 0 ? number_format($debit, 2) : '' }}</td>
                                        <td style="text-align: right;">{{ $credit > 0 ? number_format($credit, 2) : '' }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td colspan="2"><strong>Expense Total</strong></td>
                                    <td style="text-align: right;"><strong>{{ number_format($expenseTotalDebit, 2) }}</strong></td>
                                    <td></td>
                                </tr>
                            </tbody>
                            <tfoot>
                                {{-- Net Profit or Loss --}}
                                @php
                                    $netProfitLoss = ($incomeTotalCredit - $incomeTotalDebit) - ($expenseTotalDebit - $expenseTotalCredit);
                                    $grossIncomeLoss = $netProfitLoss - ($openingBalance->type == 'Debit' ? $openingBalance->balance : -$openingBalance->balance);
                                @endphp
                                <tr>
                                    <th colspan="2">Net Profit/Loss</th>
                                    <th style="text-align: right;">{{ $netProfitLoss < 0 ? number_format(abs($netProfitLoss), 2) : '' }}</th>
                                    <th style="text-align: right;">{{ $netProfitLoss > 0 ? number_format($netProfitLoss, 2) : '' }}</th>
                                </tr>
                                {{-- Gross Income/Loss --}}
                                <tr>
                                    <th colspan="2">Gross Income/Loss</th>
                                    <th style="text-align: right;">{{ $grossIncomeLoss < 0 ? number_format(abs($grossIncomeLoss), 2) : '' }}</th>
                                    <th style="text-align: right;">{{ $grossIncomeLoss > 0 ? number_format($grossIncomeLoss, 2) : '' }}</th>
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