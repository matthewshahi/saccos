@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Member Loan Application</h1>
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

    <div class="col-md-12 mb-3 d-flex justify-content-center">
        
            <a href="{{ route('loans.guarantee.requests') }}">Guarantee requests</a>&nbsp;|| &nbsp;
            <a href="{{ route('loans.pending.approval') }}">List loans pending approval</a> &nbsp;|| &nbsp;
         
    </div>

    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-body">
                <div class="card-title mb-3">Loan Application Form</div>
                

                <form action="{{ route('loans.application.submit') }}" method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label for="batch_trans_member_name">Member Name*</label>
                            <input class="form-control" id="batch_trans_member_name" name="batch_trans_member_name" type="text" value="{{ auth()->user()->member_name }} - ({{ auth()->user()->member_sacco_id }})" readonly>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label for="batch_trans_loan_amount">Amount*</label>
                            <input class="form-control" id="batch_trans_loan_amount" name="batch_trans_loan_amount" type="text" value="{{ old('batch_trans_loan_amount') }}">
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label for="batch_trans_loan_type">Loan Type*</label>
                            <select class="form-control" id="batch_trans_loan_type" name="batch_trans_loan_type">
                                @foreach($loanTypes as $type)
                                    <option value="{{ $type->loan_type_id }}" {{ old('batch_trans_loan_type') == $type->loan_type_id ? 'selected' : '' }}>{{ $type->loan_type_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label for="batch_trans_loan_category">Loan Category*</label>
                            <select class="form-control" id="batch_trans_loan_category" name="batch_trans_loan_category">
                                @foreach($loanCategories as $category)
                                    <option value="{{ $category->loan_category_id }}" {{ old('batch_trans_loan_category') == $category->loan_category_id ? 'selected' : '' }}>{{ $category->loan_category_name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label for="batch_trans_loan_duration">Repayment Period*</label>
                            <select class="form-control" id="batch_trans_loan_duration" name="batch_trans_loan_duration">
                                @for($i=1; $i<=100; $i++)
                                    <option value="{{ $i }}" {{ old('batch_trans_loan_duration') == $i ? 'selected' : '' }}>{{ $i }} months</option>
                                @endfor
                            </select>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label for="batch_trans_description">Reason(s)* (max 50 characters)</label>
                            <input class="form-control" id="batch_trans_description" name="batch_trans_description" type="text" value="{{ old('batch_trans_description') }}" maxlength="50">
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label for="batch_trans_loan_to_top_up">Loan to Top Up***</label>
                            <select class="form-control" id="batch_trans_loan_to_top_up" name="batch_trans_loan_to_top_up">
                                <option value="">Select Loan</option>
                                @foreach($memberLoans as $loan)
                                    <option value="{{ $loan->loan_id }}" {{ old('batch_trans_loan_to_top_up') == $loan->loan_id ? 'selected' : '' }}>{{ $loan->loan_type_name }} - {{ number_format($loan->loan_balance, 2) }} ({{ $loan->loan_id }})</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label for="batch_trans_pay1">Attach your latest 2 payslips (jpg/jpeg/png/gif, max 200KB)</label>
                            <input type="file" class="form-control" id="batch_trans_pay1" name="batch_trans_pay1">
                        </div>
                        <div class="col-md-6 form-group mb-3">
                            <label for="batch_trans_pay2">Attach payslip 2 (jpg/jpeg/png/gif, max 200KB)</label>
                            <input type="file" class="form-control" id="batch_trans_pay2" name="batch_trans_pay2">
                        </div>
                        <div class="col-md-12">
                            <div class="mt-4">
                                <h4>Guarantors</h4>
                                <table class="display table table-striped table-bordered" style="width: 100%">
                                    <thead>
                                        <tr>
                                            <th>#</th>
                                            <th>Member</th>
                                            <th>Amount</th>
                                            <th>Free shares****</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @for($i = 0; $i < $maximumNoOfGuarantors; $i++)
                                            <tr>
                                                <td>{{ $i + 1 }}</td>
                                                <td>
                                                    <input type="text" id="guarantors_guarantor_name{{ $i }}" name="guarantors_guarantor_name[]" class="form-control" onkeyup="showHintMembers(this.value, 'guarantors_guarantor_name{{ $i }}', 'txtHintMembersG{{ $i }}')" autocomplete="off" value="{{ old('guarantors_guarantor_name.' . $i) }}">
                                                    <div id="txtHintMembersG{{ $i }}" class="suggestions-box"></div>
                                                </td>
                                                <td><input type="text" name="guarantors_amount_guaranteed[]" class="form-control" value="{{ old('guarantors_amount_guaranteed.' . $i) }}" /></td>
                                                <td><input type="text" name="free_shares[]" class="form-control" disabled /></td>
                                            </tr>
                                        @endfor
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <button class="btn btn-primary" type="submit">Submit</button>
                        </div>
                        <input type="hidden" name="batch_trans_member_id" value="{{ Auth::user()->id }}">

                    </div>
                </form>
                <p>
                    <strong>**Commission will not attract insurance, and is also not used when calculating interest. The final total loan taken will include insurance and commission.<br />
                    ***Top up: If you are topping up, enter the full amount and full guarantors for the new loan where necessary. The amount must be greater than the loan you want to top up.<br />
                    ****Free shares will not be displayed to protect other members' privacy.</strong>
                </p>
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
                    suggestions += `<div class="suggestion-item" onclick="selectMember('${member.value}', '${inputName}', '${suggestionsBox}')">${member.label}</div>`;
                });
                document.getElementById(suggestionsBox).innerHTML = suggestions;
            }
        };
        const baseURL = "{{ url('/') }}";

    // Use the base URL in your AJAX call
         xmlhttp.open("GET", baseURL + "/search/members?query=" + str, true);
        // xmlhttp.open("GET", "/search/members?query=" + str, true);
        xmlhttp.send();
    }

    function selectMember(value, inputName, suggestionsBox) {
        document.getElementById(inputName).value = value;
        document.getElementById(suggestionsBox).innerHTML = ''; // Clear suggestions
    }
</script>
@endsection
