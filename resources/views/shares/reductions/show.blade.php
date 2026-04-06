{{-- resources/views/shares/reductions/show.blade.php --}}
@extends('layouts.app')

@section('content')
<div class="container-fluid">

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            {{ session('success') }}
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

        <div class="col-md-8">

            <div class="card o-hidden mb-4">
                <div class="card-header d-flex align-items-center">
                    <h3 class="w-50 float-start card-title m-0">Share Reduction Details</h3>
                    <div class="dropdown dropleft text-end w-50 float-end">
                        <button class="btn bg-gray-100" id="dropdownMenuButton_share_reduction_show" type="button" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                            <i class="nav-icon i-Gear-2"></i>
                        </button>
                        <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_share_reduction_show">
                            <a class="dropdown-item" href="{{ route('shares_reductions.index') }}">View All Share Reductions</a>
                            <a class="dropdown-item" href="{{ route('shares_reductions.create') }}">Add New Share Reduction</a>
                        </div>
                    </div>
                </div>

                <div class="card-body">
                    <div class="card-title mb-3">Reduction Record</div>

                    <div class="mb-3">
                        <a href="{{ route('shares_reductions.index') }}" class="btn btn-sm btn-outline-secondary me-2">
                            Back to Listing
                        </a>
                        <a href="{{ route('shares_reductions.create') }}" class="btn btn-sm btn-outline-primary">
                            New Share Reduction
                        </a>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <tbody>
                                <tr>
                                    <th style="width:220px;">Member Name</th>
                                    <td>{{ $row->member_name }}</td>
                                </tr>
                                <tr>
                                    <th>Sacco Number</th>
                                    <td>{{ $row->member_sacco_id }}</td>
                                </tr>
                                <tr>
                                    <th>Company</th>
                                    <td>{{ $row->company_name ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Department</th>
                                    <td>{{ $row->department_name ?? '-' }}</td>
                                </tr>
                                <tr>
                                    <th>Period</th>
                                    <td>{{ $row->share_period }}</td>
                                </tr>
                                <tr>
                                    <th>Date Paid</th>
                                    <td>
                                        {{ !empty($row->share_date_paid) ? \Carbon\Carbon::parse($row->share_date_paid)->format('d-m-Y') : '-' }}
                                    </td>
                                </tr>
                                <tr>
                                    <th>Document Number</th>
                                    <td>{{ $row->share_doc_no }}</td>
                                </tr>
                                <tr>
                                    <th>Amount Reduced</th>
                                    <td>
                                        <span class="badge bg-danger">
                                            -{{ number_format(abs((float) $row->share_amount_paying), 2) }}
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Paid By</th>
                                    <td>{{ $row->share_paid_by }}</td>
                                </tr>
                                <tr>
                                    <th>Description</th>
                                    <td>{{ $row->share_description }}</td>
                                </tr>
                                <tr>
                                    <th>Recorded On</th>
                                    <td>
                                        {{ !empty($row->share_transdate) ? \Carbon\Carbon::parse($row->share_transdate)->format('d-m-Y H:i:s') : '-' }}
                                    </td>
                                </tr>
                                <tr>
                                    <th>Share Entry ID</th>
                                    <td>{{ $row->share_id }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card o-hidden mb-4">
                <div class="card-header d-flex align-items-center">
                    <h3 class="w-50 float-start card-title m-0">Ledger Entries</h3>
                    <div class="text-end w-50 float-end">
                        <span class="badge bg-info">Double Entry</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="card-title mb-3">Related Ledger Postings</div>

                    <div class="table-responsive">
                        <table class="table table-striped table-bordered text-start">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Main Account</th>
                                    <th>Sub Account</th>
                                    <th>Doc No</th>
                                    <th>Period</th>
                                    <th>Date</th>
                                    <th class="text-end">Debit</th>
                                    <th class="text-end">Credit</th>
                                    <th>Description</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($ledgerRows as $index => $ledger)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>
                                            {{ $ledger->main_account_name ?? '-' }}
                                            @if(!empty($ledger->main_account_code))
                                                <br><small class="text-muted">{{ $ledger->main_account_code }}</small>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $ledger->sub_account_name ?? '-' }}
                                            @if(!empty($ledger->sub_account_code))
                                                <br><small class="text-muted">{{ $ledger->sub_account_code }}</small>
                                            @endif
                                        </td>
                                        <td>{{ $ledger->accounts_trans_doc_no }}</td>
                                        <td>{{ $ledger->accounts_trans_period }}</td>
                                        <td>
                                            {{ !empty($ledger->accounts_trans_dat_date) ? \Carbon\Carbon::parse($ledger->accounts_trans_dat_date)->format('d-m-Y') : '-' }}
                                        </td>
                                        <td class="text-end">{{ number_format((float) $ledger->accounts_trans_debit, 2) }}</td>
                                        <td class="text-end">{{ number_format((float) $ledger->accounts_trans_credit, 2) }}</td>
                                        <td>{{ $ledger->accounts_trans_decription }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="text-center text-muted py-4">
                                            No ledger entries found for this reduction.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>

                            @if(!empty($ledgerRows) && count($ledgerRows) > 0)
                                <tfoot>
                                    <tr>
                                        <th colspan="6" class="text-end">Totals</th>
                                        <th class="text-end">{{ number_format((float) collect($ledgerRows)->sum('accounts_trans_debit'), 2) }}</th>
                                        <th class="text-end">{{ number_format((float) collect($ledgerRows)->sum('accounts_trans_credit'), 2) }}</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            @endif
                        </table>
                    </div>
                </div>
            </div>

        </div>

        <div class="col-md-4">

            <div class="card o-hidden mb-4">
                <div class="card-header d-flex align-items-center">
                    <h3 class="w-50 float-start card-title m-0">Navigation</h3>
                    <div class="text-end w-50 float-end">
                        <span class="badge bg-primary">Links</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-grid gap-2">
                        <a href="{{ route('shares_reductions.index') }}" class="btn btn-outline-secondary btn-sm">
                            View All Share Reductions
                        </a>
                        <a href="{{ route('shares_reductions.create') }}" class="btn btn-outline-primary btn-sm">
                            Add New Share Reduction
                        </a>
                    </div>
                </div>
            </div>

            <div class="card o-hidden mb-4">
                <div class="card-header d-flex align-items-center">
                    <h3 class="w-50 float-start card-title m-0">Quick Summary</h3>
                    <div class="text-end w-50 float-end">
                        <span class="badge bg-danger">Reduction</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="summary-item">
                        <span>Member</span>
                        <strong>{{ $row->member_sacco_id }}</strong>
                    </div>
                    <div class="summary-item">
                        <span>Period</span>
                        <strong>{{ $row->share_period }}</strong>
                    </div>
                    <div class="summary-item">
                        <span>Document No</span>
                        <strong>{{ $row->share_doc_no }}</strong>
                    </div>
                    <div class="summary-item mb-0">
                        <span>Amount</span>
                        <strong class="text-danger">-{{ number_format(abs((float) $row->share_amount_paying), 2) }}</strong>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>


 
<style>
    .summary-item {
        display: flex;
        justify-content: space-between;
        gap: 1rem;
        padding: .7rem 0;
        border-bottom: 1px dashed #e9ecef;
        font-size: 13px;
    }

    @media (max-width: 767.98px) {
        .summary-item {
            flex-direction: column;
            gap: .15rem;
        }
    }
</style>
@endsection