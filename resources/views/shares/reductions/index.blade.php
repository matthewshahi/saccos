{{-- resources/views/shares/reductions/index.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid">

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {!! session('success') !!}
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('warning'))
        <div class="alert alert-warning alert-dismissible fade show" role="alert">
            {!! session('warning') !!}
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('info'))
        <div class="alert alert-info alert-dismissible fade show" role="alert">
            {!! session('info') !!}
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <ul class="mb-0">
                @foreach($errors->all() as $error)
                    <li>{!! $error !!}</li>
                @endforeach
            </ul>
            <button class="btn-close" type="button" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="row">
        <div class="col-md-12">
            <div class="card o-hidden mb-4">
                <div class="card-header d-flex align-items-center">
                    <h3 class="w-50 float-start card-title m-0">Share Reduction Entries</h3>
                    <div class="dropdown dropleft text-end w-50 float-end">
                        <button class="btn bg-gray-100" id="dropdownMenuButton_share_reductions" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="nav-icon i-Gear-2"></i>
                        </button>
                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_share_reductions">
                            <a class="dropdown-item" href="{{ route('shares_reductions.create') }}">Add New Share Reduction</a>
                            <a class="dropdown-item" href="{{ route('shares_reductions.index') }}">Refresh Listing</a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="card-title mb-3">Processed Member Share / Deposit Reductions</div>

                    <div class="mb-3 d-flex flex-wrap gap-2">
                        <a href="{{ route('shares_reductions.create') }}" class="btn btn-primary btn-sm">
                            Add New Share Reduction
                        </a>
                        <a href="{{ route('shares_reductions.index') }}" class="btn btn-outline-secondary btn-sm">
                            Refresh Listing
                        </a>
                    </div>

                    <div class="alert alert-light border small text-muted mb-3">
                        This list shows posted share reduction entries only. If safety controls were enabled during posting, some members may have been skipped because they were not active in the selected period or the deduction would have pushed their share balance below zero.
                    </div>

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered text-start align-middle">
                            <thead>
                                <tr>
                                    <th style="width:60px;">#</th>
                                    <th>Member</th>
                                    <th>Sacco No</th>
                                    <th>Company</th>
                                    <th>Department</th>
                                    <th>Period</th>
                                    <th>Date</th>
                                    <th>Doc No</th>
                                    <th class="text-end">Amount</th>
                                    <th>Description</th>
                                    <th style="width:90px;" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($rows as $index => $row)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $row->member_name }}</td>
                                        <td>{{ $row->member_sacco_id }}</td>
                                        <td>{{ $row->company_name ?? '-' }}</td>
                                        <td>{{ $row->department_name ?? '-' }}</td>
                                        <td>{{ $row->share_period }}</td>
                                        <td>
                                            {{ !empty($row->share_date_paid) ? \Carbon\Carbon::parse($row->share_date_paid)->format('d-m-Y') : '-' }}
                                        </td>
                                        <td>{{ $row->share_doc_no }}</td>
                                        <td class="text-end">
                                            <span class="badge bg-danger">
                                                -{{ number_format(abs((float) $row->share_amount_paying), 2) }}
                                            </span>
                                        </td>
                                        <td>{{ $row->share_description }}</td>
                                        <td class="text-center">
                                            <a class="text-primary me-2" href="{{ route('shares_reductions.show', $row->share_id) }}" title="View">
                                                <i class="nav-icon i-Eye fw-bold"></i>
                                            </a>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="11" class="text-center text-muted py-4">
                                            No share reduction records found.
                                            <div class="mt-2">
                                                <a href="{{ route('shares_reductions.create') }}" class="btn btn-sm btn-outline-primary">
                                                    Create First Entry
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>

                            @if(!empty($rows) && count($rows) > 0)
                                <tfoot>
                                    <tr>
                                        <th colspan="8" class="text-end">Total Reduced</th>
                                        <th class="text-end">
                                            {{ number_format(abs((float) collect($rows)->sum('share_amount_paying')), 2) }}
                                        </th>
                                        <th colspan="2"></th>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection