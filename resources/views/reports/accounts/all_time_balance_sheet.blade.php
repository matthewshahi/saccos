@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>All Time Balance Sheet</h1>
    <button id="downloadExcel" class="btn btn-success mb-3">
        <i class="i-Download"></i> Download Excel
    </button>
</div>
<div class="separator-breadcrumb border-top"></div>

<div class="row mb-4">
    <div class="col-md-12 mb-4">
        <div class="card text-start">
            <div class="card-body">
                <div class="table-responsive mt-4">
                    <table id="balanceSheetTable" class="display table table-striped table-bordered" style="width: 100%">
                        <thead>
                            <tr>
                                <th>Main Account Code</th>
                                <th>Main Account Name</th>
                                <th>Sub Account Code</th>
                                <th>Sub Account Name</th>
                                <th style="text-align: right;">Total Debit</th>
                                <th style="text-align: right;">Total Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data as $account)
                            <tr>
                                <td>{{ $account->main_account_code }}</td>
                                <td>{{ $account->main_account_name }}</td>
                                <td>{{ $account->sub_account_code }}</td>
                                <td>{{ $account->sub_account_name }}</td>
                                <td style="text-align: right;">{{ number_format($account->sub_account_debit, 2) }}</td>
                                <td style="text-align: right;">{{ number_format($account->sub_account_credit, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            @php
                                $totalAssets = $data->whereIn('main_account_type', ['ASSET - FIXED', 'ASSETS - CURRENT'])->sum('sub_account_debit');
                                $totalLiabilities = $data->whereIn('main_account_type', ['LIABILITIES - SHORT'])->sum('sub_account_credit');
                                $totalCapital = $data->whereIn('main_account_type', ['CAPITAL'])->sum('sub_account_credit');
                                $netAssets = $totalAssets - ($totalLiabilities + $totalCapital);
                            @endphp
                            <tr>
                                <th colspan="4">Total Assets</th>
                                <th style="text-align: right;">{{ number_format($totalAssets, 2) }}</th>
                                <th></th>
                            </tr>
                            <tr>
                                <th colspan="4">Total Liabilities</th>
                                <th></th>
                                <th style="text-align: right;">{{ number_format($totalLiabilities, 2) }}</th>
                            </tr>
                            <tr>
                                <th colspan="4">Total Capital</th>
                                <th></th>
                                <th style="text-align: right;">{{ number_format($totalCapital, 2) }}</th>
                            </tr>
                            <tr>
                                <th colspan="4">Net Assets</th>
                                <th style="text-align: right;">{{ number_format($netAssets, 2) }}</th>
                                <th></th>
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
        XLSX.writeFile(wb, 'all_time_balance_sheet.xlsx');
    });
</script>
@endsection
