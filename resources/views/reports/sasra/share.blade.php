@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>SASRA - Member Share Report</h1>
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
                                <th>ID#</th>
                                <th>Sacco. No.</th>
                                <th>Mem. Name</th>
                                <th>Type</th>
                                <th>Amount</th>
                                <th>Status</th>
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

        function fetchShares() {
            if (isLoading || allRecordsLoaded) return;
            isLoading = true;

            $.ajax({
                url: "{{ route('reports.sasra.share.data') }}",
                method: "GET",
                data: { offset: offset, limit: limit },
                success: function(response) {
                    const sacco_shares = response.sacco_shares;

                    let tableRows = '';
                    sacco_shares.forEach((share, index) => {
                        tableRows += `
                            <tr>
                                <td>${offset + index + 1}</td>
                                <td>${share.member_id}</td>
                                <td>${share.member_sacco_id}</td>
                                <td>${share.member_name}</td>
                                <td>${share.type}</td>
                                <td style="text-align:right">${parseFloat(share.member_total_share).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                <td>${share.status}</td>
                            </tr>
                        `;
                    });

                    $('#shares-table tbody').append(tableRows);

                    if (sacco_shares.length < limit) {
                        allRecordsLoaded = true;
                        $('#loading-status').text('All records loaded').removeClass('loading-status');
                    } else {
                        offset += limit;
                        isLoading = false;
                        $('#loading-status').text('Loading...').addClass('loading-status');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error:', error);
                    $('#loading-status').text('Failed to fetch share data. Please try again later.');
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
            XLSX.writeFile(wb, 'SasraShareReport.xlsx');
        });
    });
</script>
@endsection
