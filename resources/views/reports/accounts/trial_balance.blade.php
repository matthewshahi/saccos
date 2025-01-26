@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Trial Balance</h1>
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

                    <form action="{{ route('reports.accounts.trial-balance') }}" method="GET">
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
                        <table id="trialBalanceTable" class="display table table-striped table-bordered" style="width: 100%">
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
                                    $netOpeningBalance = 0;
                                    $netClosingBalance = 0;

                                    // Calculate net opening balance
                                    foreach ($accounts as $accountGroup) {
                                        foreach ($accountGroup as $account) {
                                            $openingBalance = $account->opening_balance ?? 0;
                                            $netOpeningBalance += $openingBalance;
                                            $netClosingBalance += $openingBalance + $account->total_debit - $account->total_credit;
                                        }
                                    }
                                @endphp

                                {{-- Opening Balance Row --}}
                                <tr>
                                    <td colspan="2"><strong>Opening Balance</strong></td>
                                    <td style="text-align: right;">
                                        <strong>{{ $netOpeningBalance > 0 ? number_format($netOpeningBalance, 2) : '0.00' }}</strong>
                                    </td>
                                    <td style="text-align: right;">
                                        <strong>{{ $netOpeningBalance < 0 ? number_format(abs($netOpeningBalance), 2) : '0.00' }}</strong>
                                    </td>
                                </tr>

                                {{-- Account Data --}}
                                @php
                                    $groupTotals = [
                                        'ASSET - FIXED' => ['total_debit' => 0, 'total_credit' => 0],
                                        'ASSETS - CURRENT' => ['total_debit' => 0, 'total_credit' => 0],
                                        'CAPITAL' => ['total_debit' => 0, 'total_credit' => 0],
                                        'EXPENSE' => ['total_debit' => 0, 'total_credit' => 0],
                                        'INCOME' => ['total_debit' => 0, 'total_credit' => 0],
                                        'LIABILITIES - SHORT' => ['total_debit' => 0, 'total_credit' => 0]
                                    ];
                                @endphp
                                @foreach($accounts as $mainAccountType => $accountGroup)
                                    <tr>
                                        <td colspan="4"><strong>{{ $mainAccountType }}</strong></td>
                                    </tr>
                                    @foreach($accountGroup as $account)
                                        @php
                                            $debit = (float) $account->total_debit;
                                            $credit = (float) $account->total_credit;
                                            $balance = abs($debit - $credit);

                                            if ($debit > $credit) {
                                                $groupTotals[$mainAccountType]['total_debit'] += $balance;
                                            } else {
                                                $groupTotals[$mainAccountType]['total_credit'] += $balance;
                                            }
                                        @endphp
                                        <tr>
                                            <td>{{ $account->main_account_code . '/' . $account->sub_account_code }}</td>
                                            <td>{{ $account->sub_account_name }}</td>
                                            <td style="text-align: right;">{{ $debit > $credit ? number_format($balance, 2) : '0.00' }}</td>
                                            <td style="text-align: right;">{{ $credit > $debit ? number_format($balance, 2) : '0.00' }}</td>
                                        </tr>
                                    @endforeach
                                    <tr>
                                        <td colspan="2"><strong>{{ $mainAccountType }} Total</strong></td>
                                        <td style="text-align: right;"><strong>{{ number_format($groupTotals[$mainAccountType]['total_debit'], 2) }}</strong></td>
                                        <td style="text-align: right;"><strong>{{ number_format($groupTotals[$mainAccountType]['total_credit'], 2) }}</strong></td>
                                    </tr>
                                @endforeach

                                {{-- Closing Balance Row --}}
                                <tr>
                                    <td colspan="2"><strong>Closing Balance</strong></td>
                                    <td style="text-align: right;">
                                        <strong>{{ $netClosingBalance > 0 ? number_format($netClosingBalance, 2) : '0.00' }}</strong>
                                    </td>
                                    <td style="text-align: right;">
                                        <strong>{{ $netClosingBalance < 0 ? number_format(abs($netClosingBalance), 2) : '0.00' }}</strong>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="2">Overall Total</th>
                                    <th style="text-align: right;">{{ number_format(array_sum(array_column($groupTotals, 'total_debit')), 2) }}</th>
                                    <th style="text-align: right;">{{ number_format(array_sum(array_column($groupTotals, 'total_credit')), 2) }}</th>
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
            var wb = XLSX.utils.table_to_book(document.getElementById('trialBalanceTable'), { sheet: "Trial Balance" });
            XLSX.writeFile(wb, 'trial_balance.xlsx');
        });
    </script>
@endsection