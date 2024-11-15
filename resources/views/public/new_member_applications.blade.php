@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>New Member Applications</h1>
</div>
<div class="separator-breadcrumb border-top"></div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

<div class="card mb-4">
    <div class="card-body">
        <h4 class="card-title mb-3">List of New Members</h4>
        <div class="table-responsive">
            <table class="table table-light">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>ID Number</th>
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
                            <td>{{ $member->first_name }}</td>
                            <td>{{ $member->last_name }}</td>
                            <td>{{ $member->email }}</td>
                            <td>{{ $member->phone }}</td>
                            <td>{{ $member->national_id }}</td>
                            <td>{{ $member->physical_location }}</td>
                            <td>
                                <select name="contacted" data-member-id="{{ $member->id }}" class="form-control auto-save-field">
                                    <option value="0" {{ $member->contacted == 0 ? 'selected' : '' }}>No</option>
                                    <option value="1" {{ $member->contacted == 1 ? 'selected' : '' }}>Yes</option>
                                </select>
                            </td>
                            <td>
                                <input type="text" name="contacted_by" value="{{ $member->contacted_by }}" data-member-id="{{ $member->id }}" class="form-control auto-save-field" placeholder="Enter name">
                            </td>
                            <td>
                                <input type="date" name="contacted_on" value="{{ $member->contacted_on }}" data-member-id="{{ $member->id }}" class="form-control auto-save-field">
                            </td>
                            <td>
                                <input type="text" name="comments" value="{{ $member->comments }}" data-member-id="{{ $member->id }}" class="form-control auto-save-field" placeholder="Enter comments">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Pagination Links -->
        <div class="d-flex justify-content-center mt-4">
            {{ $members->links() }}
        </div>
    </div>
</div>

<!-- JavaScript to handle auto-save on field change -->
<script>
    document.querySelectorAll('.auto-save-field').forEach(field => {
        field.addEventListener('change', function() {
            const memberId = this.dataset.memberId;
            const fieldName = this.name;
            const fieldValue = this.value;

            // Construct the URL using relative path for the current application root
            const updateUrl = `{{ url('/new_members/update') }}/${memberId}`;

            fetch(updateUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    field: fieldName,
                    value: fieldValue
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Field updated successfully');
                } else {
                    console.error('Failed to update field');
                }
            })
            .catch(error => console.error('Error:', error));
        });
    });
</script>
@endsection