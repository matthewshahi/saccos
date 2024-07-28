@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Add Sub Account</h1>
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
        <div class="col-md-12">
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
                    <form action="{{ route('accounts.sub.store') }}" method="POST">
                        @csrf
                        <div class="form-group">
                            <label for="sub_account_name">Sub Account Name*</label>
                            <input type="text" class="form-control" id="sub_account_name" name="sub_account_name" value="{{ old('sub_account_name') }}" required>
                        </div>
                        <div class="form-group">
                            <label for="sub_account_code">Sub Account Code*</label>
                            <input type="text" class="form-control" id="sub_account_code" name="sub_account_code" value="{{ old('sub_account_code') }}" required>
                        </div>
                        <div class="form-group">
                            <label for="sub_account_main_account">Main Account*</label>
                            <select class="form-control" id="sub_account_main_account" name="sub_account_main_account" required>
                                @foreach ($mainAccounts as $mainAccount)
                                    <option value="{{ $mainAccount->main_account_id }}" {{ old('sub_account_main_account') == $mainAccount->main_account_id ? 'selected' : '' }}>
                                        {{ $mainAccount->main_account_name }} ({{ $mainAccount->main_account_code }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="sub_account_debit">Debit*</label>
                            <input type="number" step="0.01" class="form-control" id="sub_account_debit" name="sub_account_debit" value="{{ old('sub_account_debit') }}" required>
                        </div>
                        <div class="form-group">
                            <label for="sub_account_credit">Credit*</label>
                            <input type="number" step="0.01" class="form-control" id="sub_account_credit" name="sub_account_credit" value="{{ old('sub_account_credit') }}" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Add Sub Account</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('styles')
    <style>
        .form-group {
            margin-bottom: 1.5rem;
        }
        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
        }
        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2653d4;
        }
    </style>
@endsection
