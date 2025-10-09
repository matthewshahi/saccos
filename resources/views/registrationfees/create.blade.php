@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Add Registration Fee (Manual Entry)</h1>
    <div class="header-part-right">
        <ul>
            <li><a href="{{ route('registrationfees.index') }}" class="btn btn-sm btn-outline-primary">
                <i class="i-Arrow-Back"></i> Back to Registration Fees
            </a></li>
        </ul>
    </div>
</div>
<div class="separator-breadcrumb border-top"></div>

<div class="row mb-4">
    <div class="col-md-12">
        <div class="card">
            <div class="card-body">
                {{-- ✅ Alert & Error Handling --}}
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif
                @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                {{-- ✅ Form --}}
                <form action="{{ route('registrationfees.store') }}" method="POST" autocomplete="off">
                    @csrf
                    <div class="row">
                        {{-- Member Search --}}
                        <div class="col-md-6 form-group mb-3 position-relative">
                            <label for="member_name">Select Member <span class="text-danger">*</span></label>
                            <input type="text" id="member_name" name="regfee_member_id"
                                   class="form-control" placeholder="Type member name or ID..."
                                   onkeyup="showHintMembers(this.value, 'member_name', 'suggestions-box-member')" required>
                            <div id="suggestions-box-member" class="suggestions-box"></div>
                        </div>

                        {{-- Amount --}}
                        <div class="col-md-6 form-group mb-3">
                            <label for="amount">Amount (KES) <span class="text-danger">*</span></label>
                            <input type="number" name="regfee_amount" id="amount"
                                   step="0.01" min="1"
                                   class="form-control" placeholder="Enter amount" required>
                        </div>

                        {{-- Description --}}
                        <div class="col-md-12 form-group mb-3">
                            <label for="desc">Description</label>
                            <input type="text" name="regfee_description" id="desc"
                                   class="form-control" placeholder="Mandatory description">
                        </div>

                        {{-- Date --}}
                        <div class="col-md-6 form-group mb-3">
                            <label for="date">Date Paid <span class="text-danger">*</span></label>
                            <input type="date" name="regfee_date_paid" id="date"
                                   class="form-control" value="{{ date('Y-m-d') }}" required>
                        </div>

                        {{-- Period --}}
                        <div class="col-md-4 form-group mb-3">
                            <label for="period">Period <span class="text-danger">*</span></label>
                            <input type="text" name="regfee_period" id="period"
                                   class="form-control" value="{{ date('Ym') }}" required>
                            <small class="text-muted">Format: YYYYMM</small>
                        </div>

                        {{-- Contra Account --}}
                        <div class="col-md-6 form-group mb-3 position-relative">
                            <label for="contra_account">Source (Contra) Account <span class="text-danger">*</span></label>
                            <input type="text" id="contra_account" name="contra_account"
                                   class="form-control" placeholder="Type account name or code..."
                                   onkeyup="showHintAccounts(this.value, 'contra_account', 'suggestions-box-account')" required>
                            <div id="suggestions-box-account" class="suggestions-box"></div>
                            <small class="text-muted">This account will be debited.</small>
                        </div>

                        {{-- Registration Fee Account --}}
                        <div class="col-md-6 form-group mb-3">
                            <label>Registration Fee Account (Credit)</label>
                            <input type="hidden" name="regfee_account_id" value="{{ $regFeeAccountId }}">
                            <input type="text" class="form-control" value="{{ $regFeeAccountLabel }}" readonly>
                        </div>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary px-5 mt-3">
                            💾 Save Fee
                        </button>
                        <a href="{{ route('registrationfees.index') }}" class="btn btn-secondary mt-3">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- 🔍 Suggestions UI --}}
<style>
    .suggestions-box {
        position: absolute;
        background-color: #fff;
        max-height: 180px;
        overflow-y: auto;
        width: 100%;
        border: 1px solid #ccc;
        border-radius: 4px;
        z-index: 1000;
    }
    .suggestion-item {
        padding: 8px 10px;
        cursor: pointer;
    }
    .suggestion-item:hover {
        background-color: #f1f1f1;
    }
</style>

<script>
    // ✅ AJAX live search (reusing HomeController routes)
    function showHintMembers(str, inputName, suggestionsBox) {
        if (str.length === 0) {
            document.getElementById(suggestionsBox).innerHTML = "";
            return;
        }
        fetch(`{{ url('/search/members') }}?query=${encodeURIComponent(str)}`)
            .then(res => res.json())
            .then(data => {
                let html = data.map(m =>
                    `<div class='suggestion-item' onclick="selectMember('${m.value}', '${m.label}', '${inputName}', '${suggestionsBox}')">${m.label}</div>`
                ).join('');
                document.getElementById(suggestionsBox).innerHTML = html;
            });
    }

    function selectMember(value, label, inputName, suggestionsBox) {
        const input = document.getElementById(inputName);
        input.value = label;
        input.setAttribute('data-id', value);
        document.getElementById(suggestionsBox).innerHTML = '';
    }

    function showHintAccounts(str, inputName, suggestionsBox) {
        if (str.length === 0) {
            document.getElementById(suggestionsBox).innerHTML = "";
            return;
        }
        fetch(`{{ url('/search/accounts') }}?query=${encodeURIComponent(str)}`)
            .then(res => res.json())
            .then(data => {
                let html = data.map(a =>
                    `<div class='suggestion-item' onclick="selectAccount('${a.value}', '${a.label}', '${inputName}', '${suggestionsBox}')">${a.label}</div>`
                ).join('');
                document.getElementById(suggestionsBox).innerHTML = html;
            });
    }

    function selectAccount(value, label, inputName, suggestionsBox) {
        const input = document.getElementById(inputName);
        input.value = label;
        input.setAttribute('data-id', value);
        document.getElementById(suggestionsBox).innerHTML = '';
    }
</script>
@endsection