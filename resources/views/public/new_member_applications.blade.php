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
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="memberDetailsModalLabel">Member Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="modal-content">
                    <p class="text-center text-muted">Loading details...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Auto-save functionality
        const autoSaveElements = document.querySelectorAll('.auto-save');

        autoSaveElements.forEach(element => {
            element.addEventListener('change', function () {
                const memberId = this.dataset.id;
                const field = this.dataset.field;
                const value = this.value;

                fetch(`{{ url('/new_members/update') }}/${memberId}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({ field, value })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status !== 'success') {
                        alert('Error updating field');
                    }
                })
                .catch(error => console.error('Error:', error));
            });
        });

        // View details button click
        const viewDetailsButtons = document.querySelectorAll('.view-details');
        viewDetailsButtons.forEach(button => {
            button.addEventListener('click', function () {
                const memberId = this.dataset.id;

                fetch(`{{ url('/new_members/details') }}/${memberId}`)
                    .then(response => response.json())
                    .then(data => {
                        const modalContent = document.getElementById('modal-content');
                        modalContent.innerHTML = `
                            <p><strong>Name:</strong> ${data.first_name} ${data.last_name}</p>
                            <p><strong>Email:</strong> ${data.email}</p>
                            <p><strong>Phone:</strong> ${data.phone}</p>
                            <p><strong>National ID:</strong> ${data.national_id}</p>
                            <p><strong>Location:</strong> ${data.physical_location}</p>
                            <p><strong>Marital Status:</strong> ${data.marital_status}</p>
                            <p><strong>Gender:</strong> ${data.gender}</p>
                            <p><strong>Dependents:</strong> ${data.dependents}</p>
                            <p><strong>Next of Kin:</strong></p>
                            <ul>
                                ${JSON.parse(data.next_of_kin_name).map((name, index) => `
                                    <li>
                                        <strong>Name:</strong> ${name}, 
                                        <strong>Relationship:</strong> ${JSON.parse(data.next_of_kin_relationship)[index]}, 
                                        <strong>Phone:</strong> ${JSON.parse(data.next_of_kin_phone)[index]},
                                        <strong>Share:</strong> ${JSON.parse(data.kin_share_percent)[index]}%
                                    </li>
                                `).join('')}
                            </ul>
                        `;
                        new bootstrap.Modal(document.getElementById('memberDetailsModal')).show();
                    })
                    .catch(error => console.error('Error fetching member details:', error));
            });
        });
    });
</script>
@endsection