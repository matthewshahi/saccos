@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Trial Balance (Horizontal Format)</h1>
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
                    <form action="{{ route('reports.accounts.trial-balance-horizontal') }}" method="GET">
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
                    <div class="table-responsive mt-4">
                        <table class="display table table-striped table-bordered" style="width: 100%">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    @foreach($accounts as $mainAccountType => $accountGroup)
                                        <th colspan="2">{{ $mainAccountType }}</th>
                                    @endforeach
                                </tr>
                                <tr>
                                    <th>Account</th>
                                    @foreach($accounts as $mainAccountType => $accountGroup)
                                        <th style="text-align: right;">Total Debit</th>
                                        <th style="text-align: right;">Total Credit</th>
                                    @endforeach
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($accounts as $mainAccountType => $accountGroup)
                                    <tr>
                                        <td><strong>{{ $mainAccountType }}</strong></td>
                                        @foreach($accountGroup as $account)
                                            @php
                                                $debit = (float) $account->total_debit;
                                                $credit = (float) $account->total_credit;
                                                $balance = abs($debit - $credit);
                                            @endphp
                                            <td style="text-align: right;">{{ $debit > $credit ? number_format($balance, 2) : '0.00' }}</td>
                                            <td style="text-align: right;">{{ $credit > $debit ? number_format($balance, 2) : '0.00' }}</td>
                                        @endforeach
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th>Overall Total</th>
                                    @foreach($accounts as $mainAccountType => $accountGroup)
                                        @php
                                            $totalDebit = array_sum(array_column($accountGroup->toArray(), 'total_debit'));
                                            $totalCredit = array_sum(array_column($accountGroup->toArray(), 'total_credit'));
                                        @endphp
                                        <th style="text-align: right;">{{ number_format($totalDebit, 2) }}</th>
                                        <th style="text-align: right;">{{ number_format($totalCredit, 2) }}</th>
                                    @endforeach
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
