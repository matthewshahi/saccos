@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Main Accounts</h1>
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
                @if(session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if(session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                <div class="mb-3">
                    <a href="{{ route('accounts.main.create') }}" class="btn btn-primary">Add New Main Account</a>
                </div>

                <div class="table-responsive">
                    <table class="display table table-striped table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Account Name</th>
                                <th>Account Code</th>
                                <th>Account Type</th>
                                <th>Debit</th>
                                <th>Credit</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($mainAccounts as $mainAccount)
                                <tr>
                                    <td>{{ $loop->iteration }}</td>
                                    <td>{{ $mainAccount->main_account_name }}</td>
                                    <td>{{ $mainAccount->main_account_code }}</td>
                                    <td>{{ $mainAccount->main_account_type }}</td>
                                    <td>{{ number_format($mainAccount->main_account_debit, 2) }}</td>
                                    <td>{{ number_format($mainAccount->main_account_credit, 2) }}</td>
                                    <td>
                                        <a href="{{ route('accounts.main.edit', $mainAccount->main_account_id) }}" class="btn btn-sm btn-primary">Edit</a>
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
@endsection
