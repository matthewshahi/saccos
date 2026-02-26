@extends('layouts.app')

@section('content')
@include("member_name")
@include("dashboard.junior-context-banner")

<div class="row">
    <div class="col-md-12">
        <div class="card o-hidden mb-4">
            <div class="card-header d-flex align-items-center border-0">
                <h3 class="w-50 float-start card-title m-0">FOSA and Other Contributions Listings</h3>
            </div>

            <!-- Add padding left/right -->
            <div class="table-responsive px-4 pb-4">

                @foreach ($data['fosaGrouped'] as $typeName => $rows)

                    {{-- TYPE HEADER --}}
                    <div class="mt-4 mb-3">
                        <h4 class="text-primary fw-bold border-bottom pb-1">
                            {{ $typeName }}
                        </h4>
                    </div>

                    <table class="table table-striped table-sm mb-5 shadow-sm rounded">
                        <thead class="table-light">
                            <tr>
                                <th>#</th>
                                <th>Period</th>
                                <th>Date Paid</th>
                                <th>Description</th>
                                <th>Document No</th>
                                <th class="text-end">Amount (Ksh)</th>
                                <th class="text-end">Running Balance (Ksh)</th>
                            </tr>
                        </thead>

                        <tbody>
                            @foreach ($rows as $i => $fosa)
                                <tr>
                                    <td>{{ $i + 1 }}</td>

                                    {{-- Period YYYY-MM --}}
                                    <td>{{ substr($fosa->fosa_period, 0, 4) }}-{{ substr($fosa->fosa_period, 4, 2) }}</td>

                                    {{-- Date --}}
                                    <td>{{ \Carbon\Carbon::parse($fosa->fosa_date_paid)->format('d-m-Y') }}</td>

                                    <td>{{ $fosa->fosa_description }}</td>

                                    <td>{{ $fosa->fosa_doc_no }}</td>

                                    <td class="text-end">{{ number_format($fosa->fosa_amount_paying, 2) }}</td>

                                    <td class="text-end fw-bold">{{ number_format($fosa->running_balance, 2) }}</td>
                                </tr>
                            @endforeach

                            {{-- SUBTOTAL --}}
                            <tr class="table-secondary fw-bold">
                                <td colspan="6" class="text-end">Subtotal for {{ $typeName }}</td>
                                <td class="text-end">{{ number_format($rows->last()->running_balance, 2) }}</td>
                            </tr>

                        </tbody>
                    </table>

                @endforeach

                {{-- IF COMPLETELY EMPTY --}}
                @if ($data['fosaGrouped']->isEmpty())
                    <div class="text-center text-muted p-3">
                        No FOSA contributions available
                    </div>
                @endif

            </div>

        </div>
    </div>
</div>
@endsection
