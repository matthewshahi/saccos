@extends('layouts.app')

@section('title', 'Loan Deduction Types')

@section('content')
<div class="breadcrumb">
    <h1>Loan Deduction Types</h1>
    <ul>
        <li><a href="{{ route('dashboard') }}">Dashboard</a></li>
        <li>Loans</li>
        <li>Deduction Types</li>
    </ul>
</div>

<div class="separator-breadcrumb border-top"></div>

<div class="row">
    <div class="col-md-12">
        @include('partials.alerts')

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h4 class="card-title mb-0">Loan Deduction Types</h4>
                    <small class="text-muted">Manage reusable deduction and charge types used in loan processing.</small>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('loans.deduction-types') }}" class="btn btn-outline-secondary btn-sm">
                        <i class="i-File-Horizontal-Text"></i> Refresh List
                    </a>
                    <a href="{{ route('loans.deduction-types.add') }}" class="btn btn-primary btn-sm">
                        <i class="i-Add"></i> Add Deduction Type
                    </a>
                </div>
            </div>

            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 60px;">#</th>
                                <th>Name</th>
                                <th>Code</th>
                                <th>Value Type</th>
                                <th class="text-end">Default Value</th>
                                <th>Effect</th>
                                <th>Posting Account</th>
                                <th>Status</th>
                                <th>Description</th>
                                <th style="width: 170px;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($records as $key => $row)
                                <tr>
                                    <td>{{ $key + 1 }}</td>
                                    <td>{{ $row->deduction_type_name }}</td>
                                    <td>{{ $row->deduction_type_code ?: '-' }}</td>
                                    <td>
                                        @if($row->deduction_type_value_type === 'PERCENT')
                                            Percent
                                        @else
                                            Fixed Amount
                                        @endif
                                    </td>
                                    <td class="text-end">{{ number_format((float) $row->deduction_type_default_value, 2) }}</td>
                                    <td>
                                        @if($row->deduction_type_effect === 'ADD_TO_LOAN')
                                            <span class="badge bg-info text-dark">Add to Loan</span>
                                        @elseif($row->deduction_type_effect === 'DEDUCT_FROM_DISBURSEMENT')
                                            <span class="badge bg-warning text-dark">Deduct from Disbursement</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(!empty($row->sub_account_name))
                                            <strong>{{ $row->sub_account_name }}</strong><br>
                                            <small class="text-muted">
                                                {{ $row->main_account_code ?? '' }}{{ !empty($row->main_account_code) && !empty($row->sub_account_code) ? ' / ' : '' }}{{ $row->sub_account_code ?? '' }}
                                                @if(!empty($row->main_account_type))
                                                    - {{ $row->main_account_type }}
                                                @endif
                                            </small>
                                        @else
                                            <span class="text-muted">Not Set</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if((int) $row->deduction_type_active === 1)
                                            <span class="badge bg-success">Active</span>
                                        @else
                                            <span class="badge bg-secondary">Inactive</span>
                                        @endif
                                    </td>
                                    <td>{{ $row->deduction_type_description ?: '-' }}</td>
                                    <td>
                                        <div class="d-flex flex-wrap gap-1">
                                            <a href="{{ route('loans.deduction-types.edit', $row->deduction_type_id) }}" class="btn btn-sm btn-primary">
                                                Edit
                                            </a>

                                            <form action="{{ route('loans.deduction-types.delete', $row->deduction_type_id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this deduction type?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="btn btn-sm btn-danger">
                                                    Delete
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="text-center text-muted">No loan deduction types found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection