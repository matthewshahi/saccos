@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Profit and Loss (Horizontal Format)</h1>
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
                    <form action="{{ route('reports.accounts.profit-loss-horizontal') }}" method="GET">
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
                    <div class="table-responsive mt-4">
                        <table class="display table table-striped table-bordered" style="width: 100%">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Total Debit</th>
                                    <th>Total Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @php
                                    $incomeTotalDebit = 0;
                                    $incomeTotalCredit = 0;
                                    $expenseTotalDebit = 0;
                                    $expenseTotalCredit = 0;
                                @endphp
                                <tr>
                                    <td><strong>INCOME</strong></td>
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
                                        <td style="text-align: right;">{{ number_format($debit, 2) }}</td>
                                        <td style="text-align: right;">{{ number_format($credit, 2) }}</td>
                                    @endforeach
                                </tr>
                                <tr>
                                    <td><strong>EXPENSE</strong></td>
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
                                        <td style="text-align: right;">{{ number_format($debit, 2) }}</td>
                                        <td style="text-align: right;">{{ number_format($credit, 2) }}</td>
                                    @endforeach
                                </tr>
                            </tbody>
                            <tfoot>
                                @php
                                    $netProfitLoss = ($incomeTotalCredit - $incomeTotalDebit) - ($expenseTotalDebit - $expenseTotalCredit);
                                @endphp
                                <tr>
                                    <th colspan="1">Net Profit/Loss</th>
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
@endsection
