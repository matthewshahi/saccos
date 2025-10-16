@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>New Member Applications</h1>
</div>
<div class="separator-breadcrumb border-top"></div>

<!-- Search Bar -->
<form action="{{ route('members.list') }}" method="GET" class="mb-4">
    <div class="input-group">
        <input type="text" name="search" class="form-control"
               placeholder="Search by name, email, phone, ID, or location"
               value="{{ request('search') }}">
        <button type="submit" class="btn btn-primary">Search</button>
    </div>
</form>

<div class="card">
    <div class="card-body">
        <h4 class="card-title mb-3">New Member Applications</h4>

        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#</th><th>Name</th><th>Email</th><th>Phone</th>
                        <th>ID</th><th>Location</th><th>Contacted</th>
                        <th>Contacted By</th><th>Contacted On</th>
                        <th>Comments</th><th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($members as $index => $member)
                    <tr>
                        <td>{{ $members->firstItem() + $index }}</td>
                        <td>{{ $member->first_name }} {{ $member->last_name }}</td>
                        <td>{{ $member->email }}</td>
                        <td>{{ $member->phone }}</td>
                        <td>{{ $member->national_id }}</td>
                        <td>{{ $member->physical_location }}</td>
                        <td>
                            <select class="form-control auto-save" data-id="{{ $member->id }}" data-field="contacted">
                                <option value="1" {{ $member->contacted ? 'selected' : '' }}>Yes</option>
                                <option value="0" {{ !$member->contacted ? 'selected' : '' }}>No</option>
                            </select>
                        </td>
                        <td><input type="text" class="form-control auto-save"
                                   data-id="{{ $member->id }}" data-field="contacted_by"
                                   value="{{ $member->contacted_by }}"></td>
                        <td><input type="date" class="form-control auto-save"
                                   data-id="{{ $member->id }}" data-field="contacted_on"
                                   value="{{ $member->contacted_on }}"></td>
                        <td><textarea class="form-control auto-save"
                                      data-id="{{ $member->id }}" data-field="comments">{{ $member->comments }}</textarea></td>
                        <td>
                            <button type="button" class="btn btn-info btn-sm view-details"
                                    data-id="{{ $member->id }}">
                                <i class="bi bi-eye"></i> View
                            </button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="d-flex justify-content-center mt-4">
            {{ $members->links() }}
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="memberDetailsModal" tabindex="-1" aria-labelledby="memberDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content shadow-lg border-0">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title" id="memberDetailsModalLabel">Member Details</h5>
                <button type="button" class="btn-close text-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4" style="max-height:70vh;overflow-y:auto;">
                <div id="modal-content"><p class="text-center text-muted">Loading details...</p></div>
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
document.addEventListener('DOMContentLoaded', function () {
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
        button.addEventListener('click', function () {
            currentMemberId = this.dataset.id;
            newExportButton.disabled = true;

            const detailsUrl = "{{ route('members.details', ['id' => 'ID_PLACEHOLDER']) }}".replace('ID_PLACEHOLDER', currentMemberId);
            console.log('Fetching:', detailsUrl);

            fetch(detailsUrl)
                .then(res => res.json())
                .then(data => {
                    if (!data || data.error) {
                        alert(data.error || 'Member not found.');
                        return;
                    }

                    currentMemberName = `${data.first_name || ''} ${data.last_name || ''}`.trim();
                    newExportButton.disabled = false;
                    newExportButton.innerHTML = `<i class="bi bi-cloud-upload"></i> Export ${currentMemberName} to Live`;

                    const safe = v => v || 'N/A';
                    const parseArr = v => { try { return JSON.parse(v || '[]'); } catch { return []; } };

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
    newExportButton.addEventListener('click', function () {
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
            body: JSON.stringify({ member_id: currentMemberId })
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
</script>
@endsection