@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Open Special Saving Account</h1>
        <ul>
            <li><a href="{{ route('special_savings.dashboard') }}">Special Savings</a></li>
            <li><a href="{{ route('special_savings.accounts.index') }}">Accounts</a></li>
            <li>Open Account</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @include('special_savings.partials.alerts')
    @include('special_savings.partials.nav')

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">Account Details</div>

                    @if(!empty($selected_member))
                        <div class="alert alert-success mb-4">
                            <strong>{{ $selected_member->member_name }}</strong><br>
                            SACCO No: {{ $selected_member->member_sacco_id ?? '-' }}
                            | Phone: {{ $selected_member->member_phone_no ?? '-' }}
                            | ID: {{ $selected_member->member_national_id ?? '-' }}
                        </div>
                    @else
                        <div class="alert alert-warning mb-4">
                            No member has been selected. Please open this page from the member search result.
                        </div>
                    @endif

                    <form method="POST" action="{{ route('special_savings.accounts.store') }}">
                        @csrf

                        <input
                            type="hidden"
                            name="special_saving_account_member_id"
                            value="{{ old('special_saving_account_member_id', $selected_member->member_id ?? '') }}">

                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label for="special_saving_account_product_id">Product</label>

                                <select
                                    id="special_saving_account_product_id"
                                    name="special_saving_account_product_id"
                                    class="form-control"
                                    required
                                    {{ empty($selected_member) ? 'disabled' : '' }}>
                                    <option value="">Select product</option>

                                    @foreach($products as $product)
                                        <option
                                            value="{{ $product->special_saving_product_id }}"
                                            {{ old('special_saving_account_product_id') == $product->special_saving_product_id ? 'selected' : '' }}>
                                            {{ $product->special_saving_product_name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label for="special_saving_account_opening_date">Opening Date</label>

                                <input
                                    type="date"
                                    id="special_saving_account_opening_date"
                                    name="special_saving_account_opening_date"
                                    value="{{ old('special_saving_account_opening_date', date('Y-m-d')) }}"
                                    class="form-control"
                                    {{ empty($selected_member) ? 'disabled' : '' }}
                                    required>
                            </div>

                            <div class="col-md-12 form-group mb-3">
                                <label for="special_saving_account_notes">Notes</label>

                                <textarea
                                    id="special_saving_account_notes"
                                    name="special_saving_account_notes"
                                    class="form-control"
                                    rows="2"
                                    placeholder="Optional account notes"
                                    {{ empty($selected_member) ? 'disabled' : '' }}>{{ old('special_saving_account_notes') }}</textarea>
                            </div>

                            <div class="col-md-12">
                                @if(!empty($selected_member))
                                    <button
                                        class="btn btn-primary"
                                        onclick="return confirm('Open Special Savings account for {{ $selected_member->member_name }}?')">
                                        Open Account
                                    </button>
                                @else
                                    <button class="btn btn-primary" disabled>
                                        Open Account
                                    </button>
                                @endif

                                <a href="{{ route('special_savings.accounts.index') }}" class="btn btn-outline-secondary">
                                    Cancel
                                </a>

                                <a href="{{ route('special_savings.deposits.create') }}" class="btn btn-outline-success">
                                    Back to Deposit
                                </a>
                            </div>
                        </div>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection