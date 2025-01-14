@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Sub Accounts</h1>
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

<div class="row mb-4">
    <div class="col-md-12 mb-4">
        <div class="card text-start">
            <div class="card-body">
                <a href="{{ route('accounts.sub.create') }}" class="btn btn-primary mb-3">Add Sub Account</a>
                <div class="table-responsive">
                    <table id="subAccountsTable" class="display table table-striped table-bordered" style="width: 100%">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>Code</th>
                                <th>Main Account</th>
                                <th>Debit</th>
                                <th>Credit</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($subAccounts as $index => $subAccount)
                                <tr>
                                    <td>{{ $index + 1 }}</td>
                                    <td>{{ $subAccount->sub_account_name }}</td>
                                    <td>{{ $subAccount->sub_account_code }}</td>
                                    <td>{{ $subAccount->main_account_code }}/{{ $subAccount->sub_account_code }}</td>
                                    <td>{{ number_format($subAccount->sub_account_debit, 2) }}</td>
                                    <td>{{ number_format($subAccount->sub_account_credit, 2) }}</td>
                                    <td>
                                        <a href="{{ route('accounts.sub.edit', $subAccount->sub_account_id) }}" class="btn btn-warning btn-sm">Edit</a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
    .form-group {
        margin-bottom: 1.5rem;
    }
    .col-form-label {
        font-weight: bold;
    }
    .btn-primary {
        background-color: #4e73df;
        border-color: #4e73df;
    }
    .btn-primary:hover {
        background-color: #2e59d9;
        border-color: #2653d4;
    }
    h5 {
        border-bottom: 1px solid #e0e0e0;
        padding-bottom: 0.5rem;
        margin-bottom: 1.5rem;
        color: #4e73df;
    }
</style>
<link href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css" rel="stylesheet">

<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.flash.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
<script>
    $(document).ready(function() {
        $('#subAccountsTable').DataTable({
            dom: 'Bfrtip',
            buttons: [
                'copy', 'csv', 'excel', 'pdf', 'print'
            ]
        });
    });
</script> 
@endsection
