@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>{{ $title }}</h1>
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
                <button id="downloadExcel" class="btn btn-success mb-3">
                    <i class="i-Download"></i> Download Excel
                </button>
                <div class="table-responsive mt-4">
                    <table id="trialBalanceTable" class="display table table-striped table-bordered" style="width: 100%">
                        <thead>
                            <tr>
                                <th>Account Code</th>
                                <th>Account Name</th>
                                <th>Sub Account Name</th>
                                <th style="text-align: right;">Debit</th>
                                <th style="text-align: right;">Credit</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($data as $account)
                                @php
                                    $netDebit = $account->sub_account_debit - $account->sub_account_credit;
                                    $netCredit = $account->sub_account_credit - $account->sub_account_debit;

                                    if ($netDebit < 0) {
                                        $netCredit = abs($netDebit);
                                        $netDebit = 0;
                                    }

                                    if ($netCredit < 0) {
                                        $netDebit = abs($netCredit);
                                        $netCredit = 0;
                                    }
                                @endphp
                                <tr>
                                    <td>{{ $account->main_account_code . '/' . $account->sub_account_code }}</td>
                                    <td>{{ $account->main_account_name }}</td>
                                    <td>{{ $account->sub_account_name }}</td>
                                    <td style="text-align: right;">{{ $netDebit > 0 ? number_format($netDebit, 2) : '0.00' }}</td>
                                    <td style="text-align: right;">{{ $netCredit > 0 ? number_format($netCredit, 2) : '0.00' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot>
                            @php
                                $totalDebit = $data->sum('sub_account_debit');
                                $totalCredit = $data->sum('sub_account_credit');
                            @endphp
                            <tr>
                                <th colspan="3">Total</th>
                                <th style="text-align: right;">{{ number_format($totalDebit, 2) }}</th>
                                <th style="text-align: right;">{{ number_format($totalCredit, 2) }}</th>
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
        var wb = XLSX.utils.table_to_book(document.getElementById('trialBalanceTable'), { sheet: "Trial Balance" });
        XLSX.writeFile(wb, 'all_time_trial_balance.xlsx');
    });
</script>
@endsection
