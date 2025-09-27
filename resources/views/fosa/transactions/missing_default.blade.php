@extends('layouts.app')

@section('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .card {
        max-width: 700px;
        margin: auto;
    }
</style>
@endsection

@section('content')
<div class="card mt-4 shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">Add / Reduce FOSA</h5>
        <a href="{{ route('fosa.transactions.index') }}" class="btn btn-sm btn-secondary">Back to List</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('fosa.transactions.store') }}">
            @csrf
            <div class="row g-3">
                <!-- Member -->
                <div class="col-md-12">
                    <label class="form-label">Member</label>
                    <select name="fosa_member_id" class="form-control member-search" required></select>
                </div>

                <!-- Amount -->
                <div class="col-md-6">
                    <label class="form-label">Amount</label>
                    <input type="number" step="0.01" name="fosa_amount_paying" class="form-control" required>
                </div>

                <!-- Transaction Type -->
                <div class="col-md-6">
                    <label class="form-label">Transaction Type</label>
                    <select name="fosa_trans_type" class="form-control" required>
                        <option value="debit" selected>Withdrawal (Reduce)</option>
                        <option value="credit">Deposit (Add)</option>
                    </select>
                </div>

                <!-- Counter Account -->
                <div class="col-md-12">
                    <label class="form-label">Counter Account</label>
                    <select name="counter_account" class="form-control account-search" required></select>
                </div>

                <!-- Description -->
                <div class="col-md-12">
                    <label class="form-label">Description</label>
                    <input type="text" name="fosa_description" class="form-control">
                </div>

                <!-- Doc No -->
                <div class="col-md-6">
                    <label class="form-label">Doc No</label>
                    <input type="text" name="fosa_doc_no" class="form-control">
                </div>

                <!-- Period -->
                <div class="col-md-3">
                    <label class="form-label">Period (YYYYMM)</label>
                    <input type="text" name="fosa_period" value="{{ now()->format('Ym') }}" class="form-control" required>
                </div>

                <!-- Date -->
                <div class="col-md-3">
                    <label class="form-label">Transaction Date</label>
                    <input type="date" name="fosa_date_paid" value="{{ now()->format('Y-m-d') }}" class="form-control" required>
                </div>
            </div>

            <div class="mt-4 text-end">
                <button type="submit" class="btn btn-primary">Save Transaction</button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    $('.member-search').select2({
        placeholder: '-- Search Member --',
        ajax: {
            url: '{{ route("api.members.search") }}',
            dataType: 'json',
            delay: 250,
            processResults: function (data) {
                return { results: data };
            }
        }
    });

    $('.account-search').select2({
        placeholder: '-- Search Account --',
        ajax: {
            url: '{{ route("api.accounts.search") }}',
            dataType: 'json',
            delay: 250,
            processResults: function (data) {
                return { results: data };
            }
        }
    });
});
</script>
@endsection