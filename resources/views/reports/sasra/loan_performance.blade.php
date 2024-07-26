@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>{{ $title }}</h1>
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

<div class="row mb-4">
    <div class="col-md-12 mb-4">
        <div class="card text-start">
            <div class="card-body">
                <button id="downloadExcel" class="btn btn-success mb-3">Download Excel</button>
                <div id="shares-table-container" style="overflow-x: auto;">
                    <table class="table table-bordered" id="shares-table" style="white-space: nowrap;">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Name</th>
                                <th>ID No</th>
                                <th>Loan Type</th>
                                <th>Period Taken</th>
                                <th>Last Paid</th>
                                <th>Loan Taken</th>
                                <th>Outstanding Amount</th>
                                <th>Loan Category</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
                <div id="loading-status" class="loading-status">Loading...</div>
            </div>
        </div>
    </div>
</div>
 
<style>
    .loading-status {
        position: fixed;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        font-size: 36px;
        font-weight: bold;
        color: #000;
        z-index: 9999;
        animation: blinkingText 1.2s infinite;
    }

    @keyframes blinkingText {
        0% { color: #000; }
        49% { color: #000; }
        50% { color: transparent; }
        99% { color: transparent; }
        100% { color: #000; }
    }

    .table {
        position: relative;
        z-index: 1;
    }
</style>
 
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/file-saver@2.0.5/FileSaver.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>

<script>
    $(document).ready(function() {
        let offset = 0;
        const limit = 5;
        let isLoading = false;
        let allRecordsLoaded = false;
        const version = "{{ $version }}";

        function fetchShares() {
            if (isLoading || allRecordsLoaded) return;
            isLoading = true;

            $.ajax({
                url: "{{ route('reports.sasra.loanperformance.data') }}",
                method: "GET",
                data: { offset: offset, limit: limit, version: version },
                success: function(response) {
                    const loans = response.loans;

                    if (loans.length === 0) {
                        allRecordsLoaded = true;
                        $('#loading-status').text('All records loaded').removeClass('loading-status');
                        return;
                    }

                    let tableRows = '';
                    loans.forEach((loan, index) => {
                        tableRows += `
                            <tr>
                                <td>${offset + index + 1}</td>
                                <td>${loan.member_name}</td>
                                <td>${loan.member_national_id}</td>
                                <td>${loan.loan_type_name}</td>
                                <td>${loan.loan_taken_period}</td>
                                <td>${loan.last_paid || 'N/A'}</td>
                                <td style="text-align:right">${parseFloat(loan.loan_amount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                <td style="text-align:right">${parseFloat(loan.OutstandingAmount).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                <td>${loan.category}</td>
                            </tr>
                        `;
                    });

                    $('#shares-table tbody').append(tableRows);

                    offset += limit;
                    isLoading = false;
                    $('#loading-status').text('Loading...').addClass('loading-status');
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    $('#loading-status').text('Failed to fetch loan data. Please try again later.');
                    isLoading = false;
                }
            });
        }

        // Initial load
        fetchShares();

        // Regularly fetch more records
        setInterval(function() {
            fetchShares();
        }, 5000);

        // Download Excel
        $('#downloadExcel').click(function() {
            let wb = XLSX.utils.table_to_book(document.getElementById('shares-table'), { sheet: "Sheet JS" });
            XLSX.writeFile(wb, 'SasraLoanPerformanceReport.xlsx');
        });
    });
</script>
@endsection
