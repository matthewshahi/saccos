@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>Budget Management</h1>
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
        <div class="col-md-12 mb-4">
            <div class="card text-start">
                <div class="card-body">
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
                    <form action="{{ route('admin.budget') }}" method="GET">
                        <div class="row row-xs">
                            <div class="col-md-5">
                                <input type="text" id="year" name="year" class="form-control" placeholder="Year (YYYY)" value="{{ $year }}">
                            </div>
                            <div class="col-md-2 mt-3 mt-md-0">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </div>
                    </form>
                    <form action="{{ route('admin.budget.store') }}" method="POST" class="mt-4">
                        @csrf
                        <input type="hidden" name="year" value="{{ $year }}">
                        <div class="table-responsive">
                            <table class="display table table-striped table-bordered" style="width: 100%">
                                <thead>
                                    <tr>
                                        <th>Sub Account Code</th>
                                        <th>Sub Account Name</th>
                                        <th>Budget Amount Debit</th>
                                        <th>Budget Amount Credit</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($subAccounts as $subAccount)
                                        @php
                                            $budget = $budgets->firstWhere('budget_account_id', $subAccount->sub_account_id);
                                        @endphp
                                        <tr>
                                            <td>{{ $subAccount->sub_account_code }}</td>
                                            <td>{{ $subAccount->sub_account_name }}</td>
                                            <td>
                                                <input type="number" name="budget[{{ $subAccount->sub_account_id }}][amount_debit]" class="form-control" value="{{ $budget->budget_amount_debit ?? 0 }}" min="0">
                                            </td>
                                            <td>
                                                <input type="number" name="budget[{{ $subAccount->sub_account_id }}][amount_credit]" class="form-control" value="{{ $budget->budget_amount_credit ?? 0 }}" min="0">
                                            </td>
                                            <input type="hidden" name="budget[{{ $subAccount->sub_account_id }}][account_id]" value="{{ $subAccount->sub_account_id }}">
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <button type="submit" class="btn btn-success mt-3">Save Budget</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
