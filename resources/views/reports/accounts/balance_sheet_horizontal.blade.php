@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Balance Sheet (Horizontal Format)</h1>
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
                <form action="{{ route('reports.accounts.balance-sheet-horizontal') }}" method="GET">
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
                <div id="balance-sheet-content">
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h5>Assets</h5>
                            <table class="table table-bordered" id="assets-table">
                                <thead>
                                    <tr>
                                        <th>Account</th>
                                        <th>Account Name</th>
                                        <th style="text-align: right;">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $fixedAssetsTotal = 0;
                                        $currentAssetsTotal = 0;
                                    @endphp
                                    <tr><th colspan="3">Fixed Assets</th></tr>
                                    @foreach($accounts['ASSET - FIXED'] ?? [] as $account)
                                        @php
                                            $amount = $account->total_debit - $account->total_credit;
                                            $fixedAssetsTotal += $amount;
                                        @endphp
                                        <tr>
                                            <td>{{ $account->main_account_code . '/' . $account->sub_account_code }}</td>
                                            <td>{{ $account->sub_account_name }}</td>
                                            <td style="text-align: right;">{{ number_format($amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                    <tr>
                                        <td colspan="2"><strong>Sub Total</strong></td>
                                        <td style="text-align: right;"><strong>{{ number_format($fixedAssetsTotal, 2) }}</strong></td>
                                    </tr>
                                    <tr><th colspan="3">Current Assets</th></tr>
                                    @foreach($accounts['ASSETS - CURRENT'] ?? [] as $account)
                                        @php
                                            $amount = $account->total_debit - $account->total_credit;
                                            $currentAssetsTotal += $amount;
                                        @endphp
                                        <tr>
                                            <td>{{ $account->main_account_code . '/' . $account->sub_account_code }}</td>
                                            <td>{{ $account->sub_account_name }}</td>
                                            <td style="text-align: right;">{{ number_format($amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                    <tr>
                                        <td colspan="2"><strong>Sub Total</strong></td>
                                        <td style="text-align: right;"><strong>{{ number_format($currentAssetsTotal, 2) }}</strong></td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="2">Total Assets</th>
                                        <th style="text-align: right;">{{ number_format($fixedAssetsTotal + $currentAssetsTotal, 2) }}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <h5>Liabilities & Capital</h5>
                            <table class="table table-bordered" id="liabilities-table">
                                <thead>
                                    <tr>
                                        <th>Account</th>
                                        <th>Account Name</th>
                                        <th style="text-align: right;">Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php
                                        $capitalTotal = 0;
                                        $liabilitiesTotal = 0;
                                    @endphp
                                    <tr><th colspan="3">Capital</th></tr>
                                    @foreach($accounts['CAPITAL'] ?? [] as $account)
                                        @php
                                            $amount = $account->total_credit - $account->total_debit;
                                            $capitalTotal += $amount;
                                        @endphp
                                        <tr>
                                            <td>{{ $account->main_account_code . '/' . $account->sub_account_code }}</td>
                                            <td>{{ $account->sub_account_name }}</td>
                                            <td style="text-align: right;">{{ number_format($amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                    <tr>
                                        <td colspan="2"><strong>Sub Total</strong></td>
                                        <td style="text-align: right;"><strong>{{ number_format($capitalTotal, 2) }}</strong></td>
                                    </tr>
                                    <tr><th colspan="3">Current Liabilities</th></tr>
                                    @foreach($accounts['LIABILITIES - SHORT'] ?? [] as $account)
                                        @php
                                            $amount = $account->total_credit - $account->total_debit;
                                            $liabilitiesTotal += $amount;
                                        @endphp
                                        <tr>
                                            <td>{{ $account->main_account_code . '/' . $account->sub_account_code }}</td>
                                            <td>{{ $account->sub_account_name }}</td>
                                            <td style="text-align: right;">{{ number_format($amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                    <tr>
                                        <td colspan="2"><strong>Sub Total</strong></td>
                                        <td style="text-align: right;"><strong>{{ number_format($liabilitiesTotal, 2) }}</strong></td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="2">Total Liabilities & Capital</th>
                                        <th style="text-align: right;">{{ number_format($capitalTotal + $liabilitiesTotal, 2) }}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div class="row mt-4">
                        <div class="col-md-12">
                            <table class="table table-bordered" id="summary-table">
                                <tfoot>
                                    <tr>
                                        <th>Total Assets</th>
                                        <th style="text-align: right;">{{ number_format($fixedAssetsTotal + $currentAssetsTotal, 2) }}</th>
                                    </tr>
                                    <tr>
                                        <th>Total Liabilities & Capital</th>
                                        <th style="text-align: right;">{{ number_format($capitalTotal + $liabilitiesTotal, 2) }}</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .loading-status {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 36px;
        font-weight: bold;
        color: #000;
        z-index: 9999;
        animation: blinkingText 1.2s infinite;
    }

    @keyframes blinkingText {
        0% { color: #000; }
        49% { color: #000; }
        50% { color: transparent; }
        99% { color: transparent; }
        100% { color: #000; }
    }

    .table {
        position: relative;
        z-index: 1;
    }
</style>

<!-- Include jQuery library -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/file-saver@2.0.5/FileSaver.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>

<script>
    $(document).ready(function() {
        // Download Excel
        $('#downloadExcel').click(function() {
            let wb = XLSX.utils.book_new();

            // Assets Table
            let wsAssets = XLSX.utils.table_to_sheet(document.getElementById('assets-table'));
            XLSX.utils.book_append_sheet(wb, wsAssets, 'Assets');

            // Liabilities Table
            let wsLiabilities = XLSX.utils.table_to_sheet(document.getElementById('liabilities-table'));
            XLSX.utils.book_append_sheet(wb, wsLiabilities, 'Liabilities & Capital');

            // Summary Table
            let wsSummary = XLSX.utils.table_to_sheet(document.getElementById('summary-table'));
            XLSX.utils.book_append_sheet(wb, wsSummary, 'Summary');

            XLSX.writeFile(wb, 'BalanceSheet.xlsx');
        });
    });
</script>
@endsection
