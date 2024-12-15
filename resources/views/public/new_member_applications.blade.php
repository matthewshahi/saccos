@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>New Member Applications</h1>
</div>
<div class="separator-breadcrumb border-top"></div>

<!-- Search Bar -->
<form action="{{ route('members.list') }}" method="GET" class="mb-4">
    <div class="input-group">
        <input type="text" name="search" class="form-control" placeholder="Search by name, email, phone, ID, or location" value="{{ request('search') }}">
        <button type="submit" class="btn btn-primary">Search</button>
    </div>
</form>

<div class="card">
    <div class="card-body">
        <h4 class="card-title mb-3">New Member Applications</h4>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>ID</th>
                        <th>Location</th>
                        <th>Contacted</th>
                        <th>Contacted By</th>
                        <th>Contacted On</th>
                        <th>Comments</th>
                        <th>Actions</th>
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
                            <td>
                                <input type="text" class="form-control auto-save" data-id="{{ $member->id }}" data-field="contacted_by" value="{{ $member->contacted_by }}">
                            </td>
                            <td>
                                <input type="date" class="form-control auto-save" data-id="{{ $member->id }}" data-field="contacted_on" value="{{ $member->contacted_on }}">
                            </td>
                            <td>
                                <textarea class="form-control auto-save" data-id="{{ $member->id }}" data-field="comments">{{ $member->comments }}</textarea>
                            </td>
                            <td>
                                <button type="button" class="btn btn-info btn-sm view-details" data-id="{{ $member->id }}">View Details</button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
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
                <button type="button" class="btn-close text-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" style="max-height: 70vh; overflow-y: auto;">
                <div id="modal-content">
                    <p class="text-center text-muted">Loading details...</p>
                </div>
            </div>
            <div class="modal-footer justify-content-between">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <div>
                    <button type="button" class="btn btn-info" id="downloadDetails"><i class="bi bi-download"></i> Download</button>
                    <button type="button" class="btn btn-success" id="printDetails"><i class="bi bi-printer"></i> Print</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const viewDetailsButtons = document.querySelectorAll('.view-details');

        viewDetailsButtons.forEach(button => {
            button.addEventListener('click', function () {
                const memberId = this.dataset.id;

                fetch(`{{ url('/new_members/details') }}/${memberId}`)
                    .then(response => response.json())
                    .then(data => {
                        const modalContent = document.getElementById('modal-content');
                        modalContent.innerHTML = `
                            <div class="text-center mb-4">
                                <img src="{{ asset($domainLogoPath) }}" alt="Logo" style="max-height: 100px;">
                            </div>
                            <h4 class="text-primary mb-3">Personal Information</h4>
                            <div class="row mb-3">
                                <div class="col-md-6"><strong>First Name:</strong> ${data.first_name || 'N/A'}</div>
                                <div class="col-md-6"><strong>Last Name:</strong> ${data.last_name || 'N/A'}</div>
                                <div class="col-md-6"><strong>Date of Birth:</strong> ${data.birth_date || 'N/A'}</div>
                                <div class="col-md-6"><strong>National ID:</strong> ${data.national_id || 'N/A'}</div>
                                <div class="col-md-6"><strong>Gender:</strong> ${data.gender || 'N/A'}</div>
                                <div class="col-md-6"><strong>Marital Status:</strong> ${data.marital_status || 'N/A'}</div>
                                <div class="col-md-6"><strong>Occupation:</strong> ${data.occupation || 'N/A'}</div>
                            </div>

                            <h4 class="text-primary mb-3">Financial Information</h4>
                            <div class="row mb-3">
                                <div class="col-md-6"><strong>Monthly Income:</strong> Ksh. ${data.monthly_income || 'N/A'}</div>
                                <div class="col-md-6"><strong>Preferred Monthly Contribution:</strong> Ksh. ${data.preferred_monthly_contribution || 'N/A'}</div>
                                <div class="col-md-6"><strong>Number of Dependents:</strong> ${data.dependents || 'N/A'}</div>
                                <div class="col-md-6"><strong>Reason for Joining:</strong> ${data.reason_for_joining || 'N/A'}</div>
                            </div>

                            <h4 class="text-primary mb-3">Contact Information</h4>
                            <div class="row mb-3">
                                <div class="col-md-6"><strong>Email:</strong> ${data.email || 'N/A'}</div>
                                <div class="col-md-6"><strong>Phone:</strong> ${data.phone || 'N/A'}</div>
                                <div class="col-md-6"><strong>Location:</strong> ${data.physical_location || 'N/A'}</div>
                            </div>

                            <h4 class="text-primary mb-3">Bank Details</h4>
                            <div class="row mb-3">
                                <div class="col-md-6"><strong>Bank Name:</strong> ${data.bank_name || 'N/A'}</div>
                                <div class="col-md-6"><strong>Branch:</strong> ${data.bank_branch || 'N/A'}</div>
                                <div class="col-md-6"><strong>Account Number:</strong> ${data.bank_account_number || 'N/A'}</div>
                            </div>

                            <h4 class="text-primary mb-3">Next of Kin</h4>
                            <table class="table table-bordered">
                                <thead class="table-primary">
                                    <tr>
                                        <th>Name</th>
                                        <th>Relationship</th>
                                        <th>Phone</th>
                                        <th>ID/Cert No</th>
                                        <th>Share (%)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${JSON.parse(data.next_of_kin_name).map((name, index) => `
                                        <tr>
                                            <td>${name || 'N/A'}</td>
                                            <td>${JSON.parse(data.next_of_kin_relationship)[index] || 'N/A'}</td>
                                            <td>${JSON.parse(data.next_of_kin_phone)[index] || 'N/A'}</td>
                                            <td>${JSON.parse(data.next_of_kin_id_or_cert_no)[index] || 'N/A'}</td>
                                            <td>${JSON.parse(data.kin_share_percent)[index] || 'N/A'}</td>
                                        </tr>
                                    `).join('')}
                                </tbody>
                            </table>

                            <div class="mt-5 text-center">
                                <p><strong>I certify that the information given here above is correct to the best of my knowledge.</strong></p>
                                <p>Signature of applicant: ____________________________</p>
                                <p>Date: ______________________________________________</p>
                            </div>
                        `;
                        new bootstrap.Modal(document.getElementById('memberDetailsModal')).show();
                    })
                    .catch(error => console.error('Error fetching member details:', error));
            });
        });

        // Print Functionality
        document.getElementById('printDetails').addEventListener('click', function () {
            const modalContent = document.getElementById('modal-content').innerHTML;
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>Member Details</title>
                        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css">
                        <style>
                            body { font-family: Arial, sans-serif; margin: 40px; }
                            h4 { color: #007bff; margin-top: 20px; }
                            table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                            table, th, td { border: 1px solid #000; padding: 8px; text-align: left; }
                            .text-primary { color: #007bff; }
                            .text-center { text-align: center; }
                            img { max-height: 100px; margin-bottom: 20px; }
                        </style>
                    </head>
                    <body>
                        <div class="container text-center">
                            ${modalContent}
                        </div>
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        });
    });
</script>
@endsection