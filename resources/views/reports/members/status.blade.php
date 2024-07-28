@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Consolidated - Shares, Capital, Fosa, Loans Reports</h1>
</div>
<div class="separator-breadcrumb border-top"></div>

<div class="row mb-4">
    <div class="col-md-12 mb-4">
        <div class="card text-start">
            <div class="card-body">
                <div class="card-title mb-3">Member Status Report</div>
                <div class="form-group">
                    <label for="statusFilter">Status</label>
                    <select id="statusFilter" class="form-control">
                        <option value="Y">Active</option>
                        <option value="N">Inactive</option>
                    </select>
                </div>
                <button id="downloadExcel" class="btn btn-success mb-3">Download Excel</button>
                <div id="members-table-container" style="overflow-x: auto;">
                    <table class="table table-bordered" id="members-table" style="white-space: nowrap;">
                        <thead>
                        <tr>
                            <th>#</th>
                            <th>Member Name</th>
                            <th>Company Name</th>
                            <th>National ID</th>
                            <th>Sacco ID</th>
                            <th>Status</th>
                            <th class="text-right">Total Shares</th>
                            <th class="text-right">Total Capital</th>
                            <!-- The loanTypes headers will be added dynamically -->
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

<!-- Include jQuery library -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/file-saver@2.0.5/FileSaver.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.16.9/xlsx.full.min.js"></script>

<script>
    $(document).ready(function() {
        let offset = 0;
        const limit = 5;
        let isLoading = false;
        let allRecordsLoaded = false;

        function fetchMembers(status) {
            if (isLoading || allRecordsLoaded) return;
            isLoading = true;

            $.ajax({
                url: "{{ route('reports.members.status.data') }}",
                method: "GET",
                data: { status: status, offset: offset, limit: limit },
                success: function(response) {
                    const members = response.members;
                    const loanTypes = response.loanTypes;

                    // Add loanTypes headers dynamically
                    if (offset === 0) {
                        let headers = `
                            <tr>
                                <th>#</th>
                                <th>Member Name</th>
                                <th>Company Name</th>
                                <th>National ID</th>
                                <th>Sacco ID</th>
                                <th>Status</th>
                                <th class="text-right">Total Shares</th>
                                <th class="text-right">Total Capital</th>`;
                        loanTypes.forEach(type => {
                            headers += `<th class="text-right">${type.loan_type_name} Loan Balance</th>`;
                        });
                        headers += `<th class="text-right">Total Loans</th></tr>`;
                        $('#members-table thead').html(headers);
                    }

                    let tableRows = '';
                    members.forEach((member, index) => {
                        let loanColumns = '';
                        let totalLoans = 0;
                        loanTypes.forEach(type => {
                            const loanBalance = member.loans[type.loan_type_name] ?? 0;
                            loanColumns += `<td class="text-right">${loanBalance.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>`;
                            totalLoans += loanBalance;
                        });

                        tableRows += `
                            <tr>
                                <td>${offset + index + 1}</td>
                                <td>${member.member_name}</td>
                                <td>${member.company_name}</td>
                                <td>${member.member_national_id}</td>
                                <td>${member.member_sacco_id}</td>
                                <td>${member.member_active}</td>
                                <td class="text-right">${parseFloat(member.total_shares).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                <td class="text-right">${parseFloat(member.total_capital).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                                ${loanColumns}
                                <td class="text-right">${totalLoans.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                            </tr>
                        `;
                    });

                    $('#members-table tbody').append(tableRows);

                    if (members.length < limit) {
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
                    $('#loading-status').text('Failed to fetch member data. Please try again later.');
                    isLoading = false;
                }
            });
        }

        $('#statusFilter').change(function() {
            offset = 0;
            allRecordsLoaded = false;
            $('#members-table tbody').empty();
            $('#loading-status').text('Loading...').addClass('loading-status');
            fetchMembers($(this).val());
        });

        // Initial load with active members
        fetchMembers('Y');

        // Regularly fetch more records
        setInterval(function() {
            fetchMembers($('#statusFilter').val());
        }, 5000);

        // Download Excel
        $('#downloadExcel').click(function() {
            let wb = XLSX.utils.table_to_book(document.getElementById('members-table'), { sheet: "Sheet JS" });
            XLSX.writeFile(wb, 'MemberStatusReport.xlsx');
        });
    });
</script>


@endsection
