@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Ledger Accounts Transaction Listing</h1>
        <div class="header-part-right">
            <ul>
                @if(isset($currentPeriod))
                    <li><a href="{{ route('admin.periods') }}">{{ $currentPeriod->period_name }}</a></li>
                @endif
                @if(Auth::check())
                    <li>{{ Auth::user()->member_name }}</li>
                @endif
                <li><i class="i-Full-Screen header-icon d-none d-sm-inline-block" data-fullscreen=""></i></li>
            </ul>
        </div>
    </div>
    <div class="separator-breadcrumb border-top"></div>

    <div class="row mb-4">
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
        <div class="col-md-12 mb-4">
            <div class="card text-start">
                <div class="card-body">
                    <h4 class="card-title mb-3">Ledger Accounts Transaction Listing</h4>
                    <form action="{{ url()->current() }}" method="get" class="custom-search-form">
                        <fieldset>
                            <legend>Search Criteria</legend>
                            <div class="row row-xs">
                                <div class="col-md-3">
                                    <label for="pfrom">Period from</label>
                                    <input name="pfrom" type="text" id="pfrom" value="{{ $pfrom }}" class="form-control" maxlength="6" placeholder="202407">
                                </div>
                                <div class="col-md-3">
                                    <label for="pto">Period to</label>
                                    <input name="pto" type="text" id="pto" value="{{ $pto }}" class="form-control" maxlength="6" placeholder="202407">
                                </div>
                                <div class="col-md-3">
                                    <label for="main_account_code">Main Account Code</label>
                                    <input name="main_account_code" type="text" id="main_account_code" value="{{ $main_account_code }}" class="form-control" placeholder="Enter main account code">
                                </div>
                                <div class="col-md-3">
                                    <label for="sub_account_name">Sub Account Name</label>
                                    <input name="sub_account_name" type="text" id="sub_account_name" value="{{ $sub_account_name }}" class="form-control" placeholder="Enter sub account name">
                                </div>
                                <div class="col-md-3 mt-3">
                                    <label for="accounts_trans_dat_date">Transaction Date</label>
                                    <input name="accounts_trans_dat_date" type="date" id="accounts_trans_dat_date" value="{{ $accounts_trans_dat_date }}" class="form-control">
                                </div>
                                <div class="col-md-3 mt-3">
                                    <label for="accounts_trans_doc_no">Document Number</label>
                                    <input name="accounts_trans_doc_no" type="text" id="accounts_trans_doc_no" value="{{ $accounts_trans_doc_no }}" class="form-control" placeholder="Enter document number">
                                </div>
                                <div class="col-md-3 mt-3">
                                    <label for="accounts_trans_decription">Description</label>
                                    <input name="accounts_trans_decription" type="text" id="accounts_trans_decription" value="{{ $accounts_trans_decription }}" class="form-control" placeholder="Enter description">
                                </div>
                                <div class="col-md-3 mt-3 d-flex align-items-end">
                                    <button class="btn btn-primary w-100">Search</button>
                                </div>
                            </div>
                        </fieldset>
                    </form>
                    <button id="downloadExcel" class="btn btn-success mb-3">
                        <i class="i-Download"></i> Download Excel
                    </button>
                    <div class="table-responsive mt-4">
                        <table class="display table table-striped table-bordered" id="ledger_table" style="width: 100%">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Account</th>
                                    <th>Account Name</th>
                                    <th>Period</th>
                                    <th>Date</th>
                                    <th>Doc. No</th>
                                    <th>Description</th>
                                    <th>Debit</th>
                                    <th>Credit</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($transactions as $index => $transaction)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $transaction->main_account_code }}/{{ $transaction->sub_account_code }}</td>
                                        <td>{{ $transaction->sub_account_name }}</td>
                                        <td>{{ $transaction->accounts_trans_period }}</td>
                                        <td>{{ \Carbon\Carbon::parse($transaction->accounts_trans_dat_date)->format('d/m/Y') }}</td>
                                        <td>{{ $transaction->accounts_trans_doc_no }}</td>
                                        <td>{{ $transaction->accounts_trans_decription }}</td>
                                        <td class="text-right">{{ number_format($transaction->accounts_trans_debit, 2) }}</td>
                                        <td class="text-right">{{ number_format($transaction->accounts_trans_credit, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <th colspan="7" class="text-right">Total</th>
                                    <th class="text-right">{{ number_format($total_debits, 2) }}</th>
                                    <th class="text-right">{{ number_format($total_credits, 2) }}</th>
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
            var wb = XLSX.utils.table_to_book(document.getElementById('ledger_table'), { sheet: "Ledger Transactions" });
            XLSX.writeFile(wb, 'ledger_transactions.xlsx');
        });
    </script>
@endsection
