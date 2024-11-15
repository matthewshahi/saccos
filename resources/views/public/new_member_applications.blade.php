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

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const autoSaveElements = document.querySelectorAll('.auto-save');

        autoSaveElements.forEach(element => {
            element.addEventListener('change', function () {
                const memberId = this.dataset.id;
                const field = this.dataset.field;
                const value = this.value;

                fetch(`/new_members/update/${memberId}`, {
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
    });
</script>
@endsection