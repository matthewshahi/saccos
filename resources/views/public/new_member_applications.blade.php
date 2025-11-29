@extends('layouts.app')

@section('content')
    <div class="breadcrumb d-flex justify-content-between align-items-center">
        <h1>New Member Applications</h1>
    </div>
    <div class="separator-breadcrumb border-top"></div>



    <div class="col-md-12">
        <div class="card o-hidden mb-4">
            <div class="card-header d-flex align-items-center">
                <h3 class="w-50 float-start card-title m-0">New Member Applications</h3>

                <div class="dropdown dropleft text-end w-50 float-end">
                    <button class="btn bg-gray-100" id="dropdownMenuButton_table2" type="button" data-bs-toggle="dropdown"
                        aria-haspopup="true" aria-expanded="false">
                        <i class="nav-icon i-Gear-2"></i>
                    </button>
                    <div class="dropdown-menu" aria-labelledby="dropdownMenuButton_table2">
                        <a class="dropdown-item" href="{{ route('members.list') }}">Refresh List</a>
                        <a class="dropdown-item" href="#">Export All (CSV)</a>
                    </div>
                </div>
            </div>

            <div class="card-body">
                <!-- Search Bar -->
                <form action="{{ route('members.list') }}" method="GET" class="mb-3">
                    <div class="input-group">
                        <input type="text" name="search" class="form-control"
                            placeholder="Search by name, email, phone, ID, or location" value="{{ request('search') }}">
                        <button type="submit" class="btn btn-primary">Search</button>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover text-center">
                        <thead class="thead-light">
<tr>
    <th>#</th>
    <th>Name & Contact</th>
    <th>Date Applied</th>
    <th>Gender</th>
    <th>Location</th>
    <th>Monthly Income</th>
    <th>Exported?</th>
    <th>Contacted</th>
    <th>Actions</th>
</tr>
</thead>

<tbody>
@foreach ($members as $index => $member)
<tr>

    <td>{{ $members->firstItem() + $index }}</td>

    <!-- Name & Contact -->
    <td class="text-start">

        <div class="fw-bold" style="font-size:15px;">
            {{ strtoupper($member->first_name.' '.$member->last_name) }}
        </div>

        <div class="text-muted small mt-1">
            <i class="i-Phone"></i> {{ $member->phone ?? 'N/A' }}
            &nbsp; | &nbsp;
            <i class="i-ID-Card"></i> {{ $member->national_id ?? 'N/A' }}
        </div>

        @if ($member->email)
        <div class="text-muted small">
            <i class="i-Mail"></i> {{ $member->email }}
        </div>
        @endif

    </td>

    <!-- Date -->
    <td>{{ \Carbon\Carbon::parse($member->created_at)->format('d/m/Y') }}</td>

    <!-- Gender -->
    <td>{{ $member->gender ?? 'N/A' }}</td>

    <!-- Location -->
    <td>{{ $member->physical_location ?? 'N/A' }}</td>

    <!-- Monthly Income -->
    <td>
        @if ($member->monthly_income)
            Ksh {{ number_format($member->monthly_income) }}
        @else
            <span class="text-muted">N/A</span>
        @endif
    </td>

    <!-- Exported -->
    <td>
        @if ($member->exported == 'Y')
            <span class="badge bg-success px-3 py-1">Exported</span>
        @else
            <span class="badge bg-warning px-3 py-1">Pending</span>
        @endif
    </td>

    <!-- Contacted -->
    <td>
        <span class="badge {{ $member->contacted ? 'bg-success' : 'bg-danger' }} px-3 py-1">
            {{ $member->contacted ? 'Yes' : 'No' }}
        </span>
    </td>

    <!-- Actions -->
    <td class="text-center">

        <!-- View -->
        <button class="btn btn-outline-primary btn-sm view-details"
                data-id="{{ $member->id }}"
                data-bs-toggle="tooltip"
                title="View Application Details">
            <i class="i-Eye"></i>
        </button>

        <!-- Delete (only if NOT exported) -->
        @if ($member->exported !== 'Y')
        <button class="btn btn-outline-danger btn-sm delete-application"
                data-id="{{ $member->id }}"
                data-bs-toggle="tooltip"
                title="Delete Application">
            <i class="i-Close-Window"></i>
        </button>
        @endif

    </td>

</tr>
@endforeach
</tbody>


                    </table>
                </div>

                <!-- Pagination -->
                <div class="d-flex justify-content-center mt-4">
                    {!! $members->onEachSide(1)->links('pagination::bootstrap-5') !!}
                </div>
            </div>
        </div>
    </div>


    <!-- Modal -->
    <div class="modal fade" id="memberDetailsModal" tabindex="-1" aria-labelledby="memberDetailsModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content shadow-lg border-0">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="memberDetailsModalLabel">Member Details</h5>
                    <button type="button" class="btn-close text-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4" style="max-height:70vh;overflow-y:auto;">
                    <div id="modal-content">
                        <p class="text-center text-muted">Loading details...</p>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <div>
                        <button type="button" class="btn btn-success" id="printDetails">
                            <i class="bi bi-printer"></i> Print
                        </button>
                        <button type="button" class="btn btn-warning" id="exportLiveData" disabled>
                            <i class="bi bi-cloud-upload"></i> Export to Live
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const exportButton = document.getElementById('exportLiveData');
            const printButton = document.getElementById('printDetails');
            const modalElement = document.getElementById('memberDetailsModal');
            const modalInstance = new bootstrap.Modal(modalElement);

            let currentMemberId = null;
            let currentMemberName = '';

            // Clear duplicate listeners
            const newExportButton = exportButton.cloneNode(true);
            exportButton.parentNode.replaceChild(newExportButton, exportButton);

            // Handle View Details
            document.querySelectorAll('.view-details').forEach(button => {
                button.addEventListener('click', function() {
                    currentMemberId = this.dataset.id;
                    newExportButton.disabled = true;

                    const detailsUrl = "{{ route('members.details', ['id' => 'ID_PLACEHOLDER']) }}"
                        .replace('ID_PLACEHOLDER', currentMemberId);
                    console.log('Fetching:', detailsUrl);

                    fetch(detailsUrl)
                        .then(res => res.json())
                        .then(data => {
                            if (!data || data.error) {
                                alert(data.error || 'Member not found.');
                                return;
                            }

                            currentMemberName =
                                `${data.first_name || ''} ${data.last_name || ''}`.trim();
                            newExportButton.disabled = false;
                            newExportButton.innerHTML =
                                `<i class="bi bi-cloud-upload"></i> Export ${currentMemberName} to Live`;

                            const safe = v => v || 'N/A';
                            const parseArr = v => {
                                try {
                                    return JSON.parse(v || '[]');
                                } catch {
                                    return [];
                                }
                            };

                            let kinRows = '';
                            const kinNames = parseArr(data.next_of_kin_name);
                            kinNames.forEach((name, i) => {
                                kinRows += `
                            <tr>
                                <td>${name}</td>
                                <td>${parseArr(data.next_of_kin_relationship)[i] || ''}</td>
                                <td>${parseArr(data.next_of_kin_phone)[i] || ''}</td>
                                <td>${parseArr(data.next_of_kin_id_or_cert_no)[i] || ''}</td>
                                <td>${parseArr(data.kin_share_percent)[i] || ''}</td>
                            </tr>`;
                            });

                            document.getElementById('modal-content').innerHTML = `
                        <h4 class="text-primary mb-3">Personal Information</h4>
                        <div class="row mb-3">
                            <div class="col-md-6"><b>First Name:</b> ${safe(data.first_name)}</div>
                            <div class="col-md-6"><b>Last Name:</b> ${safe(data.last_name)}</div>
                            <div class="col-md-6"><b>Date of Birth:</b> ${safe(data.birth_date)}</div>
                            <div class="col-md-6"><b>National ID:</b> ${safe(data.national_id)}</div>
                            <div class="col-md-6"><b>Gender:</b> ${safe(data.gender)}</div>
                            <div class="col-md-6"><b>Marital Status:</b> ${safe(data.marital_status)}</div>
                            <div class="col-md-6"><b>Dependents:</b> ${safe(data.dependents)}</div>
                            <div class="col-md-6"><b>KRA PIN:</b> ${safe(data.kra_pin_no)}</div>
                        </div>
                        <h4 class="text-primary mb-3">Reason for Joining</h4>
                        <p>${safe(data.reason_for_joining)}</p>
                        <h4 class="text-primary mb-3">Financial Information</h4>
                        <div class="row mb-3">
                            <div class="col-md-6"><b>Monthly Income:</b> Ksh. ${safe(data.monthly_income)}</div>
                            <div class="col-md-6"><b>Preferred Contribution:</b> Ksh. ${safe(data.preferred_monthly_contribution)}</div>
                        </div>
                        <h4 class="text-primary mb-3">Bank Details</h4>
                        <div class="row mb-3">
                            <div class="col-md-6"><b>Bank:</b> ${safe(data.bank_name)}</div>
                            <div class="col-md-6"><b>Branch:</b> ${safe(data.bank_branch)}</div>
                            <div class="col-md-6"><b>Account No:</b> ${safe(data.bank_account_number)}</div>
                        </div>
                        <h4 class="text-primary mb-3">Next of Kin</h4>
                        <table class="table table-bordered">
                            <thead><tr><th>Name</th><th>Relationship</th><th>Phone</th><th>ID/Cert No</th><th>Share (%)</th></tr></thead>
                            <tbody>${kinRows}</tbody>
                        </table>
                    `;

                            modalInstance.show();
                        })
                        .catch(err => {
                            console.error('Error fetching details:', err);
                            alert('Failed to load member details.');
                        });
                });
            });

            // Export to live
            newExportButton.addEventListener('click', function() {
                if (!currentMemberId) {
                    alert('Please open a member first.');
                    return;
                }

                if (!confirm(`Are you sure you want to export ${currentMemberName}?`)) return;

                fetch("{{ route('members.exportLive') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': '{{ csrf_token() }}'
                        },
                        body: JSON.stringify({
                            member_id: currentMemberId
                        })
                    })
                    .then(res => res.json())
                    .then(data => {
                        alert(data.success ? '✅ Exported successfully.' : '⚠️ ' + data.message);
                    })
                    .catch(err => {
                        console.error('Export error:', err);
                        alert('Unexpected export error.');
                    });
            });

            // Print
            printButton.addEventListener('click', () => {
                const content = document.getElementById('modal-content').innerHTML;
                const w = window.open('', '_blank');
                w.document.write(`<html><head><title>Print</title>
            <style>body{font-family:Arial;margin:20px;}table{width:100%;border-collapse:collapse;}th,td{border:1px solid #000;padding:4px;}</style>
            </head><body>${content}</body></html>`);
                w.document.close();
                w.print();
            });
        });

        // Soft Delete Application
        document.querySelectorAll('.delete-application').forEach(btn => {
            btn.addEventListener('click', function() {

                const id = this.dataset.id;

                if (!confirm("Are you sure you want to delete this application?")) return;

                fetch("{{ route('new_members.delete') }}", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/json",
                            "X-CSRF-TOKEN": "{{ csrf_token() }}"
                        },
                        body: JSON.stringify({
                            id
                        })
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            alert("Application deleted.");
                            location.reload();
                        }
                    });
            });
        });
    </script>
@endsection
