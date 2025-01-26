@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Balance Sheet</h1>
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
                    <form action="{{ route('reports.accounts.balance-sheet') }}" method="GET">
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
                    <button id="downloadExcel" class="btn btn-success mb-3">Download Excel</button>
                    <div class="table-responsive mt-4">
                        <table id="balanceSheetTable" class="display table table-striped table-bordered" style="width: 100%">
                            <thead>
                                <tr>
                                    <th>Account</th>
                                    <th>Account Name</th>
                                    <th style="text-align: right;">Debit</th>
                                    <th style="text-align: right;">Credit</th>
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

                                {{-- Initialize totals --}}
                                @php
                                    $assetTotalDebit = 0;
                                    $assetTotalCredit = 0;
                                    $liabilityTotalDebit = 0;
                                    $liabilityTotalCredit = 0;
                                    $capitalTotalDebit = 0;
                                    $capitalTotalCredit = 0;
                                @endphp

                                {{-- Fixed Assets --}}
                                @foreach($accounts['ASSET - FIXED'] ?? [] as $account)
                                    @php
                                        $debit = max($account->total_debit - $account->total_credit, 0);
                                        $credit = max($account->total_credit - $account->total_debit, 0);
                                        $assetTotalDebit += $debit;
                                        $assetTotalCredit += $credit;
                                    @endphp
                                    <tr>
                                        <td>{{ $account->main_account_code . '/' . $account->sub_account_code }}</td>
                                        <td>{{ $account->sub_account_name }}</td>
                                        <td style="text-align: right;">{{ $debit > 0 ? number_format($debit, 2) : '' }}</td>
                                        <td style="text-align: right;">{{ $credit > 0 ? number_format($credit, 2) : '' }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td colspan="2"><strong>Fixed Asset Total</strong></td>
                                    <td style="text-align: right;"><strong>{{ number_format($assetTotalDebit, 2) }}</strong></td>
                                    <td style="text-align: right;"><strong>{{ number_format($assetTotalCredit, 2) }}</strong></td>
                                </tr>

                                {{-- Current Assets --}}
                                @foreach($accounts['ASSETS - CURRENT'] ?? [] as $account)
                                    @php
                                        $debit = max($account->total_debit - $account->total_credit, 0);
                                        $credit = max($account->total_credit - $account->total_debit, 0);
                                        $assetTotalDebit += $debit;
                                        $assetTotalCredit += $credit;
                                    @endphp
                                    <tr>
                                        <td>{{ $account->main_account_code . '/' . $account->sub_account_code }}</td>
                                        <td>{{ $account->sub_account_name }}</td>
                                        <td style="text-align: right;">{{ $debit > 0 ? number_format($debit, 2) : '' }}</td>
                                        <td style="text-align: right;">{{ $credit > 0 ? number_format($credit, 2) : '' }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td colspan="2"><strong>Current Asset Total</strong></td>
                                    <td style="text-align: right;"><strong>{{ number_format($assetTotalDebit, 2) }}</strong></td>
                                    <td style="text-align: right;"><strong>{{ number_format($assetTotalCredit, 2) }}</strong></td>
                                </tr>

                                {{-- Liabilities --}}
                                @foreach($accounts['LIABILITIES - SHORT'] ?? [] as $account)
                                    @php
                                        $debit = max($account->total_debit - $account->total_credit, 0);
                                        $credit = max($account->total_credit - $account->total_debit, 0);
                                        $liabilityTotalDebit += $debit;
                                        $liabilityTotalCredit += $credit;
                                    @endphp
                                    <tr>
                                        <td>{{ $account->main_account_code . '/' . $account->sub_account_code }}</td>
                                        <td>{{ $account->sub_account_name }}</td>
                                        <td style="text-align: right;">{{ $debit > 0 ? number_format($debit, 2) : '' }}</td>
                                        <td style="text-align: right;">{{ $credit > 0 ? number_format($credit, 2) : '' }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td colspan="2"><strong>Liability Total</strong></td>
                                    <td style="text-align: right;"><strong>{{ number_format($liabilityTotalDebit, 2) }}</strong></td>
                                    <td style="text-align: right;"><strong>{{ number_format($liabilityTotalCredit, 2) }}</strong></td>
                                </tr>

                                {{-- Capital --}}
                                @foreach($accounts['CAPITAL'] ?? [] as $account)
                                    @php
                                        $debit = max($account->total_debit - $account->total_credit, 0);
                                        $credit = max($account->total_credit - $account->total_debit, 0);
                                        $capitalTotalDebit += $debit;
                                        $capitalTotalCredit += $credit;
                                    @endphp
                                    <tr>
                                        <td>{{ $account->main_account_code . '/' . $account->sub_account_code }}</td>
                                        <td>{{ $account->sub_account_name }}</td>
                                        <td style="text-align: right;">{{ $debit > 0 ? number_format($debit, 2) : '' }}</td>
                                        <td style="text-align: right;">{{ $credit > 0 ? number_format($credit, 2) : '' }}</td>
                                    </tr>
                                @endforeach
                                <tr>
                                    <td colspan="2"><strong>Capital Total</strong></td>
                                    <td style="text-align: right;"><strong>{{ number_format($capitalTotalDebit, 2) }}</strong></td>
                                    <td style="text-align: right;"><strong>{{ number_format($capitalTotalCredit, 2) }}</strong></td>
                                </tr>
                            </tbody>
                            <tfoot>
                                {{-- Net Assets --}}
                                @php
                                    $netAssets = ($assetTotalDebit - $assetTotalCredit) - ($liabilityTotalCredit - $liabilityTotalDebit) - ($capitalTotalCredit - $capitalTotalDebit);
                                @endphp
                                <tr>
                                    <th colspan="2">Net Assets</th>
                                    <th style="text-align: right;">{{ number_format($netAssets > 0 ? $netAssets : 0, 2) }}</th>
                                    <th style="text-align: right;">{{ number_format($netAssets < 0 ? abs($netAssets) : 0, 2) }}</th>
                                </tr>
                                {{-- Closing Balance --}}
                                @php
                                    $closingBalance = $netAssets + ($openingBalance->type == 'Debit' ? $openingBalance->balance : -$openingBalance->balance);
                                @endphp
                                <tr>
                                    <th colspan="2">Closing Balance</th>
                                    <th style="text-align: right;">{{ $closingBalance > 0 ? number_format($closingBalance, 2) : '' }}</th>
                                    <th style="text-align: right;">{{ $closingBalance < 0 ? number_format(abs($closingBalance), 2) : '' }}</th>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>

    <script>
        document.getElementById('downloadExcel').addEventListener('click', function() {
            var wb = XLSX.utils.table_to_book(document.getElementById('balanceSheetTable'), { sheet: "Balance Sheet" });
            XLSX.writeFile(wb, 'balance_sheet.xlsx');
        });
    </script>
@endsection