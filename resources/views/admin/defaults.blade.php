@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>System Defaults</h1>
    <div class="header-part-right">
        <ul>
            @if(Auth::check())
                <li>{{ Auth::user()->member_name }}</li>
            @endif
            @if(isset($currentPeriod) && $currentPeriod)
                <li><a href="{{ route('admin.periods') }}">{{ $currentPeriod->period_name }}</a></li>
            @endif
            <li>
                <i class="i-Full-Screen header-icon d-none d-sm-inline-block" data-fullscreen=""></i>
            </li>
        </ul>
    </div>
</div>

<div class="separator-breadcrumb border-top"></div>

<div class="row">
    <div class="col-md-12 mb-4">
        <div class="card o-hidden mb-4">
            <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
                <h3 class="card-title m-0">System Defaults Listing</h3>

                <div class="d-flex gap-2 mt-2 mt-md-0">
                    <button type="button" class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addDefaultModal">
                        <i class="i-Add me-1"></i> Add New Default
                    </button>
                </div>
            </div>

            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        {{ session('success') }}
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif

                @if(session('error'))
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        @if(is_array(session('error')))
                            <ul class="mb-0 ps-3">
                                @foreach(session('error') as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        @else
                            {{ session('error') }}
                        @endif
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif

                @if($errors->any())
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>Please fix the following:</strong>
                        <ul class="mb-0 ps-3">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                @endif

                <form action="{{ route('admin.defaults.update') }}" method="POST">
                    @csrf

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-hover align-middle">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 70px;">#</th>
                                    <th style="min-width: 260px;">Default Name</th>
                                    <th style="min-width: 360px;">Default Value</th>
                                    <th style="width: 140px;" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($defaults as $default)
                                    @php
                                        $isAccountDefault = str_starts_with($default->default_name, 'default_')
                                            && str_ends_with($default->default_name, '_account');

                                        $selectedAccount = null;
                                        if ($isAccountDefault) {
                                            $selectedAccount = collect($subAccounts)->firstWhere('sub_account_id', (int) $default->default_value);
                                        }
                                    @endphp

                                    <tr>
                                        <td class="fw-bold text-center">{{ $loop->iteration }}</td>

                                        <td>
                                            <div class="fw-bold">{{ $default->default_name }}</div>
                                            <small class="text-muted">ID: {{ $default->default_id }}</small>
                                        </td>

                                        <td>
                                            @if($isAccountDefault)
                                                <select name="defaults[{{ $default->default_id }}]" class="form-control">
                                                    <option value="">-- Select Account --</option>
                                                    @foreach($subAccounts as $account)
                                                        <option
                                                            value="{{ $account->sub_account_id }}"
                                                            {{ (string) $default->default_value === (string) $account->sub_account_id ? 'selected' : '' }}
                                                        >
                                                            {{ $account->main_account_code }}/{{ $account->sub_account_code }}
                                                            - {{ $account->sub_account_name }}
                                                            ({{ $account->main_account_name }})
                                                        </option>
                                                    @endforeach
                                                </select>

                                                @if($selectedAccount)
                                                    <small class="text-muted d-block mt-2">
                                                        Current account:
                                                        <strong>
                                                            {{ $selectedAccount->main_account_code }}/{{ $selectedAccount->sub_account_code }}
                                                            - {{ $selectedAccount->sub_account_name }}
                                                        </strong>
                                                    </small>
                                                @else
                                                    <small class="text-warning d-block mt-2">
                                                        No valid sub-account currently linked.
                                                    </small>
                                                @endif
                                            @else
                                                <input
                                                    type="text"
                                                    name="defaults[{{ $default->default_id }}]"
                                                    class="form-control"
                                                    value="{{ old('defaults.' . $default->default_id, $default->default_value) }}"
                                                >
                                            @endif
                                        </td>

                                        <td class="text-center">
                                            <button
                                                type="button"
                                                class="btn btn-danger btn-sm"
                                                onclick="if(confirm('Delete this default permanently?')) document.getElementById('delete-default-{{ $default->default_id }}').submit();"
                                            >
                                                <i class="i-Close-Window me-1"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center text-muted">No defaults found.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-3 d-flex justify-content-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="i-Disk me-1"></i> Save Changes
                        </button>
                    </div>
                </form>

                @foreach($defaults as $default)
                    <form
                        id="delete-default-{{ $default->default_id }}"
                        action="{{ route('admin.defaults.destroy', $default->default_id) }}"
                        method="POST"
                        class="d-none"
                    >
                        @csrf
                        @method('DELETE')
                    </form>
                @endforeach
            </div>
        </div>
    </div>
</div>

<!-- Add Default Modal -->
<div class="modal fade" id="addDefaultModal" tabindex="-1" role="dialog" aria-labelledby="addDefaultModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <form action="{{ route('admin.defaults.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Default</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>

                <div class="modal-body">
                    <div class="form-group mb-3">
                        <label for="default_name"><strong>Default Name</strong></label>
                        <input
                            type="text"
                            class="form-control"
                            id="default_name"
                            name="default_name"
                            value="{{ old('default_name') }}"
                            required
                        >
                        <small class="text-muted">
                            Example: <code>default_bank_account</code> or <code>loan_interest_insurance</code>
                        </small>
                    </div>

                    <div class="form-group mb-3">
                        <label for="default_value"><strong>Default Value</strong></label>
                        <input
                            type="text"
                            class="form-control"
                            id="default_value"
                            name="default_value"
                            value="{{ old('default_value') }}"
                            required
                        >
                        <small class="text-muted">
                            For account defaults, save the numeric sub-account ID.
                        </small>
                    </div>
                </div>

                <div class="modal-footer d-flex justify-content-between flex-column flex-sm-row">
                    <button type="button" class="btn btn-secondary mb-2 mb-sm-0" data-dismiss="modal">
                        Close
                    </button>
                    <button type="submit" class="btn btn-primary">
                        Save Default
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
@endsection