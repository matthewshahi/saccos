<div class="col-12">
    <div class="card o-hidden mb-4">

        {{-- HEADER --}}
        <div class="card-header d-flex align-items-center">
            <h3 class="card-title m-0 flex-grow-1">
                Member Financial Position — As at {{ $period }}
            </h3>

            <a href="{{ route('reports.members.financial_position.export', ['period' => $period]) }}"
               class="btn btn-sm btn-outline-success">
                Export CSV
            </a>
        </div>

        {{-- BODY --}}
        <div class="card-body">

            {{-- SCROLL WRAPPER --}}
            <div class="table-responsive"
                 style="max-height:70vh; overflow:auto; white-space:nowrap;">

                <table class="table table-bordered table-striped table-sm text-center">

                    {{-- TABLE HEAD --}}
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>Sacco ID</th>
                            <th>National ID</th>
                            <th>Gender</th>
                            <th>Company</th>
                            <th>Department</th>
                            <th class="text-end">Savings</th>
                            <th class="text-end">FOSA</th>
                            <th class="text-end">Capital</th>

                            @foreach($loanTypes as $lt)
                                <th class="text-end">{{ $lt->loan_type_name }} Taken</th>
                                <th class="text-end">{{ $lt->loan_type_name }} Paid</th>
                            @endforeach
                        </tr>
                    </thead>

                    {{-- TABLE BODY --}}
                    <tbody>
                        @foreach($rows as $i => $r)
                            <tr>
                                <td>{{ $i + 1 }}</td>
                                <td class="text-start">{{ $r['member_name'] }}</td>
                                <td>{{ $r['member_sacco_id'] }}</td>
                                <td>{{ $r['member_national_id'] }}</td>
                                <td>{{ $r['member_gender'] }}</td>
                                <td>{{ $r['company'] }}</td>
                                <td>{{ $r['department'] }}</td>

                                <td class="text-end">{{ number_format($r['savings'], 2) }}</td>
                                <td class="text-end">{{ number_format($r['fosa'], 2) }}</td>
                                <td class="text-end">{{ number_format($r['capital'], 2) }}</td>

                                @foreach($r['loans'] as $loan)
                                    <td class="text-end">
                                        {{ number_format($loan['taken'], 2) }}
                                    </td>
                                    <td class="text-end">
                                        {{ number_format($loan['paid'], 2) }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>

                    {{-- TOTALS FOOTER --}}
                    <tfoot class="table-dark sticky-bottom">
                        <tr>
                            <th colspan="7" class="text-end">TOTALS</th>

                            <th class="text-end">{{ number_format($totals['savings'], 2) }}</th>
                            <th class="text-end">{{ number_format($totals['fosa'], 2) }}</th>
                            <th class="text-end">{{ number_format($totals['capital'], 2) }}</th>

                            @foreach($loanTypes as $lt)
                                <th class="text-end">
                                    {{ number_format($totals['loans'][$lt->loan_type_id]['taken'], 2) }}
                                </th>
                                <th class="text-end">
                                    {{ number_format($totals['loans'][$lt->loan_type_id]['paid'], 2) }}
                                </th>
                            @endforeach
                        </tr>
                    </tfoot>

                </table>
            </div>
        </div>
    </div>
</div>
