@extends('layouts.app')
 
@section('content')
@include("member_name")
<div class="row">
    <div class="col-md-12">
        <div class="card o-hidden mb-4">
            <div class="card-header d-flex align-items-center border-0">
                <h3 class="w-50 float-start card-title m-0">Capital Contribution Listings</h3>
            </div>
            <div>
                <div class="table-responsive">
                    <table class="table" id="capital_listings_table">
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
                            @foreach ($data['capitalShares'] as $index => $capital)
                                <tr>
                                    <th scope="row">{{ $index + 1 }}</th>
                                    <td>{{ substr($capital->share_capitalperiod, 0, 4) . '-' . substr($capital->share_capitalperiod, 4) }}</td>
                                    <td>{{ date('d-m-Y', strtotime($capital->share_capitaldate_paid)) }}</td>
                                    <td>{{ $capital->share_capitaldescription }}</td>
                                    <td>{{ $capital->share_capitaldoc_no }}</td>
                                    <td class="text-end">{{ number_format($capital->share_capitalamount_paying, 2) }}</td>
                                    <td class="text-end">{{ number_format($capital->running_balance, 2) }}</td>
                                </tr>
                            @endforeach
                            @if ($data['capitalShares']->isEmpty())
                                <tr>
                                    <td colspan="7" class="text-muted text-center">No capital contributions available</td>
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