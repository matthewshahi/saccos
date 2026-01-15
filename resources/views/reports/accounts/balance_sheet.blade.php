@extends('layouts.app')

@section('content')
<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between">
        <h3>Balance Sheet</h3>
        @include('includes.accounts_nav')
    </div>

    <div class="card-body">

        <p class="text-muted small">
            Period: {{ $dateFrom }} to {{ $dateTo }}
        </p>

        <div class="table-responsive">
            <table class="table table-bordered align-middle">

                <thead class="table-light text-center">
                    <tr>
                        <th width="45%">ASSETS</th>
                        <th width="15%">KES</th>
                        <th width="30%">LIABILITIES &amp; CAPITAL</th>
                        <th width="10%">KES</th>
                    </tr>
                </thead>

                @php
                    $rightSide = collect()
                        ->concat($liabilities)
                        ->concat($capital);

                    $maxRows = max($assets->count(), $rightSide->count());
                @endphp

                <tbody>
                    @for($i = 0; $i < $maxRows; $i++)
                        <tr>
                            {{-- ASSETS --}}
                            <td>{{ $assets[$i]->sub_account_name ?? '' }}</td>
                            <td class="text-end">
                                @if(isset($assets[$i]))
                                    {{ number_format(
                                        ($assets[$i]->debit ?? 0) > 0
                                            ? $assets[$i]->debit
                                            : $assets[$i]->credit,
                                        2
                                    ) }}
                                @endif
                            </td>

                            {{-- RIGHT SIDE --}}
                            <td>{{ $rightSide[$i]->sub_account_name ?? '' }}</td>
                            <td class="text-end">
                                @if(isset($rightSide[$i]))
                                    {{ number_format(
                                        ($rightSide[$i]->credit ?? 0) > 0
                                            ? $rightSide[$i]->credit
                                            : $rightSide[$i]->debit,
                                        2
                                    ) }}
                                @endif
                            </td>
                        </tr>
                    @endfor
                </tbody>

                <tfoot class="table-dark fw-bold">
                    <tr>
                        <td class="text-end">Total Assets</td>
                        <td class="text-end">{{ number_format($totalAssets, 2) }}</td>
                        <td class="text-end">Total Liabilities &amp; Capital</td>
                        <td class="text-end">{{ number_format($totalRight, 2) }}</td>
                    </tr>
                </tfoot>

            </table>
        </div>

    </div>
</div>
@endsection
