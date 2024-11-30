@extends('layouts.app')

@section('content')
<div class="container">
    <h2 class="my-4">Initiate STK Push</h2>

    <!-- Display success message -->
    @if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <!-- Display error messages -->
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <!-- STK Push Form -->
    <form action="{{ route('stk.push.submit', ['unique_number' => uniqid()]) }}" method="POST">
        @csrf

        <!-- Select Shortcode -->
        <div class="form-group">
            <label for="shortcode">Select Shortcode</label>
            <select class="form-control" id="shortcode" name="shortcode" required>
                <option value="">Select a Shortcode</option>
                @foreach ($stkConfigs as $config)
                    <option value="{{ $config->shortcode }}">{{ $config->shortcode }}</option>
                @endforeach
            </select>
        </div>

        <!-- Unique Number (Prefilled Random) -->
        <div class="form-group">
            <label for="unique_number">Unique Number</label>
            <input type="text" class="form-control" id="unique_number" name="unique_number" value="{{ uniqid() }}" readonly>
        </div>

        <!-- Phone Number -->
        <div class="form-group">
            <label for="phone_number">Phone Number</label>
            <input type="text" class="form-control" id="phone_number" name="phone_number" placeholder="2547XXXXXXXX" required>
        </div>

        <!-- Amount -->
        <div class="form-group">
            <label for="amount">Amount</label>
            <input type="number" class="form-control" id="amount" name="amount" required>
        </div>

        <!-- Account Reference -->
        <div class="form-group">
            <label for="account_reference">Account Reference</label>
            <input type="text" class="form-control" id="account_reference" name="account_reference" required>
        </div>

        <!-- Transaction Description -->
        <div class="form-group">
            <label for="transaction_desc">Transaction Description</label>
            <input type="text" class="form-control" id="transaction_desc" name="transaction_desc" required>
        </div>

        <button type="submit" class="btn btn-primary">Initiate STK Push</button>
    </form>
</div>
@endsection
