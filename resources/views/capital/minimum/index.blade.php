@extends('layouts.app')

@section('content')
<div class="col-md-12">
    <div class="card mb-4">
        <div class="card-body">
            <div class="card-title mb-3">Minimum Capital Transfer</div>

            @if(session('success'))
                <div class="alert alert-success mb-3">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger mb-3">
                    {{ session('error') }}
                </div>
            @endif

            @if($setupError)
                <div class="alert alert-danger mb-3">
                    {{ $setupError }}
                </div>
            @endif

            <form method="GET" action="{{ route('minimum_capital.index') }}">
                <div class="row">
                    <div class="col-md-4 form-group mb-3">
                        <label for="period">Period</label>
                        <input class="form-control" id="period" name="period" value="{{ $period }}" placeholder="YYYYMM">
                    </div>

                    <div class="col-md-4 form-group mb-3">
                        <label for="posting_date">Posting Date</label>
                        <input class="form-control" id="posting_date" name="posting_date" value="{{ substr($postingDate, 0, 10) }}" placeholder="YYYY-MM-DD">
                    </div>

                    <div class="col-md-4 form-group mb-3 d-flex align-items-end">
                        <button class="btn btn-primary" type="submit">Preview</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="col-md-12">
    <div class="row">
        <div class="col-md-3 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted mb-1">Minimum Capital</div>
                    <h4 class="mb-0">KES {{ number_format($summary['minimum_capital'], 2) }}</h4>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted mb-1">Members Affected</div>
                    <h4 class="mb-0">{{ number_format($summary['eligible_members']) }}</h4>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted mb-1">Missing Capital</div>
                    <h4 class="mb-0">KES {{ number_format($summary['total_missing_capital'], 2) }}</h4>
                </div>
            </div>
        </div>

        <div class="col-md-3 mb-4">
            <div class="card">
                <div class="card-body">
                    <div class="text-muted mb-1">Amount to Transfer</div>
                    <h4 class="mb-0">KES {{ number_format($summary['total_transferable'], 2) }}</h4>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="col-md-12">
    <div class="card mb-4">
        <div class="card-body">
            <div class="card-title mb-3">Ledger Setup</div>

            <div class="row">
                <div class="col-md-6 form-group mb-3">
                    <label>Member Shares Ledger</label>
                    <input class="form-control" value="{{ $summary['share_account'] ? $summary['share_account'] . ' - ' . $summary['share_account_name'] : 'Not configured' }}" readonly>
                </div>

                <div class="col-md-6 form-group mb-3">
                    <label>Share Capital Ledger</label>
                    <input class="form-control" value="{{ $summary['capital_account'] ? $summary['capital_account'] . ' - ' . $summary['capital_account_name'] : 'Not configured' }}" readonly>
                </div>

                <div class="col-md-6 form-group mb-3">
                    <label>Fully Covered Members</label>
                    <input class="form-control" value="{{ number_format($summary['members_fully_fixed']) }}" readonly>
                </div>

                <div class="col-md-6 form-group mb-3">
                    <label>Partially Covered Members</label>
                    <input class="form-control" value="{{ number_format($summary['members_partially_fixed']) }}" readonly>
                </div>
            </div>

            <form method="POST" action="{{ route('minimum_capital.process') }}" onsubmit="return confirm('Confirm transfer from member shares to share capital?');">
                @csrf
                <input type="hidden" name="period" value="{{ $period }}">
                <input type="hidden" name="posting_date" value="{{ substr($postingDate, 0, 10) }}">

                <button
                    class="btn btn-danger"
                    type="submit"
                    @if($setupError || $summary['total_transferable'] <= 0) disabled @endif
                >
                    Process Transfer
                </button>
            </form>
        </div>
    </div>
</div>

<div class="col-md-12">
    <div class="card mb-4">
        <div class="card-body">
            <div class="card-title mb-3">Members Preview</div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Member</th>
                            <th>SACCO ID</th>
                            <th>National ID</th>
                            <th class="text-right">Shares</th>
                            <th class="text-right">Capital</th>
                            <th class="text-right">Shortfall</th>
                            <th class="text-right">Transfer</th>
                            <th class="text-right">Shares After</th>
                            <th class="text-right">Capital After</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row->member_name }}</td>
                                <td>{{ $row->member_sacco_id }}</td>
                                <td>{{ $row->member_national_id }}</td>
                                <td class="text-right">{{ number_format($row->current_share_balance, 2) }}</td>
                                <td class="text-right">{{ number_format($row->current_capital_balance, 2) }}</td>
                                <td class="text-right">{{ number_format($row->missing_capital, 2) }}</td>
                                <td class="text-right">{{ number_format($row->transfer_amount, 2) }}</td>
                                <td class="text-right">{{ number_format($row->share_balance_after, 2) }}</td>
                                <td class="text-right">{{ number_format($row->capital_balance_after, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center">No members found for transfer.</td>
                            </tr>
                        @endforelse
                    </tbody>
                    @if($rows->count() > 0)
                        <tfoot>
                            <tr>
                                <th colspan="3">Totals</th>
                                <th class="text-right">{{ number_format($summary['total_current_shares'], 2) }}</th>
                                <th class="text-right">{{ number_format($summary['total_current_capital'], 2) }}</th>
                                <th class="text-right">{{ number_format($summary['total_missing_capital'], 2) }}</th>
                                <th class="text-right">{{ number_format($summary['total_transferable'], 2) }}</th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</div>
@endsection