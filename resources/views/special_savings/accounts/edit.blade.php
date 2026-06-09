@extends('layouts.app')

@section('content')
<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Edit Special Saving Account</h1>
        <ul>
            <li><a href="{{ route('special_savings.accounts.index') }}">Accounts</a></li>
            <li>Edit</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @include('special_savings.partials.alerts')

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="card-title mb-3">{{ $record->special_saving_account_number }}</div>

                    <form method="POST" action="{{ route('special_savings.accounts.update', $record->special_saving_account_id) }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-6 form-group mb-3">
                                <label>Member</label>
                                <input type="text" value="{{ $record->member_name }} - {{ $record->member_sacco_id }}" class="form-control" readonly>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>Product</label>
                                <input type="text" value="{{ $record->special_saving_product_name }}" class="form-control" readonly>
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>Status</label>
                                <select name="special_saving_account_status" class="form-control" required>
                                    @foreach(['Active', 'Frozen', 'Dormant', 'Closed'] as $item)
                                        <option value="{{ $item }}" {{ old('special_saving_account_status', $record->special_saving_account_status) == $item ? 'selected' : '' }}>
                                            {{ $item }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            <div class="col-md-12 form-group mb-3">
                                <label>Notes</label>
                                <textarea name="special_saving_account_notes" class="form-control" rows="2">{{ old('special_saving_account_notes', $record->special_saving_account_notes) }}</textarea>
                            </div>

                            <div class="col-md-12">
                                <button class="btn btn-primary">Update Account</button>
                                <a href="{{ route('special_savings.accounts.show', $record->special_saving_account_id) }}" class="btn btn-outline-secondary">Cancel</a>
                            </div>
                        </div>
                    </form>

                    <hr>

                    <form method="POST" action="{{ route('special_savings.accounts.freeze', $record->special_saving_account_id) }}" class="d-inline">
                        @csrf
                        <button class="btn btn-warning">Freeze</button>
                    </form>

                    <form method="POST" action="{{ route('special_savings.accounts.activate', $record->special_saving_account_id) }}" class="d-inline">
                        @csrf
                        <button class="btn btn-success">Activate</button>
                    </form>

                    <form method="POST" action="{{ route('special_savings.accounts.close', $record->special_saving_account_id) }}" class="d-inline">
                        @csrf
                        <button class="btn btn-danger" onclick="return confirm('Close this account?')">Close</button>
                    </form>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection