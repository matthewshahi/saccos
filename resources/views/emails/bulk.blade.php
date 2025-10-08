@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Bulk Email to Members</h1>
    <div class="header-part-right">
        <ul>
            @if(Auth::check())
            <li>{{ Auth::user()->member_name ?? Auth::user()->name }}</li>
            @endif
        </ul>
    </div>
</div>
<div class="separator-breadcrumb border-top"></div>
@if(session('success'))
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <strong>✅ Success:</strong> {!! nl2br(e(session('success'))) !!}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

@if(session('error'))
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <strong>⚠️ Error:</strong> {!! nl2br(e(session('error'))) !!}
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
@endif

{{-- ========================= FILTER SECTION ========================= --}}
<div class="row">
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-body">

                <div class="card-title mb-3">Filter Members</div>
                <form method="GET" action="{{ route('emails.bulk') }}">
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <label for="search_name">Search Name</label>
                            <input type="text" name="search_name" id="search_name"
                                value="{{ $filters['search_name'] }}"
                                class="form-control" placeholder="Enter member name...">
                        </div>

                        <div class="col-md-3">
                            <label for="officials_only">Target Group</label>
                            <select name="officials_only" id="officials_only" class="form-control">
                                <option value="0" {{ !$filters['officials_only'] ? 'selected' : '' }}>All Members</option>
                                <option value="1" {{ $filters['officials_only'] ? 'selected' : '' }}>Officials Only</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label for="active">Status</label>
                            <select name="active" id="active" class="form-control">
                                <option value="Y" {{ $filters['active'] == 'Y' ? 'selected' : '' }}>Active</option>
                                <option value="N" {{ $filters['active'] == 'N' ? 'selected' : '' }}>Inactive</option>
                            </select>
                        </div>

                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

{{-- ========================= EMAIL COMPOSE SECTION ========================= --}}
<div class="row">
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-body">
                <div class="card-title mb-3">Compose Bulk Email</div>
                <form method="POST" action="{{ route('emails.bulk.send') }}">
                    <div class="alert alert-danger">
                        <strong>⚠️ Important Notice:</strong><br>
                        This is a <strong>bulk email</strong> module. Please use it responsibly.
                        The sender’s details, including your <strong>account identity</strong>, <strong>machine information</strong>, and <strong>IP address</strong>, are being logged.
                        Any misuse, spam, or abuse of this feature will be <strong>personally attributed</strong> to the sender and may result in disciplinary action.
                    </div>
                    @csrf
                    @csrf
                    <input type="hidden" name="active" value="{{ $filters['active'] }}">
                    <input type="hidden" name="include_deleted" value="{{ $filters['include_deleted'] ? 1 : 0 }}">
                    <input type="hidden" name="officials_only" value="{{ $filters['officials_only'] ? 1 : 0 }}">
                    <input type="hidden" name="search_name" value="{{ $filters['search_name'] }}">

                    <div class="row">
                        <div class="col-md-6 form-group mb-3">
                            <label for="subject">Email Subject</label>
                            <input type="text" name="subject" id="subject" class="form-control" placeholder="Enter subject" required>
                        </div>
                        <div class="col-md-12 form-group mb-3">
                            <label for="message">Message Body</label>
                            <textarea name="message" id="message" class="form-control" rows="5" placeholder="Enter your message..." required></textarea>
                        </div>
                    </div>


                    <div class="alert alert-info">
                        All members matching the current filters will be included automatically.
                        Use filters above to narrow your target list before sending.
                    </div>
                    <div style="overflow-x:auto;">
                        <table class="table table-bordered table-striped">
                            <thead>
    <tr>
        <th>#</th>
        <th><input type="checkbox" id="selectAll" checked disabled></th>
        <th>Name</th>
        <th>Email</th>
        <th>Phone</th>
        <th>Status</th>
    </tr>
</thead>
<tbody>
    @foreach($members as $m)
    <tr>
        <td>{{ $loop->iteration + ($members->firstItem() - 1) }}</td>
        <td><input type="checkbox" name="member_ids[]" value="{{ $m->member_id }}"></td>
        <td>{{ $m->member_name }}</td>
        <td>{{ $m->member_email }}</td>
        <td>{{ $m->member_phone_no }}</td>
        <td>
            @if($m->member_active == 'Y')
                <span class="badge bg-success text-white">Active</span>
            @else
                <span class="badge bg-secondary text-white">Inactive</span>
            @endif
        </td>
    </tr>
    @endforeach
</tbody>
                        </table>
                    </div>

                    {{ $members->links('pagination::bootstrap-5') }}

                    <div class="mt-3 text-right">
                        <button type="submit" class="btn btn-success">Send Emails to Selected Members</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.getElementById('selectAll').addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('input[name="member_ids[]"]');
        checkboxes.forEach(c => c.checked = this.checked);
    });
</script>


@endsection