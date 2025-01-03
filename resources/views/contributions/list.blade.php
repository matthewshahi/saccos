@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>List of Member Contributions</h1>
        <div class="header-part-right">
            <ul>
                @if(Auth::check())
                    <li>{{ Auth::user()->member_name }}</li>
                @endif
                @if(isset($currentPeriod))
                    <li><a href="{{ route('admin.periods') }}">{{ $currentPeriod->period_name }}</a></li>
                @endif
                <li><i class="i-Full-Screen header-icon d-none d-sm-inline-block" data-fullscreen=""></i></li>
            </ul>
        </div>
    </div>
    <div class="separator-breadcrumb border-top"></div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="card text-start">
        <div class="card-body">
            <form action="{{ url()->current() }}" method="get" class="custom-search-form">
                <div class="input-group mb-3">
                    <input name="pms_srch" type="text" id="pms_srch" value="{{ str_replace('%', '', $pms_srch) }}" class="form-control" placeholder="Search by name, ID, phone, email, etc.">
                    <div class="input-group-append">
                        <button class="btn btn-primary" type="submit">Search</button>
                    </div>
                </div>
            </form>
            <div class="table-responsive">
                <table id="memberContributionsTable" class="display table table-striped table-bordered"  style="width: 100%">
                    <thead>
                        <tr>
                            <th nowrap>#</th>
                            <th nowrap>Names</th>
                            <th nowrap>Sacco ID</th>
                            <th nowrap>Company</th>
                            <th nowrap>Share</th>
                            <th nowrap>FOSA</th>
                            @foreach($loanTypes as $loanType)
                                <th nowrap>{{ $loanType->loan_type_name }}</th>
                            @endforeach
                            <th nowrap>Total Deduction</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $totalShare = 0;
                            $totalFOSA = 0;
                            $loanTypeTotals = [];
                            $grandTotal = 0;
                        @endphp
                        @foreach($members as $index => $member)
                            @php
                                $totalShare += $member->member_share_contr_monthly;
                                $totalFOSA += $member->member_fosa_contr_monthly;
                                $grandTotal += $member->total_deduction;
                            @endphp
                            <tr>
                                <td nowrap>{{ $index + 1 }}</td>
                                <td nowrap>{{ $member->member_name }}</td>
                                <td nowrap>{{ $member->member_sacco_id }}</td>
                                <td nowrap>{{ $member->company_name }}</td>
                                <td nowrap>{{ number_format($member->member_share_contr_monthly, 2) }}</td>
                                <td nowrap>{{ number_format($member->member_fosa_contr_monthly, 2) }}</td>
                                @foreach($loanTypes as $loanType)
                                    @php
                                        $loanTypeTotal = $member->loan_contributions[$loanType->loan_type_id] ?? 0;
                                        if (!isset($loanTypeTotals[$loanType->loan_type_id])) {
                                            $loanTypeTotals[$loanType->loan_type_id] = 0;
                                        }
                                        $loanTypeTotals[$loanType->loan_type_id] += $loanTypeTotal;
                                    @endphp
                                    <td nowrap>{{ number_format($loanTypeTotal, 2) }}</td>
                                @endforeach
                                <td nowrap>{{ number_format($member->total_deduction, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr>
                            <th nowrap>#</th>
                            <th nowrap>Names</th>
                            <th nowrap>Sacco ID</th>
                            <th nowrap>Company</th>
                            <th nowrap>{{ number_format($totalShare, 2) }}</th>
                            <th nowrap>{{ number_format($totalFOSA, 2) }}</th>
                            @foreach($loanTypes as $loanType)
                                <th nowrap>{{ number_format($loanTypeTotals[$loanType->loan_type_id] ?? 0, 2) }}</th>
                            @endforeach
                            <th nowrap>{{ number_format($grandTotal, 2) }}</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <style>
    .form-group {
        margin-bottom: 1.5rem;
    }
    .col-form-label {
        font-weight: bold;
    }
    .btn-primary {
        background-color: #4e73df;
        border-color: #4e73df;
    }
    .btn-primary:hover {
        background-color: #2e59d9;
        border-color: #2653d4;
    }
    h5 {
        border-bottom: 1px solid #e0e0e0;
        padding-bottom: 0.5rem;
        margin-bottom: 1.5rem;
        color: #4e73df;
    }
</style>
<link href="https://cdn.datatables.net/1.11.5/css/jquery.dataTables.min.css" rel="stylesheet">
<link href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.dataTables.min.css" rel="stylesheet">

<script src="https://code.jquery.com/jquery-3.5.1.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.flash.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
 
<script>
    $(document).ready(function() {
        $('#memberContributionsTable').DataTable({
            dom: 'Bfrtip', // Allows the buttons to be displayed
            buttons: [
                'copy', 'csv', 'excel', 'pdf', 'print'
            ],
            searching: false, // Disables the default search box
            pageLength: 150, // Sets the number of rows per page to 150
            lengthChange: false, // Disables the ability to change the number of rows per page
            order: [[0, 'asc']] // Optional: Orders by the first column
        });
    });
</script>
@endsection


