@extends('layouts.app')

@section('content')
@include("member_name")
@include("dashboard.junior-context-banner")
<div class="row">
    <div class="col-md-12">
        <div class="card o-hidden mb-4">
            <div class="card-header d-flex align-items-center border-0">
                <h3 class="w-50 float-start card-title m-0">Savings / Contributions Listings</h3>
            </div>
            <div>
                <div class="table-responsive">
                    <table class="table" id="share_listings_table" style="white-space: nowrap;">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Period</th>
                                <th>Date Paid</th>
                                <th>Description</th>
                                <th>Document No</th>
                                <th class="text-end">Amount Paying (Ksh)</th>
                                <th class="text-end">Running Balance (Ksh)</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($data['shares'] as $index => $share)
                                <tr>
                                    <th scope="row">{{ $index + 1 }}</th>
                                    <td>{{ substr($share->share_period, 0, 4) . '-' . substr($share->share_period, 4) }}</td>
                                    <td>{{ date('d-m-Y', strtotime($share->share_date_paid)) }}</td>
                                    <td>{{ $share->share_description }}</td>
                                    <td>{{ $share->share_doc_no }}</td>
                                    <td class="text-end">{{ number_format($share->share_amount_paying, 2) }}</td>
                                    <td class="text-end">{{ number_format($share->running_balance, 2) }}</td>
                                </tr>
                            @endforeach
                            @if ($data['shares']->isEmpty())
                                <tr>
                                    <td colspan="7" class="text-muted text-center">No share listings available</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection