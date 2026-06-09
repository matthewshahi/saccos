@extends('layouts.app')

@section('content')
@php
    $records = $records ?? collect();
    $products = $products ?? collect();

    $q = $q ?? request('q', '');
    $product_id = $product_id ?? request('product_id', '');
    $status = $status ?? request('status', '');

    $recordsCanPaginate = is_object($records) && method_exists($records, 'links');
@endphp

<div class="main-content pt-4">
    <div class="breadcrumb">
        <h1>Special Saving Accounts</h1>
        <ul>
            <li><a href="{{ route('special_savings.dashboard') }}">Special Savings</a></li>
            <li>Accounts</li>
        </ul>
    </div>

    <div class="separator-breadcrumb border-top"></div>

    @include('special_savings.partials.alerts')
    @include('special_savings.partials.nav')

    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <div class="card-title mb-0">Accounts</div>

                        <a href="{{ route('special_savings.accounts.create') }}" class="btn btn-primary btn-sm">
                            Open Account
                        </a>
                    </div>

                    <form method="GET" action="{{ route('special_savings.accounts.index') }}" class="row mb-3">
                        <div class="col-md-4 form-group mb-2">
                            <input
                                type="text"
                                name="q"
                                value="{{ $q }}"
                                class="form-control"
                                placeholder="Search by member, SACCO no, phone, ID, or account">
                        </div>

                        <div class="col-md-3 form-group mb-2">
                            <select name="product_id" class="form-control">
                                <option value="">All Products</option>

                                @foreach($products as $product)
                                    <option
                                        value="{{ $product->special_saving_product_id }}"
                                        {{ (string) $product_id === (string) $product->special_saving_product_id ? 'selected' : '' }}>
                                        {{ $product->special_saving_product_name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2 form-group mb-2">
                            <select name="status" class="form-control">
                                <option value="">All Statuses</option>

                                @foreach(['Active', 'Frozen', 'Dormant', 'Closed'] as $item)
                                    <option value="{{ $item }}" {{ $status === $item ? 'selected' : '' }}>
                                        {{ $item }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div class="col-md-2 form-group mb-2">
                            <button class="btn btn-secondary btn-block">
                                Filter
                            </button>
                        </div>

                        <div class="col-md-1 form-group mb-2">
                            <a href="{{ route('special_savings.accounts.index') }}" class="btn btn-outline-secondary btn-block">
                                Reset
                            </a>
                        </div>
                    </form>

                    <div class="table-responsive">
                        <table class="table table-bordered table-sm">
                            <thead>
                                <tr>
                                    <th>Account</th>
                                    <th>Member</th>
                                    <th>Product</th>
                                    <th class="text-end">Principal</th>
                                    <th class="text-end">Accrued Interest</th>
                                    <th class="text-end">Available Interest</th>
                                    <th class="text-end">Total</th>
                                    <th>Status</th>
                                    <th style="width:210px;">Action</th>
                                </tr>
                            </thead>

                            <tbody>
                                @forelse($records as $row)
                                    <tr>
                                        <td>{{ $row->special_saving_account_number ?? '-' }}</td>

                                        <td>
                                            {{ $row->member_name ?? '-' }}

                                            <small class="text-muted d-block">
                                                SACCO: {{ $row->member_sacco_id ?? '-' }}
                                            </small>

                                            <small class="text-muted d-block">
                                                Phone: {{ $row->member_phone_no ?? '-' }}
                                            </small>
                                        </td>

                                        <td>{{ $row->special_saving_product_name ?? '-' }}</td>

                                        <td class="text-end">
                                            {{ number_format((float) ($row->special_saving_account_principal_balance ?? 0), 2) }}
                                        </td>

                                        <td class="text-end">
                                            {{ number_format((float) ($row->special_saving_account_accrued_interest_balance ?? 0), 2) }}
                                        </td>

                                        <td class="text-end">
                                            {{ number_format((float) ($row->special_saving_account_available_interest_balance ?? 0), 2) }}
                                        </td>

                                        <td class="text-end">
                                            {{ number_format((float) ($row->special_saving_account_total_balance ?? 0), 2) }}
                                        </td>

                                        <td>{{ $row->special_saving_account_status ?? '-' }}</td>

                                        <td>
                                            @if(!empty($row->special_saving_account_id))
                                                <a
                                                    href="{{ route('special_savings.accounts.show', $row->special_saving_account_id) }}"
                                                    class="btn btn-sm btn-info">
                                                    View
                                                </a>

                                                <a
                                                    href="{{ route('special_savings.accounts.edit', $row->special_saving_account_id) }}"
                                                    class="btn btn-sm btn-primary">
                                                    Edit
                                                </a>

                                                <a
                                                    href="{{ route('special_savings.accounts.statement', $row->special_saving_account_id) }}"
                                                    class="btn btn-sm btn-outline-secondary">
                                                    Statement
                                                </a>
                                            @else
                                                -
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted">
                                            No accounts found.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    @if($recordsCanPaginate)
                        {{ $records->appends(request()->query())->links() }}
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection