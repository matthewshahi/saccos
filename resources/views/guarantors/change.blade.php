@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Change Guarantors</h1>
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
                    @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <h4 class="card-title mb-3">Guarantor and Loan Details</h4>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th scope="col">Guarantor name</th>
                                    <th scope="col">{{ $currentGuarantor->member_name }}, {{ $currentGuarantor->member_sacco_id }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Amount Guaranteed</td>
                                    <td>{{ number_format($currentGuarantor->loan_guar_amount_guaranteed, 2) }}</td>
                                    <td>Tied shares</td>
                                    <td>{{ number_format($currentGuarantor->loan_guar_amount_guaranteed - $currentGuarantor->loan_guar_amount_freed, 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Loan type</td>
                                    <td>{{ $loan->loan_type_name }}</td>
                                    <td>Loan no.</td>
                                    <td>{{ $loan->loan_id }}</td>
                                </tr>
                                <tr>
                                    <td>Loan taken by</td>
                                    <td>{{ $loan->loan_taker_name }}</td>
                                    <td>Loan amount taken</td>
                                    <td>{{ number_format($loan->loan_amount, 2) }}</td>
                                </tr>
                                <tr>
                                    <td>Period Taken</td>
                                    <td>{{ $loan->loan_taken_period }}</td>
                                    <td>Loan balance</td>
                                    <td>{{ number_format($loan->loan_amount - $loan->loan_loan_paid, 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <form action="{{ route('updateGuarantors', ['member_id' => $member_id, 'guarantor_id' => $guarantor_id]) }}" method="POST">
                        @csrf
                        <h4 class="card-title mb-3 mt-5">New Guarantors</h4>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th scope="col">#</th>
                                        <th scope="col">Member</th>
                                        <th scope="col">Amount</th>
                                        <th scope="col">Free shares</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @for ($i = 1; $i <= $maximum_no_of_guarantors; $i++)
                                        <tr>
                                            <th scope="row">{{ $i }}</th>
                                            <td>
                                                <input class="form-control member-search" type="text" id="member{{ $i }}" name="guarantors_guarantor_name{{ $i }}" placeholder="Member" onkeyup="showHintMembers(this.value, 'member{{ $i }}', 'suggestions-box-member{{ $i }}')" autocomplete="off">
                                                <div id="suggestions-box-member{{ $i }}" class="suggestions-box"></div>
                                            </td>
                                            <td>
                                                <input class="form-control" type="text" name="guarantors_amount_guaranteed{{ $i }}" placeholder="Amount">
                                            </td>
                                            <td>
                                                <input class="form-control" type="text" name="free_shares{{ $i }}" value="hidden" disabled>
                                            </td>
                                        </tr>
                                    @endfor
                                </tbody>
                            </table>
                        </div>

                        <input type="hidden" name="loan_id" value="{{ $loan->loan_id }}">
                        <input type="hidden" name="loan_member" value="{{ $loan->loan_member }}">
                        <input type="hidden" name="loan_guar_id" value="{{ $currentGuarantor->loan_guar_id }}">
                        <input type="hidden" name="loan_guar_loan_id" value="{{ $currentGuarantor->loan_guar_loan_id }}">
                        <input type="hidden" name="loan_guar_guarantor_id" value="{{ $currentGuarantor->loan_guar_guarantor_id }}">
                        <input type="hidden" name="submittedRows" value="{{ $maximum_no_of_guarantors }}">
                        <input type="hidden" name="batch_tied_shares_to_pay" value="{{ $currentGuarantor->loan_guar_amount_guaranteed - $currentGuarantor->loan_guar_amount_freed }}">

                        <div class="col-md-2 mt-3 mt-md-0">
                            <button class="btn btn-primary w-100">Submit</button>
                        </div>
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
        function showHintMembers(str, inputName, suggestionsBox) {
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
                    response.forEach(member => {
                        suggestions += `<div class="suggestion-item" onclick="selectMember('${member.name} (${member.sacco_id})', '${inputName}', '${suggestionsBox}')">${member.name} (${member.sacco_id})</div>`;
                    });
                    document.getElementById(suggestionsBox).innerHTML = suggestions;
                }
            };
            xmlhttp.open("GET", "{{ route('ajaxGetMembers') }}?q=" + str, true);
            xmlhttp.send();
        }

        function selectMember(value, inputName, suggestionsBox) {
            document.getElementById(inputName).value = value;
            document.getElementById(suggestionsBox).innerHTML = ''; // Clear suggestions
        }
    </script>
@endsection
