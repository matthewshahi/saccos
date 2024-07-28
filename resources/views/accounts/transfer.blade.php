@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Accounts Transfer</h1>
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
                    <form action="{{ route('accounts.transfer.store') }}" method="POST" autocomplete="off">
                        @csrf
                        <div class="table-responsive">
                            <table class="display table table-striped table-bordered" style="width: 100%">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Account*</th>
                                        <th>Debit</th>
                                        <th>Credit</th>
                                        <th>Doc. No.*</th>
                                        <th>Description*</th>
                                        <th>Date*</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @for($i = 0; $i < 20; $i++)
                                        <tr>
                                            <td>{{ $i + 1 }}</td>
                                            <td>
                                                <input type="text" id="accountsTransfer_account_{{ $i }}" name="accountsTransfer_account_{{ $i }}" class="form-control account-name" value="{{ old('accountsTransfer_account_' . $i) }}" onkeyup="showHintAccounts(this.value, 'accountsTransfer_account_{{ $i }}', 'suggestions-box-account_{{ $i }}')" autocomplete="off">
                                                <div id="suggestions-box-account_{{ $i }}" class="suggestions-box"></div>
                                            </td>
                                            <td>
                                                <input type="text" id="accountsTransfer_debit_{{ $i }}" name="accountsTransfer_debit_{{ $i }}" class="form-control" value="{{ old('accountsTransfer_debit_' . $i) }}">
                                            </td>
                                            <td>
                                                <input type="text" id="accountsTransfer_credit_{{ $i }}" name="accountsTransfer_credit_{{ $i }}" class="form-control" value="{{ old('accountsTransfer_credit_' . $i) }}">
                                            </td>
                                            <td>
                                                <input type="text" id="accountsTransfer_doc_no_{{ $i }}" name="accountsTransfer_doc_no_{{ $i }}" class="form-control" value="{{ old('accountsTransfer_doc_no_' . $i) }}">
                                            </td>
                                            <td>
                                                <input type="text" id="accountsTransfer_description_{{ $i }}" name="accountsTransfer_description_{{ $i }}" class="form-control" value="{{ old('accountsTransfer_description_' . $i) }}">
                                            </td>
                                            <td>
                                                <input type="date" id="accountsTransfer_date_{{ $i }}" name="accountsTransfer_date_{{ $i }}" class="form-control" value="{{ old('accountsTransfer_date_' . $i, date('Y-m-d')) }}">
                                            </td>
                                        </tr>
                                    @endfor
                                </tbody>
                            </table>
                        </div>
                        <button type="submit" class="btn btn-primary">Submit</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <style>
        .suggestions-box {
            background-color: #fff;
            max-height: 150px;
            overflow-y: auto;
            position: absolute;
            z-index: 1000;
            width: 300px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
        }

        .suggestion-item {
            padding: 10px;
            cursor: pointer;
        }

        .suggestion-item:last-child {
            border-bottom: none;
        }

        .suggestion-item:hover {
            background-color: #f0f0f0;
        }
    </style>

    <script>
        function showHintAccounts(str, inputName, suggestionsBox) {
            if (str.length == 0) {
                document.getElementById(suggestionsBox).innerHTML = "";
                return;
            }
            let xmlhttp;
            if (window.XMLHttpRequest) {
                xmlhttp = new XMLHttpRequest();
            } else {
                xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
            }
            xmlhttp.onreadystatechange = function () {
                if (xmlhttp.readyState == 4 && xmlhttp.status == 200) {
                    let response = JSON.parse(xmlhttp.responseText);
                    let suggestions = '';
                    response.forEach(account => {
                        suggestions += `<div class="suggestion-item" onclick="selectAccount('${account.value}', '${inputName}', '${suggestionsBox}')">${account.label}</div>`;
                    });
                    document.getElementById(suggestionsBox).innerHTML = suggestions;
                }
            };
            xmlhttp.open("GET", "{{ route('search.accounts') }}?query=" + str, true);
            xmlhttp.send();
        }

        function selectAccount(value, inputName, suggestionsBox) {
            document.getElementById(inputName).value = value;
            document.getElementById(suggestionsBox).innerHTML = ''; // Clear suggestions
        }
    </script>
@endsection
