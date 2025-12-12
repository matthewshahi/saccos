@extends('layouts.app')

@section('content')
<div class="container py-4">

    <h2 class="mb-4">KASS Loan Import</h2>

    {{-- ===========================
         CURRENT STATS
       =========================== --}}
    <div class="mb-3">
        <h5>Current Stats</h5>
        <ul>
            <li>Staging Loans: <strong>{{ $stats['rows'] }}</strong></li>
            <li>Loans Created: <strong>{{ $stats['loans'] }}</strong></li>
            <li>Repayments Inserted: <strong>{{ $stats['pays'] }}</strong></li>
        </ul>
    </div>

    {{-- ===========================
         RUN IMPORT
       =========================== --}}
    <form method="POST" action="{{ route('kassloan.run') }}">
        @csrf
        <button class="btn btn-primary">Run Loan Import</button>
    </form>

    {{-- ===========================
         RESET LOAN TABLES
       =========================== --}}
    <form method="POST" action="{{ route('kassloan.reset') }}" class="mt-3">
        @csrf
        <button class="btn btn-danger"
                onclick="return confirm('Clear loans & repayments?')">
            Reset Loan Tables
        </button>
    </form>

    {{-- ===========================
         SUMMARY OUTPUT
       =========================== --}}
    @if(!empty($summary))
        <hr>
        <h4>Last Import Summary</h4>

        {{-- Array summary (normal import run) --}}
        @if(is_array($summary))
            <ul>
                <li>Rows Processed: <strong>{{ $summary['rows_processed'] ?? 0 }}</strong></li>
                <li>Loan Cycles Created: <strong>{{ $summary['loan_cycles_created'] ?? 0 }}</strong></li>
                <li>Payments Inserted: <strong>{{ $summary['payments_inserted'] ?? 0 }}</strong></li>

                {{-- Optional reset message --}}
                @if(!empty($summary['message']))
                    <li><em>{{ $summary['message'] }}</em></li>
                @endif
            </ul>

            {{-- ===========================
                 UNRESOLVED MEMBERS LIST
               =========================== --}}
            @if(!empty($summary['unresolved_members']))
                <hr>
                <h5>Unresolved Members ({{ count($summary['unresolved_members']) }})</h5>
                <ul>
                    @foreach($summary['unresolved_members'] as $u)
                        <li style="color:red">{{ $u }}</li>
                    @endforeach
                </ul>
            @endif

        {{-- String summary (after reset) --}}
        @elseif(is_string($summary))
            <p>{{ $summary }}</p>
        @endif
    @endif

</div>
@endsection
