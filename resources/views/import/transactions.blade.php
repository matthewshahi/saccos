@extends('layouts.app')

@section('content')
<div class="container">
    <h4>Import Savings and Shares</h4>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ route('members.import.transactions') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="mb-3">
            <label for="csv_file" class="form-label">Upload CSV</label>
            <input type="file" class="form-control" name="csv_file" required>
        </div>

        <button type="submit" class="btn btn-primary">Import Transactions</button>
    </form>
</div>
@endsection