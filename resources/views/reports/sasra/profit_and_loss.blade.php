@extends('layouts.app')

@section('styles')
<style>
    .align-right {
        text-align: right;
    }
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
@endsection

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Profit & Loss Statement</h1>
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
                <div class="card-title mb-3">Filter Profit & Loss Statement</div>
                <form action="{{ route('reports.sasra.profitandloss') }}" method="post">
                    @csrf
                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label for="startPeriod">Start Period (YYYYMM):</label>
                            <input type="text" class="form-control" id="startPeriod" name="startPeriod" value="{{ $startPeriod }}" required>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label for="endPeriod">End Period (YYYYMM):</label>
                            <input type="text" class="form-control" id="endPeriod" name="endPeriod" value="{{ $endPeriod }}" required>
                        </div>
                        <div class="col-md-12">
                            <button type="submit" class="btn btn-primary">Filter</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-12 mb-4">
        <div class="card text-start">
            <div class="card-body">
                <button id="downloadExcel" class="btn btn-success mb-3">Download Excel</button>
                <div id="profit-loss-table-container" style="overflow-x: auto;">
                    <table class="table table-striped table-bordered" id="profit-loss-table" style="white-space: nowrap;">
                        <thead class="bg-primary text-white">
                            <tr>
                                <th>Description</th>
                                <th class="text-right">Debit</th>
                                <th class="text-right">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $totalIncomeDebit = 0;
                                $totalIncomeCredit = 0;
                                $totalExpenseDebit = 0;
                                $totalExpenseCredit = 0;
                            @endphp

                            <tr class="bg-light">
                                <td colspan="3"><strong>Incomes</strong></td>
                            </tr>
                            @foreach($accounts as $account)
                                @if(in_array(trim($account['main_account_type']), ['INCOME', 'INCOMES']))
                                    <tr>
                                        <td>{{ $account['sub_account_name'] }}</td>
                                        <td class="text-right">{{ number_format($account['adjusted_debit'], 2) }}</td>
                                        <td class="text-right">{{ number_format($account['adjusted_credit'], 2) }}</td>
                                        @php
                                            $totalIncomeDebit += $account['adjusted_debit'];
                                            $totalIncomeCredit += $account['adjusted_credit'];
                                        @endphp
                                    </tr>
                                @endif
                            @endforeach
                            <tr>
                                <td><strong>Subtotal Incomes</strong></td>
                                <td class="text-right"><strong>{{ number_format($totalIncomeDebit, 2) }}</strong></td>
                                <td class="text-right"><strong>{{ number_format($totalIncomeCredit, 2) }}</strong></td>
                            </tr>

                            <tr class="bg-light">
                                <td colspan="3"><strong>Expenses</strong></td>
                            </tr>
                            @foreach($accounts as $account)
                                @if(in_array(trim($account['main_account_type']), ['EXPENSE', 'EXPENSES']))
                                    <tr>
                                        <td>{{ $account['sub_account_name'] }}</td>
                                        <td class="text-right">{{ number_format($account['adjusted_debit'], 2) }}</td>
                                        <td class="text-right">{{ number_format($account['adjusted_credit'], 2) }}</td>
                                        @php
                                            $totalExpenseDebit += $account['adjusted_debit'];
                                            $totalExpenseCredit += $account['adjusted_credit'];
                                        @endphp
                                    </tr>
                                @endif
                            @endforeach
                            <tr>
                                <td><strong>Subtotal Expenses</strong></td>
                                <td class="text-right"><strong>{{ number_format($totalExpenseDebit, 2) }}</strong></td>
                                <td class="text-right"><strong>{{ number_format($totalExpenseCredit, 2) }}</strong></td>
                            </tr>
                        </tbody>
                        <tfoot class="bg-secondary text-white">
                            <tr>
                                <td>Total Income</td>
                                <td class="text-right">{{ number_format($totalIncomeDebit, 2) }}</td>
                                <td class="text-right">{{ number_format($totalIncomeCredit, 2) }}</td>
                            </tr>
                            <tr>
                                <td>Total Expenses</td>
                                <td class="text-right">{{ number_format($totalExpenseDebit, 2) }}</td>
                                <td class="text-right">{{ number_format($totalExpenseCredit, 2) }}</td>
                            </tr>
                            <tr class="bg-primary text-white">
                                <td>Net {{ $totalIncomeCredit > $totalExpenseDebit ? 'Profit' : 'Loss' }}</td>
                                <td colspan="2" class="text-right">{{ number_format($totalIncomeCredit - $totalExpenseDebit, 2) }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
 
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/file-saver@2.0.5/FileSaver.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>

<script>
    $(document).ready(function() {
        $('#downloadExcel').click(function() {
            let wb = XLSX.utils.table_to_book(document.getElementById('profit-loss-table'), { sheet: "Sheet JS" });
            XLSX.writeFile(wb, 'ProfitAndLossReport.xlsx');
        });
    });
</script>
@endsection
