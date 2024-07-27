@extends('layouts.app')

@section('content')
<div class="breadcrumb d-flex justify-content-between align-items-center">
    <h1>Modify User Access Rights</h1>
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
    <div class="col-md-12">
        <div class="card mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between mb-3">
                    <div>
                        <label for="user" class="form-label">User: </label>
                        <select id="user" class="form-select" onchange="reloadWithUser(this)">
                            <option value="">Select a user</option>
                            @foreach($members as $member)
                                <option value="{{ $member->member_id }}" {{ $rightsUserId == $member->member_id ? 'selected' : '' }}>
                                    {{ $member->member_name }} ({{ $member->member_phone_no }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <button class="btn btn-primary" onclick="updateAllRights(true)">Grant All</button>
                        <button class="btn btn-danger" onclick="updateAllRights(false)">Revoke All</button> 
                        <a href="{{ route('admin.access-rights.add.module') }}" class="btn btn-success">Add Module</a>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table text-left">
                        <thead>
                            <tr>
                                <th scope="col">#</th>
                                <th scope="col">Module</th>
                                <th scope="col">Grant Access</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($modules as $index => $module)
                                <tr>
                                    <th scope="row">{{ $index + 1 }}</th>
                                    <td>{{ $module->module_name }}</td>
                                    <td>
                                        <label class="switch pe-5 switch-success me-3">
                                            <input type="checkbox" {{ $module->rights_access == 'Y' ? 'checked' : '' }} onchange="updateRights({{ $module->module_id }}, this)">
                                            <span class="slider"></span>
                                        </label>
                                        <span class="access-label">{{ $module->rights_access == 'Y' ? 'Granted' : 'Denied' }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function reloadWithUser(select) {
    const memberId = select.value;
    window.location.href = `?rights_user=${memberId}`;
}

function updateRights(moduleId, checkbox) {
    const rightsUserId = new URLSearchParams(window.location.search).get('rights_user');
    const granted = checkbox.checked;

    if (!rightsUserId) {
        alert('Please select a user first.');
        checkbox.checked = !granted;
        return;
    }

    fetch("{{ route('admin.access-rights.save') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            module_id: moduleId,
            rights_user: rightsUserId,
            granted: granted
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const label = checkbox.closest('td').querySelector('.access-label');
            label.textContent = granted ? 'Granted' : 'Denied';
        } else {
            alert('Failed to update rights. Please try again.');
            checkbox.checked = !granted;
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update rights. Please try again.');
        checkbox.checked = !granted;
    });
}

function updateAllRights(granted) {
    const rightsUserId = new URLSearchParams(window.location.search).get('rights_user');

    if (!rightsUserId) {
        alert('Please select a user first.');
        return;
    }

    fetch("{{ route('admin.access-rights.save') }}", {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({
            rights_user: rightsUserId,
            granted: granted,
            bulk: true
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            document.querySelectorAll('.access-label').forEach(label => {
                label.textContent = granted ? 'Granted' : 'Denied';
            });
            document.querySelectorAll('input[type="checkbox"]').forEach(checkbox => {
                checkbox.checked = granted;
            });
        } else {
            alert('Failed to update rights. Please try again.');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Failed to update rights. Please try again.');
    });
}
</script>
@endsection
