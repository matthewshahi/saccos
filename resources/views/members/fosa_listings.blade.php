@extends('layouts.app')

@section('content')
@include("member_name")
<div class="row">
    <div class="col-md-12">
        <div class="card o-hidden mb-4">
            <div class="card-header d-flex align-items-center border-0">
                <h3 class="w-50 float-start card-title m-0">FOSA Contribution Listings</h3>
            </div>
            <div>
                <div class="table-responsive">
                    <table class="table" id="fosa_listings_table">
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
                            @foreach ($data['fosaContributions'] as $index => $fosa)
                                <tr>
                                    <th scope="row">{{ $index + 1 }}</th>
                                    <td>{{ substr($fosa->fosa_period, 0, 4) . '-' . substr($fosa->fosa_period, 4) }}</td>
                                    <td>{{ date('d-m-Y', strtotime($fosa->fosa_date_paid)) }}</td>
                                    <td>{{ $fosa->fosa_description }}</td>
                                    <td>{{ $fosa->fosa_doc_no }}</td>
                                    <td class="text-end">{{ number_format($fosa->fosa_amount_paying, 2) }}</td>
                                    <td class="text-end">{{ number_format($fosa->running_balance, 2) }}</td>
                                </tr>
                            @endforeach
                            @if ($data['fosaContributions']->isEmpty())
                                <tr>
                                    <td colspan="7" class="text-muted text-center">No FOSA contributions available</td>
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